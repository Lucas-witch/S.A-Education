<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user_preferences.php';
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
send_private_no_store_headers();

$account = require_settings_account();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        echo json_encode(
            ['preferences' => load_user_preferences($pdo, $account['user_id'])],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    } catch (PDOException | JsonException $exception) {
        error_log('[S.A Education][settings_read] ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Não foi possível carregar as preferências agora.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$requestBody = file_get_contents('php://input');
try {
    $payload = is_string($requestBody) && strlen($requestBody) <= 8192
        ? json_decode($requestBody, true, 16, JSON_THROW_ON_ERROR)
        : null;
} catch (JsonException) {
    $payload = null;
}

if (!is_array($payload) || !isset($payload['preferences']) || !is_array($payload['preferences'])) {
    http_response_code(400);
    echo json_encode(['error' => 'As preferências enviadas são inválidas.']);
    exit;
}

verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrf'] ?? null);

try {
    $current = load_user_preferences($pdo, $account['user_id']);
    $preferences = normalize_user_preferences($payload['preferences'], $current);
    if ($preferences === null) {
        http_response_code(422);
        echo json_encode(['error' => 'Uma ou mais opções de preferência são inválidas.']);
        exit;
    }

    save_user_preferences($pdo, $account['user_id'], $preferences);
    echo json_encode(
        ['preferences' => $preferences, 'saved' => true],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (PDOException | JsonException $exception) {
    error_log('[S.A Education][settings_save] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar suas preferências agora.']);
}
