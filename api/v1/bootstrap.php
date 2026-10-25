<?php
declare(strict_types=1);

if (!defined('SA_API_REQUEST')) {
    define('SA_API_REQUEST', true);
}

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/mobile_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Authorization');
header('X-Content-Type-Options: nosniff');

function mobile_api_respond(int $status, ?array $data = null, ?array $error = null, ?array $meta = null): void {
    http_response_code($status);
    echo json_encode(
        ['data' => $data, 'error' => $error, 'meta' => $meta],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function mobile_api_error(int $status, string $code, string $message): void {
    mobile_api_respond($status, null, ['code' => $code, 'message' => $message]);
}

ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $error): void {
    error_log('[S.A Education][mobile_api] Unhandled ' . get_class($error));
    if (!headers_sent()) {
        mobile_api_error(500, 'INTERNAL_ERROR', 'Não foi possível concluir a operação.');
    }
});

require_once __DIR__ . '/../../conexao.php';

function mobile_api_read_json(int $maximumBytes = 8192): array {
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        mobile_api_error(415, 'UNSUPPORTED_MEDIA_TYPE', 'Envie o conteúdo no formato application/json.');
    }
    $input = fopen('php://input', 'rb');
    if ($input === false) {
        throw new RuntimeException('Não foi possível ler o corpo da requisição.');
    }
    $body = stream_get_contents($input, $maximumBytes + 1);
    fclose($input);
    if (!is_string($body)) {
        throw new RuntimeException('Não foi possível ler o corpo da requisição.');
    }
    if (strlen($body) > $maximumBytes) {
        mobile_api_error(413, 'PAYLOAD_TOO_LARGE', 'A requisição excede o tamanho permitido.');
    }
    try {
        $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        mobile_api_error(400, 'INVALID_JSON', 'O corpo JSON é inválido.');
    }
    if (!is_array($payload)) {
        mobile_api_error(400, 'INVALID_JSON', 'O corpo JSON deve ser um objeto.');
    }
    return $payload;
}

function mobile_api_method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        mobile_api_error(405, 'METHOD_NOT_ALLOWED', 'Método HTTP não permitido.');
    }
}

function mobile_api_request_bearer(): ?string {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($header === '') {
        return null;
    }
    if (preg_match('/^Bearer ([a-f0-9]{64})$/i', $header, $matches) !== 1) {
        mobile_api_error(401, 'AUTHENTICATION_REQUIRED', 'Credencial de acesso inválida.');
    }
    return strtolower($matches[1]);
}

function mobile_api_hydrate_account(PDO $pdo, array $context): void {
    $_SESSION = [];
    $userId = (int) $context['user_id'];
    $profile = (string) $context['perfil'];
    if ($context['account_type'] === 'institution') {
        $organization = $pdo->prepare('SELECT nome FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
        $organization->execute(['user_id' => $userId]);
        $institutionName = $organization->fetchColumn();
        if (!is_string($institutionName) || $institutionName === '') {
            mobile_api_error(401, 'AUTHENTICATION_REQUIRED', 'A credencial de acesso não é mais válida.');
        }
        $_SESSION['instituicao_usuario_id'] = $userId;
        $_SESSION['instituicao_auth_version'] = (int) $context['auth_version'];
        $_SESSION['instituicao_nome'] = $institutionName;
        $_SESSION['instituicao_email'] = (string) $context['email'];
        require_institution_auth();
        return;
    }

    $_SESSION['usuario_id'] = $userId;
    $_SESSION['usuario_auth_version'] = (int) $context['auth_version'];
    $_SESSION['usuario_nome'] = (string) $context['nome_completo'];
    $_SESSION['usuario_email'] = (string) $context['email'];
    $_SESSION['usuario_username'] = (string) $context['username'];
    $_SESSION['usuario_perfil'] = $profile;
    $_SESSION['perfil_imagem'] = (string) ($context['perfil_imagem'] ?: 'assets/images/icons/user-icon.png');
    require_once __DIR__ . '/../../includes/permissions.php';
    refresh_user_permission_session($pdo, $userId);
}

function mobile_api_authenticate(PDO $pdo, bool $required = true): ?array {
    $token = mobile_api_request_bearer();
    if ($token === null) {
        if ($required) {
            mobile_api_error(401, 'AUTHENTICATION_REQUIRED', 'Autenticação necessária.');
        }
        return null;
    }

    $context = mobile_find_access_context($pdo, $token);
    if ($context === null) {
        mobile_api_error(401, 'INVALID_OR_EXPIRED_TOKEN', 'A credencial de acesso expirou ou foi revogada.');
    }
    mobile_api_hydrate_account($pdo, $context);
    define('SA_API_BEARER_AUTHENTICATED', true);
    return $context;
}
