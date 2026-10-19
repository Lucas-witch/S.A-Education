<?php
function session_configure_security(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_HOST']) && str_contains(strtolower((string) $_SERVER['HTTP_HOST']), 'localhost'));

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($secure) {
        ini_set('session.cookie_secure', '1');
    }
}

function send_private_no_store_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Cookie');
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

function verify_csrf($token): void {
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
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