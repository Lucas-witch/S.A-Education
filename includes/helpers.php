<?php
function app_environment(): string {
    if (defined('APP_ENVIRONMENT')) {
        return strtolower((string) APP_ENVIRONMENT);
    }
    $configured = getenv('APP_ENV');
    if ($configured !== false && trim((string) $configured) !== '') {
        return strtolower(trim((string) $configured));
    }

    $httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $hostWithoutPort = preg_replace('/:\d+$/', '', $httpHost) ?: $httpHost;
    return in_array($hostWithoutPort, ['localhost', '127.0.0.1', '::1'], true)
        ? 'development'
        : 'production';
}

function app_base_url(): string {
    $baseUrl = rtrim(trim((string) (getenv('APP_BASE_URL') ?: '')), '/');
    if ($baseUrl === '' && app_environment() !== 'production') {
        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/^(?:localhost|127\.0\.0\.1)(?::\d{1,5})?$/i', $requestHost) !== 1) {
            throw new RuntimeException('APP_BASE_URL deve ser configurada fora do acesso local.');
        }

        $scriptDirectory = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        $pathSegments = array_filter(explode('/', trim($scriptDirectory, '/')), 'strlen');
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            $pathSegments
        ));
        $scheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'
            ? 'https'
            : 'http';
        $baseUrl = $scheme . '://' . $requestHost . ($encodedPath !== '' ? '/' . $encodedPath : '');
    }

    $baseUrlParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
    $allowedSchemes = app_environment() === 'production' ? ['https'] : ['http', 'https'];
    if (
        !is_array($baseUrlParts)
        || !isset($baseUrlParts['scheme'], $baseUrlParts['host'])
        || !in_array(strtolower((string) $baseUrlParts['scheme']), $allowedSchemes, true)
        || isset($baseUrlParts['user'])
        || isset($baseUrlParts['pass'])
        || isset($baseUrlParts['query'])
        || isset($baseUrlParts['fragment'])
        || !filter_var($baseUrl, FILTER_VALIDATE_URL)
    ) {
        throw new RuntimeException('APP_BASE_URL ausente ou inválida para o ambiente.');
    }

    return $baseUrl;
}

function session_configure_security(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $forceSecureCookie = app_environment() === 'production'
        || strtolower((string) (getenv('SESSION_COOKIE_SECURE') ?: 'false')) === 'true';
    $secure = $https || $forceSecureCookie;

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

function subject_icon_for(string $materia): string {
    $normalized = strtr(mb_strtolower(trim($materia), 'UTF-8'), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ]);
    $icons = [
        'biologia' => 'assets/images/icons/materias/dna.png',
        'ciencias' => 'assets/images/icons/materias/dna.png',
        'matematica' => 'assets/images/icons/materias/simbolo-de-constante-matematica-pi.png',
        'historia' => 'assets/images/icons/templo-grego.png',
        'geografia' => 'assets/images/icons/materias/geografia.png',
        'quimica' => 'assets/images/icons/materias/produtos-quimicos.png',
        'fisica' => 'assets/images/icons/materias/newton.png',
        'redacao' => 'assets/images/icons/redacao.png',
        'sociologia' => 'assets/images/icons/materias/abra-o-livro.png',
        'filosofia' => 'assets/images/icons/materias/filosofia.png',
        'portugues' => 'assets/images/icons/materias/linguistica.png',
        'literatura' => 'assets/images/icons/materias/abra-o-livro.png',
        'ingles' => 'assets/images/icons/materias/eng.png',
        'lingua inglesa' => 'assets/images/icons/materias/eng.png',
        'espanhol' => 'assets/images/icons/materias/eng.png',
        'linguas estrangeiras' => 'assets/images/icons/materias/eng.png',
    ];

    foreach ($icons as $subject => $icon) {
        if ($normalized === $subject || str_starts_with($normalized, $subject . ' ')) {
            return $icon;
        }
    }

    return 'assets/images/icons/materias/abra-o-livro.png';
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

function validate_profile_fields(string $name, string $email, string $username): array {
    $errors = [];

    if ($name === '' || mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 150) {
        $errors['nome_completo'] = 'Informe um nome entre 3 e 150 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors['email'] = 'Informe um e-mail válido.';
    }

    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
        $errors['username'] = 'Use de 3 a 40 caracteres no nome de usuário: letras, números, ponto, hífen ou sublinhado.';
    }

    return $errors;
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