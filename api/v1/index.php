<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$resources = [
    'biblioteca-editor' => ['file' => 'biblioteca-editor.php', 'auth' => true, 'accounts' => ['user', 'institution'], 'methods' => ['POST']],
    'biblioteca-emprestimos' => ['file' => 'biblioteca-emprestimos.php', 'auth' => true, 'accounts' => ['user', 'institution'], 'methods' => ['GET', 'POST']],
    'biblioteca-leitura' => ['file' => 'biblioteca-leitura.php', 'auth' => true, 'accounts' => ['user'], 'methods' => ['GET', 'POST']],
    'biblioteca-revisao' => ['file' => 'biblioteca-revisao.php', 'auth' => true, 'accounts' => ['user'], 'methods' => ['POST']],
    'configuracoes' => ['file' => 'configuracoes.php', 'auth' => true, 'accounts' => ['user', 'institution'], 'methods' => ['GET', 'POST']],
    'exportar-dados' => ['file' => 'exportar-dados.php', 'auth' => true, 'accounts' => ['user', 'institution'], 'methods' => ['GET']],
    'feed' => ['file' => 'feed.php', 'auth' => true, 'accounts' => ['user'], 'methods' => ['GET', 'POST']],
    'instituicao-portal' => ['file' => 'instituicao-portal.php', 'auth' => true, 'accounts' => ['user', 'institution'], 'methods' => ['GET', 'POST']],
    'pesquisa' => ['file' => 'pesquisa.php', 'auth' => false, 'accounts' => ['user', 'institution'], 'methods' => ['GET']],
    'ranking' => ['file' => 'ranking.php', 'auth' => false, 'accounts' => ['user', 'institution'], 'methods' => ['GET']],
    'salas' => ['file' => 'salas.php', 'auth' => false, 'accounts' => ['user', 'institution'], 'methods' => ['GET']],
    'video-aprendizado' => ['file' => 'video-aprendizado.php', 'auth' => true, 'accounts' => ['user'], 'methods' => ['POST']],
    'usuario' => ['file' => 'usuario.php', 'auth' => true, 'accounts' => ['user'], 'methods' => ['GET']],
];

$resource = $_GET['resource'] ?? null;
if (!is_string($resource) || !isset($resources[$resource])) {
    mobile_api_error(404, 'ENDPOINT_NOT_FOUND', 'Endpoint v1 não encontrado.');
}
$definition = $resources[$resource];
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $definition['methods'], true)) {
    header('Allow: ' . implode(', ', $definition['methods']));
    mobile_api_error(405, 'METHOD_NOT_ALLOWED', 'Método HTTP não permitido para este recurso.');
}
$context = mobile_api_authenticate($pdo, $definition['auth']);
if (is_array($context) && !in_array((string) $context['account_type'], $definition['accounts'], true)) {
    mobile_api_error(403, 'FORBIDDEN', 'Esta conta não tem permissão para acessar o recurso.');
}

unset($_GET['resource']);
if ($resource === 'salas') {
    require_once __DIR__ . '/../../includes/room_search.php';
    try {
        $read = static function (string $key, int $limit): string {
            $value = $_GET[$key] ?? '';
            if (!is_string($value) || mb_strlen($value, 'UTF-8') > $limit) {
                throw new InvalidArgumentException('Parâmetro inválido.');
            }
            return trim($value);
        };
        $pageValue = $_GET['page'] ?? '1';
        $limitValue = $_GET['limit'] ?? '20';
        if (!is_string($pageValue) || !ctype_digit($pageValue) || !is_string($limitValue) || !ctype_digit($limitValue)) {
            throw new InvalidArgumentException('Paginação inválida.');
        }
        $page = max(1, min(100000, (int) $pageValue));
        $limit = max(1, min(50, (int) $limitValue));
        $userId = is_array($context) ? (int) $context['user_id'] : 0;
        $total = 0;
        $rooms = buscar_salas(
            $pdo,
            $userId,
            $read('q', 120),
            $read('assunto', 80),
            $read('materia', 80),
            strtolower($read('acesso', 12)),
            null,
            $limit,
            ($page - 1) * $limit,
            $total
        );
        mobile_api_respond(200, $rooms, null, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ]);
    } catch (InvalidArgumentException $error) {
        mobile_api_error(400, 'INVALID_PARAMETERS', $error->getMessage());
    } catch (PDOException $error) {
        error_log('[S.A Education][mobile_api][rooms] ' . $error->getMessage());
        mobile_api_error(500, 'INTERNAL_ERROR', 'Não foi possível carregar as salas agora.');
    }
}

$bodyType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($bodyType === 'application/json' && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'], true)) {
    $payload = mobile_api_read_json(1048576);
    $_POST = $payload;
}

$path = __DIR__ . '/../' . $definition['file'];
if (!is_file($path)) {
    mobile_api_error(500, 'INTERNAL_ERROR', 'O endpoint solicitado não está disponível.');
}

ob_start();
register_shutdown_function(static function (): void {
    $body = ob_get_clean();
    $status = http_response_code();
    $lastError = error_get_last();
    if (
        is_array($lastError)
        && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)
    ) {
        error_log('[S.A Education][mobile_api] Fatal PHP error');
        $status = 500;
    }
    if ($status >= 300 && $status < 400) {
        header_remove('Location');
        $status = 401;
    }
    if (!is_int($status) || $status < 100) {
        $status = 200;
    }
    http_response_code($status);

    $decoded = null;
    try {
        $decoded = is_string($body) ? json_decode($body, true, 32, JSON_THROW_ON_ERROR) : null;
    } catch (JsonException) {
        $decoded = null;
    }

    if ($status < 400 && !is_array($decoded)) {
        $status = 500;
        http_response_code($status);
    }
    if ($status >= 500 || !is_array($decoded)) {
        $errors = [
            401 => ['AUTHENTICATION_REQUIRED', 'Autenticação necessária.'],
            403 => ['FORBIDDEN', 'A conta não tem permissão para concluir esta operação.'],
            404 => ['NOT_FOUND', 'O recurso solicitado não foi encontrado ou não está disponível.'],
            405 => ['METHOD_NOT_ALLOWED', 'Método HTTP não permitido.'],
            409 => ['CONFLICT', 'A operação não pode ser concluída no estado atual.'],
            413 => ['PAYLOAD_TOO_LARGE', 'A requisição excede o tamanho permitido.'],
            422 => ['VALIDATION_ERROR', 'Os dados enviados são inválidos.'],
        ];
        [$code, $message] = $errors[$status] ?? ['INTERNAL_ERROR', 'Não foi possível concluir a operação.'];
        $envelope = ['data' => null, 'error' => ['code' => $code, 'message' => $message], 'meta' => null];
    } elseif ($status >= 400) {
        $errors = [
            400 => ['INVALID_PARAMETERS', 'Os parâmetros enviados são inválidos.'],
            401 => ['AUTHENTICATION_REQUIRED', 'Autenticação necessária.'],
            403 => ['FORBIDDEN', 'A conta não tem permissão para concluir esta operação.'],
            404 => ['NOT_FOUND', 'O recurso solicitado não foi encontrado ou não está disponível.'],
            405 => ['METHOD_NOT_ALLOWED', 'Método HTTP não permitido.'],
            409 => ['CONFLICT', 'A operação não pode ser concluída no estado atual.'],
            413 => ['PAYLOAD_TOO_LARGE', 'A requisição excede o tamanho permitido.'],
            422 => ['VALIDATION_ERROR', 'Os dados enviados são inválidos.'],
        ];
        [$code, $message] = $errors[$status] ?? ['REQUEST_FAILED', 'Não foi possível concluir a operação.'];
        $envelope = ['data' => null, 'error' => ['code' => $code, 'message' => $message], 'meta' => null];
    } else {
        $envelope = ['data' => $decoded, 'error' => null, 'meta' => null];
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Vary: Authorization');
    header('X-Content-Type-Options: nosniff');
    header_remove('Content-Disposition');
    echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

require $path;
