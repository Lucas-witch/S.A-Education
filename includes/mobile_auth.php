<?php
declare(strict_types=1);

const MOBILE_ACCESS_TOKEN_TTL = 900;
const MOBILE_REFRESH_TOKEN_TTL = 2592000;

function mobile_token_value(): string {
    return bin2hex(random_bytes(32));
}

function mobile_token_hash(string $token): string {
    return hash('sha256', $token);
}

function mobile_token_expiry(int $seconds): string {
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

function mobile_utc_now(): string {
    return gmdate('Y-m-d H:i:s');
}

function mobile_issue_token_pair(PDO $pdo, int $userId, string $accountType, int $authVersion, string $deviceName = ''): array {
    if ($userId <= 0 || !in_array($accountType, ['user', 'institution'], true)) {
        throw new InvalidArgumentException('Conta mobile inválida.');
    }

    $accessToken = mobile_token_value();
    $refreshToken = mobile_token_value();
    $pdo->beginTransaction();
    try {
        $session = $pdo->prepare(
            'INSERT INTO mobile_auth_sessions
                (user_id, account_type, auth_version, device_name, created_at, last_used_at)
             VALUES
                    (:user_id, :account_type, :auth_version, :device_name, :created_at, :last_used_at)'
        );
        $session->execute([
            'user_id' => $userId,
            'account_type' => $accountType,
            'auth_version' => $authVersion,
            'device_name' => $deviceName !== '' ? $deviceName : null,
            'created_at' => mobile_utc_now(),
            'last_used_at' => mobile_utc_now(),
        ]);
        $sessionId = (int) $pdo->lastInsertId();
        mobile_insert_token($pdo, $sessionId, 'access', $accessToken, MOBILE_ACCESS_TOKEN_TTL);
        mobile_insert_token($pdo, $sessionId, 'refresh', $refreshToken, MOBILE_REFRESH_TOKEN_TTL);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => MOBILE_ACCESS_TOKEN_TTL,
        'refresh_expires_in' => MOBILE_REFRESH_TOKEN_TTL,
    ];
}

function mobile_insert_token(PDO $pdo, int $sessionId, string $type, string $token, int $ttl): void {
    $statement = $pdo->prepare(
        'INSERT INTO mobile_auth_tokens (session_id, token_hash, token_type, expires_at, created_at)
         VALUES (:session_id, :token_hash, :token_type, :expires_at, :created_at)'
    );
    $statement->execute([
        'session_id' => $sessionId,
        'token_hash' => mobile_token_hash($token),
        'token_type' => $type,
        'expires_at' => mobile_token_expiry($ttl),
        'created_at' => mobile_utc_now(),
    ]);
}

function mobile_find_access_context(PDO $pdo, string $token): ?array {
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT t.id AS token_id, t.expires_at AS token_expires_at,
                s.id AS session_id, s.user_id, s.account_type, s.auth_version,
                u.id, u.nome_completo, u.email, u.username, u.perfil,
                u.perfil_imagem, u.auth_version AS current_auth_version
         FROM mobile_auth_tokens t
         INNER JOIN mobile_auth_sessions s ON s.id = t.session_id
         INNER JOIN usuarios u ON u.id = s.user_id
         WHERE t.token_hash = :token_hash
           AND t.token_type = :token_type
           AND t.consumed_at IS NULL
           AND t.revoked_at IS NULL
           AND s.revoked_at IS NULL
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => mobile_token_hash($token),
        'token_type' => 'access',
    ]);
    $context = $statement->fetch(PDO::FETCH_ASSOC);
    if (
        !is_array($context)
        || strtotime((string) $context['token_expires_at'] . ' UTC') <= time()
        || (int) $context['auth_version'] !== (int) $context['current_auth_version']
        || !mobile_account_profile_is_valid((string) $context['account_type'], (string) $context['perfil'])
    ) {
        if (is_array($context) && (int) $context['auth_version'] !== (int) $context['current_auth_version']) {
            mobile_revoke_session($pdo, (int) $context['session_id']);
        }
        return null;
    }

    $touch = $pdo->prepare(
        'UPDATE mobile_auth_sessions SET last_used_at = :last_used_at WHERE id = :id AND revoked_at IS NULL'
    );
    $touch->execute(['id' => (int) $context['session_id'], 'last_used_at' => mobile_utc_now()]);
    return $context;
}

function mobile_account_profile_is_valid(string $accountType, string $profile): bool {
    return $accountType === 'user'
        ? in_array($profile, ['estudante', 'professor'], true)
        : $profile === 'instituicao';
}

function mobile_rotate_refresh_token(PDO $pdo, string $refreshToken): ?array {
    if (preg_match('/^[a-f0-9]{64}$/', $refreshToken) !== 1) {
        return null;
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $pdo->prepare(
            'SELECT t.id AS token_id, t.session_id, t.expires_at, t.consumed_at, t.revoked_at,
                    s.user_id, s.account_type, s.auth_version, s.revoked_at AS session_revoked_at,
                    u.perfil, u.auth_version AS current_auth_version
             FROM mobile_auth_tokens t
             INNER JOIN mobile_auth_sessions s ON s.id = t.session_id
             INNER JOIN usuarios u ON u.id = s.user_id
             WHERE t.token_hash = :token_hash AND t.token_type = :token_type
             LIMIT 1' . $lock
        );
        $statement->execute([
            'token_hash' => mobile_token_hash($refreshToken),
            'token_type' => 'refresh',
        ]);
        $token = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($token)) {
            $pdo->rollBack();
            return null;
        }

        if ($token['consumed_at'] !== null) {
            mobile_revoke_session($pdo, (int) $token['session_id']);
            $pdo->commit();
            return ['reuse_detected' => true];
        }
        if (
            $token['revoked_at'] !== null
            || $token['session_revoked_at'] !== null
            || strtotime((string) $token['expires_at'] . ' UTC') <= time()
            || (int) $token['auth_version'] !== (int) $token['current_auth_version']
            || !mobile_account_profile_is_valid((string) $token['account_type'], (string) $token['perfil'])
        ) {
            if ((int) $token['auth_version'] !== (int) $token['current_auth_version']) {
                mobile_revoke_session($pdo, (int) $token['session_id']);
            }
            $pdo->commit();
            return null;
        }

        $consume = $pdo->prepare(
            'UPDATE mobile_auth_tokens SET consumed_at = :consumed_at
             WHERE id = :id AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        $consume->execute(['id' => (int) $token['token_id'], 'consumed_at' => mobile_utc_now()]);
        if ($consume->rowCount() !== 1) {
            mobile_revoke_session($pdo, (int) $token['session_id']);
            $pdo->commit();
            return ['reuse_detected' => true];
        }

        $accessToken = mobile_token_value();
        $nextRefreshToken = mobile_token_value();
        mobile_insert_token($pdo, (int) $token['session_id'], 'access', $accessToken, MOBILE_ACCESS_TOKEN_TTL);
        mobile_insert_token($pdo, (int) $token['session_id'], 'refresh', $nextRefreshToken, MOBILE_REFRESH_TOKEN_TTL);
        $touch = $pdo->prepare(
            'UPDATE mobile_auth_sessions SET last_used_at = :last_used_at WHERE id = :id'
        );
        $touch->execute(['id' => (int) $token['session_id'], 'last_used_at' => mobile_utc_now()]);
        $pdo->commit();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $nextRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => MOBILE_ACCESS_TOKEN_TTL,
            'refresh_expires_in' => MOBILE_REFRESH_TOKEN_TTL,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mobile_revoke_session(PDO $pdo, int $sessionId): void {
    $session = $pdo->prepare(
        'UPDATE mobile_auth_sessions
         SET revoked_at = COALESCE(revoked_at, :revoked_at)
         WHERE id = :id'
    );
    $session->execute(['id' => $sessionId, 'revoked_at' => mobile_utc_now()]);
    $tokens = $pdo->prepare(
        'UPDATE mobile_auth_tokens
         SET revoked_at = COALESCE(revoked_at, :revoked_at)
         WHERE session_id = :session_id AND revoked_at IS NULL'
    );
    $tokens->execute(['session_id' => $sessionId, 'revoked_at' => mobile_utc_now()]);
}

function mobile_revoke_refresh_token(PDO $pdo, string $refreshToken): void {
    if (preg_match('/^[a-f0-9]{64}$/', $refreshToken) !== 1) {
        return;
    }
    $statement = $pdo->prepare(
        'SELECT session_id FROM mobile_auth_tokens
         WHERE token_hash = :token_hash AND token_type = :token_type
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => mobile_token_hash($refreshToken),
        'token_type' => 'refresh',
    ]);
    $sessionId = $statement->fetchColumn();
    if ($sessionId !== false) {
        mobile_revoke_session($pdo, (int) $sessionId);
    }
}
