<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
send_private_no_store_headers();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/video_learning.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }
    require_auth();
    verify_csrf($_POST['csrf'] ?? null);
    $userId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId <= 0 || !authenticated_session_is_current($pdo)) {
        invalidate_current_session();
        http_response_code(401);
        echo json_encode(['error' => 'Autenticação necessária.']);
        exit;
    }

    $permissions = refresh_user_permission_session($pdo, (int) $userId);
    $publicationId = filter_var($_POST['publication_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($publicationId === false || $publicationId === null || $publicationId <= 0) {
        throw new InvalidArgumentException('Aula inválida.');
    }
    $lesson = video_get_authorized_lesson(
        $pdo,
        (int) $publicationId,
        (int) $userId,
        $permissions['premium'] || $permissions['is_admin']
    );
    if ($lesson === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Aula não encontrada ou sem permissão.']);
        exit;
    }

    if ($action === 'start') {
        echo json_encode(
            video_learning_start($pdo, (int) $userId, (int) $publicationId),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
    if ($action !== 'heartbeat') {
        throw new InvalidArgumentException('Ação de reprodução inválida.');
    }
    $token = $_POST['session_token'] ?? null;
    $position = filter_var($_POST['position'] ?? null, FILTER_VALIDATE_FLOAT);
    $sequence = filter_var($_POST['sequence'] ?? null, FILTER_VALIDATE_INT);
    $playing = $_POST['is_playing'] ?? null;
    if (
        !is_string($token)
        || $position === false
        || $sequence === false
        || $sequence === null
        || !is_string($playing)
        || !in_array($playing, ['0', '1'], true)
    ) {
        throw new InvalidArgumentException('Estado de reprodução inválido.');
    }

    $requiresAds = video_account_requires_ads($permissions['is_admin'], $permissions['premium']);
    echo json_encode(
        video_learning_heartbeat(
            $pdo,
            (int) $userId,
            (int) $publicationId,
            $token,
            (float) $position,
            $playing === '1',
            $requiresAds,
            (int) $sequence
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (PermissionDeniedException | DomainException $error) {
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $error) {
    error_log('[S.A Education][video_learning][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível registrar a reprodução.']);
} catch (Throwable $error) {
    error_log('[S.A Education][video_learning] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível registrar a reprodução.']);
}
