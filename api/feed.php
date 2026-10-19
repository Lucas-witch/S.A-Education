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
require_once __DIR__ . '/../includes/feed.php';

$usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($usuarioId === false || $usuarioId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Autenticação necessária.']);
    exit;
}
$usuarioPremium = !empty($_SESSION['usuario_premium_ativo']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        if (!is_string($action) || $action !== 'comments') {
            http_response_code(400);
            echo json_encode(['error' => 'Ação inválida.']);
            exit;
        }
        $publicationId = feed_parse_id($_GET['id'] ?? null);
        echo json_encode(['comments' => feed_list_comments($pdo, $publicationId, $usuarioId, $usuarioPremium)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }

    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? null;
    if (!is_string($action)) {
        throw new InvalidArgumentException('Ação inválida.');
    }

    switch ($action) {
        case 'publish':
            $publicationId = feed_create_publication($pdo, $usuarioId, $_POST);
            echo json_encode(['ok' => true, 'id' => $publicationId], JSON_UNESCAPED_UNICODE);
            break;
        case 'edit':
            $publicationId = feed_parse_id($_POST['id'] ?? null);
            feed_update_publication($pdo, $publicationId, $usuarioId, $usuarioPremium, $_POST);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;
        case 'delete':
            $publicationId = feed_parse_id($_POST['id'] ?? null);
            feed_archive_publication($pdo, $publicationId, $usuarioId);
            echo json_encode(['ok' => true, 'archived' => true], JSON_UNESCAPED_UNICODE);
            break;
        case 'like':
        case 'save':
            $publicationId = feed_parse_id($_POST['id'] ?? null);
            $result = feed_toggle_interaction($pdo, $publicationId, $usuarioId, $usuarioPremium, $action);
            echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
            break;
        case 'comment':
            $publicationId = feed_parse_id($_POST['id'] ?? null);
            $content = $_POST['content'] ?? null;
            if (!is_string($content)) {
                throw new InvalidArgumentException('Comentário inválido.');
            }
            $comment = feed_add_comment($pdo, $publicationId, $usuarioId, $usuarioPremium, $content);
            echo json_encode(['ok' => true, 'comment' => $comment], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'delete_comment':
            $commentId = feed_parse_id($_POST['id'] ?? null);
            feed_delete_comment($pdo, $commentId, $usuarioId, $usuarioPremium);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;
        default:
            throw new InvalidArgumentException('Ação inválida.');
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DomainException $exception) {
    http_response_code(404);
    echo json_encode(['error' => 'Conteúdo indisponível ou sem permissão.'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    error_log('[S.A Education][api/feed] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.']);
} catch (Throwable $exception) {
    error_log('[S.A Education][api/feed] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.']);
}