<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/rate_limit.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE auth_rate_limits (
        bucket_key CHAR(64) PRIMARY KEY,
        attempts INTEGER NOT NULL,
        window_started_at TEXT NOT NULL,
        blocked_until TEXT NULL
    )'
);
putenv('APP_SECRET=test-key-for-rate-limiter-unit-test-at-least-32-chars');

if (auth_rate_limit_remaining($pdo, 'login', '127.0.0.1', 2, 900) !== 0) {
    throw new RuntimeException('Um endereço sem tentativas deve permanecer liberado.');
}
auth_rate_limit_record_failure($pdo, 'login', '127.0.0.1', 2, 900);
if (auth_rate_limit_remaining($pdo, 'login', '127.0.0.1', 2, 900) !== 0) {
    throw new RuntimeException('A primeira tentativa não deve bloquear o acesso.');
}
auth_rate_limit_record_failure($pdo, 'login', '127.0.0.1', 2, 900);
if (auth_rate_limit_remaining($pdo, 'login', '127.0.0.1', 2, 900) <= 0) {
    throw new RuntimeException('O limite deve bloquear temporariamente novas tentativas.');
}
auth_rate_limit_clear($pdo, 'login', '127.0.0.1');
if (auth_rate_limit_remaining($pdo, 'login', '127.0.0.1', 2, 900) !== 0) {
    throw new RuntimeException('A limpeza do bucket deve liberar novas tentativas.');
}

echo "OK: bloqueio temporário e liberação de tentativas autenticadas.\n";
