<?php
declare(strict_types=1);

require_once __DIR__ . '/feed.php';

const VIDEO_AD_INTERVAL_SECONDS = 600;
const VIDEO_HEARTBEAT_MAX_GAP_SECONDS = 30;
const VIDEO_MAX_PLAYBACK_RATE = 2.5;
const VIDEO_POSITION_TOLERANCE_SECONDS = 2.0;
const VIDEO_MIN_POSITION_RATIO = 0.5;

interface VideoAdProvider {
    public function status(): array;
    public function requestBreak(int $userId, int $publicationId, int $intervalNumber): ?array;
    public function verifyCompletion(int $userId, int $publicationId, int $intervalNumber, string $receipt): bool;
}

final class UnconfiguredVideoAdProvider implements VideoAdProvider {
    public function status(): array {
        return [
            'configured' => false,
            'mode' => 'test',
            'message' => 'Modo de teste: nenhum anúncio real foi configurado ou exibido.',
        ];
    }

    public function requestBreak(int $userId, int $publicationId, int $intervalNumber): ?array {
        return null;
    }

    public function verifyCompletion(int $userId, int $publicationId, int $intervalNumber, string $receipt): bool {
        return false;
    }
}

function video_ad_provider(): VideoAdProvider {
    static $provider = null;
    if (!$provider instanceof VideoAdProvider) {
        $provider = new UnconfiguredVideoAdProvider();
    }
    return $provider;
}

function video_ad_provider_status(): array {
    return video_ad_provider()->status();
}

function video_account_requires_ads(bool $isAdmin, bool $isPremium): bool {
    if ($isAdmin) {
        return false;
    }
    return !$isPremium || strtolower(trim((string) (getenv('VIDEO_PREMIUM_AD_FREE') ?: ''))) !== 'true';
}

function video_get_authorized_lesson(PDO $pdo, int $publicationId, int $userId, bool $premium): ?array {
    if ($publicationId <= 0 || $userId <= 0) {
        return null;
    }
    $scope = feed_authorization_scope($userId);
    $premiumAccessSql = $premium ? '1 = 1' : '1 = 0';
    $query = $pdo->prepare(
        'SELECT p.*, COALESCE(i.nome, u.nome_completo) AS responsible_name
         FROM publicacoes p
         INNER JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN instituicoes i ON i.id = p.instituicao_id
         WHERE p.id = :publication_id
           AND p.tipo = "video_aula"
           AND p.status = "publicado"
           AND (
               p.is_premium_only = 0
               OR p.usuario_id = :video_owner_id
               OR ' . $premiumAccessSql . '
           )
           AND ' . $scope['sql'] . '
         LIMIT 1'
    );
    $query->execute([
        ':publication_id' => $publicationId,
        ':video_owner_id' => $userId,
    ] + $scope['params']);
    $lesson = $query->fetch(PDO::FETCH_ASSOC);
    return is_array($lesson) ? $lesson : null;
}

function video_learning_start(PDO $pdo, int $userId, int $publicationId, int $now = 0): array {
    if ($userId <= 0 || $publicationId <= 0) {
        throw new InvalidArgumentException('Aula ou conta inválida.');
    }
    $now = $now > 0 ? $now : time();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $progressQuery = $pdo->prepare(
        'SELECT played_seconds, last_position FROM video_learning_progress
         WHERE user_id = :user_id AND publication_id = :publication_id LIMIT 1'
    );
    $progressQuery->execute(['user_id' => $userId, 'publication_id' => $publicationId]);
    $progress = $progressQuery->fetch(PDO::FETCH_ASSOC) ?: ['played_seconds' => 0, 'last_position' => 0];

    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT INTO video_playback_sessions
             (user_id, publication_id, token_hash, last_position, heartbeat_at, is_playing, client_sequence)
           VALUES (:user_id, :publication_id, :token_hash, :last_position, :heartbeat_at, 0, 0)
           ON CONFLICT(user_id, publication_id) DO UPDATE SET
             token_hash = excluded.token_hash, last_position = excluded.last_position,
             heartbeat_at = excluded.heartbeat_at, is_playing = 0, client_sequence = 0'
        : 'INSERT INTO video_playback_sessions
             (user_id, publication_id, token_hash, last_position, heartbeat_at, is_playing, client_sequence)
           VALUES (:user_id, :publication_id, :token_hash, :last_position, :heartbeat_at, 0, 0)
           ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash),
             last_position = VALUES(last_position), heartbeat_at = VALUES(heartbeat_at),
             is_playing = 0, client_sequence = 0';
    $pdo->prepare($sql)->execute([
        'user_id' => $userId,
        'publication_id' => $publicationId,
        'token_hash' => $tokenHash,
        'last_position' => (float) $progress['last_position'],
        'heartbeat_at' => $now,
    ]);

    return [
        'session_token' => $token,
        'played_seconds' => (float) $progress['played_seconds'],
        'last_position' => (float) $progress['last_position'],
        'ad_provider' => video_ad_provider_status(),
    ];
}

function video_learning_heartbeat(
    PDO $pdo,
    int $userId,
    int $publicationId,
    string $token,
    float $position,
    bool $isPlaying,
    bool $requiresAds,
    int $sequence,
    int $now = 0
): array {
    if (
        $userId <= 0 || $publicationId <= 0
        || preg_match('/^[a-f0-9]{64}$/', $token) !== 1
        || !is_finite($position) || $position < 0 || $position > 86400
        || $sequence <= 0
    ) {
        throw new InvalidArgumentException('Estado de reprodução inválido.');
    }
    $now = $now > 0 ? $now : time();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $sessionQuery = $pdo->prepare(
            'SELECT token_hash, last_position, heartbeat_at, is_playing, client_sequence
             FROM video_playback_sessions
             WHERE user_id = :user_id AND publication_id = :publication_id LIMIT 1' . $lockClause
        );
        $sessionQuery->execute(['user_id' => $userId, 'publication_id' => $publicationId]);
        $session = $sessionQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($session) || !hash_equals((string) $session['token_hash'], hash('sha256', $token))) {
            throw new DomainException('Sessão de reprodução expirada. Reabra a aula para continuar.');
        }
        if ($sequence <= (int) $session['client_sequence']) {
            $currentProgress = $pdo->prepare(
                'SELECT played_seconds, last_position FROM video_learning_progress
                 WHERE user_id = :user_id AND publication_id = :publication_id LIMIT 1'
            );
            $currentProgress->execute(['user_id' => $userId, 'publication_id' => $publicationId]);
            $current = $currentProgress->fetch(PDO::FETCH_ASSOC) ?: ['played_seconds' => 0, 'last_position' => 0];
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'played_seconds' => (float) $current['played_seconds'],
                'last_position' => (float) $current['last_position'],
                'new_ad_intervals' => [],
                'ad_provider' => video_ad_provider_status(),
            ];
        }

        $previousPosition = (float) $session['last_position'];
        $elapsed = max(0, min(VIDEO_HEARTBEAT_MAX_GAP_SECONDS, $now - (int) $session['heartbeat_at']));
        $positionDelta = $position - $previousPosition;
        $countedSeconds = 0.0;
        $minimumPositionDelta = max(0.5, $elapsed * VIDEO_MIN_POSITION_RATIO);
        if (
            (int) $session['is_playing'] === 1
            && $positionDelta >= $minimumPositionDelta
            && $elapsed > 0
        ) {
            $maximumPositionDelta = ($elapsed * VIDEO_MAX_PLAYBACK_RATE) + VIDEO_POSITION_TOLERANCE_SECONDS;
            if ($positionDelta <= $maximumPositionDelta) {
                $countedSeconds = (float) $elapsed;
            }
        }

        $progressQuery = $pdo->prepare(
            'SELECT played_seconds FROM video_learning_progress
             WHERE user_id = :user_id AND publication_id = :publication_id LIMIT 1'
        );
        $progressQuery->execute(['user_id' => $userId, 'publication_id' => $publicationId]);
        $previousPlayed = (float) ($progressQuery->fetchColumn() ?: 0);
        $totalPlayed = $previousPlayed + $countedSeconds;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $progressSql = $driver === 'sqlite'
            ? 'INSERT INTO video_learning_progress (user_id, publication_id, played_seconds, last_position)
               VALUES (:user_id, :publication_id, :played_seconds, :last_position)
               ON CONFLICT(user_id, publication_id) DO UPDATE SET
                 played_seconds = excluded.played_seconds, last_position = excluded.last_position'
            : 'INSERT INTO video_learning_progress (user_id, publication_id, played_seconds, last_position)
               VALUES (:user_id, :publication_id, :played_seconds, :last_position)
               ON DUPLICATE KEY UPDATE played_seconds = VALUES(played_seconds), last_position = VALUES(last_position)';
        $pdo->prepare($progressSql)->execute([
            'user_id' => $userId,
            'publication_id' => $publicationId,
            'played_seconds' => $totalPlayed,
            'last_position' => $position,
        ]);

        $sessionUpdate = $pdo->prepare(
            'UPDATE video_playback_sessions
             SET last_position = :last_position, heartbeat_at = :heartbeat_at,
                 is_playing = :is_playing, client_sequence = :client_sequence
             WHERE user_id = :user_id AND publication_id = :publication_id'
        );
        $sessionUpdate->execute([
            'last_position' => $position,
            'heartbeat_at' => $now,
            'is_playing' => $isPlaying ? 1 : 0,
            'client_sequence' => $sequence,
            'user_id' => $userId,
            'publication_id' => $publicationId,
        ]);

        $newIntervals = [];
        if ($requiresAds && $countedSeconds > 0) {
            $firstInterval = (int) floor($previousPlayed / VIDEO_AD_INTERVAL_SECONDS) + 1;
            $lastInterval = (int) floor($totalPlayed / VIDEO_AD_INTERVAL_SECONDS);
            $insertSql = $driver === 'sqlite'
                ? 'INSERT OR IGNORE INTO video_ad_intervals (user_id, publication_id, interval_number, status)
                   VALUES (:user_id, :publication_id, :interval_number, "not_configured")'
                : 'INSERT IGNORE INTO video_ad_intervals (user_id, publication_id, interval_number, status)
                   VALUES (:user_id, :publication_id, :interval_number, "not_configured")';
            $insert = $pdo->prepare($insertSql);
            for ($interval = $firstInterval; $interval <= $lastInterval; $interval++) {
                $insert->execute([
                    'user_id' => $userId,
                    'publication_id' => $publicationId,
                    'interval_number' => $interval,
                ]);
                if ($insert->rowCount() === 1) {
                    $newIntervals[] = $interval;
                }
            }
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
        return [
            'played_seconds' => $totalPlayed,
            'last_position' => $position,
            'new_ad_intervals' => $newIntervals,
            'ad_provider' => video_ad_provider_status(),
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
