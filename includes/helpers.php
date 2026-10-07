<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): void {
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('Requisição inválida (CSRF).');
    }
}

function auth_debug_log(string $evento, array $contexto = []): void {
    if (!defined('DEV_MODE') || DEV_MODE !== true) {
        return;
    }

    $payload = [
        'timestamp' => date('c'),
        'evento' => $evento,
        'contexto' => $contexto,
    ];

    error_log('[auth_debug] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

?>