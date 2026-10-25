<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = (string) ($_GET['action'] ?? '');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');

if ($method === 'GET' && $action === 'me') {
    $context = mobile_api_authenticate($pdo);
    $statement = $pdo->prepare(
        'SELECT id, nome_completo, email, username, perfil, perfil_imagem
         FROM usuarios WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => (int) $context['user_id']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        mobile_api_error(404, 'ACCOUNT_NOT_FOUND', 'Conta não encontrada.');
    }
    mobile_api_respond(200, ['account_type' => $context['account_type'], 'user' => $user]);
}

if ($method !== 'POST' || !in_array($action, ['login', 'refresh', 'logout'], true)) {
    header('Allow: GET, POST');
    mobile_api_error(405, 'METHOD_NOT_ALLOWED', 'Ação ou método HTTP não permitido.');
}

$payload = mobile_api_read_json();

if ($action === 'login') {
    $identifier = $payload['identifier'] ?? null;
    $password = $payload['password'] ?? null;
    $accountType = $payload['account_type'] ?? 'user';
    $deviceName = $payload['device_name'] ?? '';
    if (
        !is_string($identifier) || trim($identifier) === '' || mb_strlen($identifier, 'UTF-8') > 255
        || !is_string($password) || $password === '' || strlen($password) > 4096
        || !is_string($accountType) || !in_array($accountType, ['user', 'institution'], true)
        || !is_string($deviceName) || mb_strlen($deviceName, 'UTF-8') > 100
    ) {
        mobile_api_error(400, 'INVALID_CREDENTIALS_FORMAT', 'Informe identificador, senha e tipo de conta válidos.');
    }

    $identifier = trim($identifier);
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipWait = auth_rate_limit_remaining($pdo, 'mobile-login-ip', $clientIp, 20, 900);
    $identifierWait = auth_rate_limit_remaining($pdo, 'mobile-login-identifier', strtolower($identifier), 10, 900);
    if ($ipWait > 0 || $identifierWait > 0) {
        header('Retry-After: ' . (string) max($ipWait, $identifierWait));
        mobile_api_error(429, 'RATE_LIMITED', 'Muitas tentativas. Aguarde antes de tentar novamente.');
    }

    $statement = $pdo->prepare(
        'SELECT id, nome_completo, email, username, senha, perfil, perfil_imagem, auth_version
         FROM usuarios
         WHERE LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:username)
         LIMIT 1'
    );
    $statement->execute(['email' => $identifier, 'username' => $identifier]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    $profileMatches = is_array($user) && mobile_account_profile_is_valid($accountType, (string) $user['perfil']);
    $valid = $profileMatches && password_verify($password, (string) $user['senha']);
    if (!$valid) {
        auth_rate_limit_record_failure($pdo, 'mobile-login-ip', $clientIp, 20, 900);
        auth_rate_limit_record_failure($pdo, 'mobile-login-identifier', strtolower($identifier), 10, 900);
        mobile_api_error(401, 'INVALID_CREDENTIALS', 'Identificador ou senha inválidos.');
    }
    if ($accountType === 'institution') {
        $organization = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
        $organization->execute(['user_id' => (int) $user['id']]);
        if (!$organization->fetchColumn()) {
            auth_rate_limit_record_failure($pdo, 'mobile-login-ip', $clientIp, 20, 900);
            auth_rate_limit_record_failure($pdo, 'mobile-login-identifier', strtolower($identifier), 10, 900);
            mobile_api_error(401, 'INVALID_CREDENTIALS', 'Identificador ou senha inválidos.');
        }
    }

    $tokens = mobile_issue_token_pair(
        $pdo,
        (int) $user['id'],
        $accountType,
        (int) $user['auth_version'],
        trim($deviceName)
    );
    auth_rate_limit_clear($pdo, 'mobile-login-identifier', strtolower($identifier));
    unset($user['senha'], $user['auth_version']);
    mobile_api_respond(200, ['tokens' => $tokens, 'account_type' => $accountType, 'user' => $user]);
}

if ($action === 'refresh') {
    $refreshToken = $payload['refresh_token'] ?? null;
    if (!is_string($refreshToken)) {
        mobile_api_error(400, 'INVALID_REFRESH_TOKEN', 'Informe um token de renovação válido.');
    }
    $tokens = mobile_rotate_refresh_token($pdo, $refreshToken);
    if ($tokens === null || !empty($tokens['reuse_detected'])) {
        mobile_api_error(401, 'INVALID_REFRESH_TOKEN', 'A credencial de renovação expirou, foi revogada ou já foi utilizada.');
    }
    mobile_api_respond(200, ['tokens' => $tokens]);
}

$refreshToken = $payload['refresh_token'] ?? null;
if (!is_string($refreshToken) || $refreshToken === '') {
    mobile_api_error(400, 'INVALID_REFRESH_TOKEN', 'Informe o token de renovação para encerrar a sessão.');
}
mobile_revoke_refresh_token($pdo, $refreshToken);
mobile_api_respond(200, ['revoked' => true]);
