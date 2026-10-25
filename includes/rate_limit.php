<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function rate_limit_secret(): string {
    $secret = getenv('APP_SECRET');
    if (is_string($secret) && strlen($secret) >= 32) {
        return $secret;
    }

    if (app_environment() === 'production') {
        throw new RuntimeException('APP_SECRET deve ter pelo menos 32 caracteres em produção.');
    }

    return hash('sha256', __DIR__ . '|' . php_uname('n') . '|' . PHP_VERSION);
}

function rate_limit_bucket_key(string $scope, string $subject): string {
    return hash_hmac('sha256', $scope . "\0" . $subject, rate_limit_secret());
}

function auth_rate_limit_remaining(
    PDO $pdo,
    string $scope,
    string $subject,
    int $limit,
    int $windowSeconds
): int {
    $key = rate_limit_bucket_key($scope, $subject);
    $stmt = $pdo->prepare(
        'SELECT attempts, window_started_at, blocked_until
         FROM auth_rate_limits
         WHERE bucket_key = :bucket_key
         LIMIT 1'
    );
    $stmt->execute(['bucket_key' => $key]);
    $bucket = $stmt->fetch();

    if (!$bucket) {
        return 0;
    }

    $now = time();
    $blockedUntil = !empty($bucket['blocked_until']) ? strtotime((string) $bucket['blocked_until'] . ' UTC') : false;
    if ($blockedUntil !== false && $blockedUntil > $now) {
        return $blockedUntil - $now;
    }

    $windowStarted = strtotime((string) $bucket['window_started_at'] . ' UTC');
    if ($windowStarted === false || $windowStarted + $windowSeconds <= $now) {
        $delete = $pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_key = :bucket_key');
        $delete->execute(['bucket_key' => $key]);
        return 0;
    }

    return (int) $bucket['attempts'] >= $limit ? $windowStarted + $windowSeconds - $now : 0;
}

function auth_rate_limit_record_failure(
    PDO $pdo,
    string $scope,
    string $subject,
    int $limit,
    int $windowSeconds
): void {
    if (
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        && random_int(1, 100) === 1
    ) {
        $pdo->exec(
            'DELETE FROM auth_rate_limits
             WHERE window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
               AND (blocked_until IS NULL OR blocked_until < UTC_TIMESTAMP())'
        );
    }

    $key = rate_limit_bucket_key($scope, $subject);
    $now = time();
    $stmt = $pdo->prepare(
        'SELECT attempts, window_started_at
         FROM auth_rate_limits
         WHERE bucket_key = :bucket_key
         LIMIT 1'
    );
    $stmt->execute(['bucket_key' => $key]);
    $bucket = $stmt->fetch();
    $windowStarted = $bucket ? strtotime((string) $bucket['window_started_at'] . ' UTC') : false;

    if (!$bucket || $windowStarted === false || $windowStarted + $windowSeconds <= $now) {
        $write = $pdo->prepare(
            'INSERT INTO auth_rate_limits (bucket_key, attempts, window_started_at, blocked_until)
             VALUES (:bucket_key, 1, :window_started_at, NULL)'
        );
        try {
            $write->execute([
                'bucket_key' => $key,
                'window_started_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
        } catch (PDOException $error) {
            if (!in_array((string) $error->getCode(), ['23000', '19'], true)) {
                throw $error;
            }
            $retry = $pdo->prepare(
                'UPDATE auth_rate_limits
                 SET attempts = 1, window_started_at = :window_started_at, blocked_until = NULL
                 WHERE bucket_key = :bucket_key'
            );
            $retry->execute([
                'bucket_key' => $key,
                'window_started_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
        }
        return;
    }

    $attempts = (int) $bucket['attempts'] + 1;
    $blockedUntil = $attempts >= $limit ? gmdate('Y-m-d H:i:s', $now + $windowSeconds) : null;
    $update = $pdo->prepare(
        'UPDATE auth_rate_limits
         SET attempts = :attempts, blocked_until = :blocked_until
         WHERE bucket_key = :bucket_key'
    );
    $update->execute([
        'attempts' => $attempts,
        'blocked_until' => $blockedUntil,
        'bucket_key' => $key,
    ]);
}

function auth_rate_limit_clear(PDO $pdo, string $scope, string $subject): void {
    $stmt = $pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_key = :bucket_key');
    $stmt->execute(['bucket_key' => rate_limit_bucket_key($scope, $subject)]);
}
