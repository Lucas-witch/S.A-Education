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
$usuarioPremium = false;

try {
    if (!authenticated_session_is_current($pdo)) {
        invalidate_current_session();
        http_response_code(401);
        echo json_encode(['error' => 'Autenticação necessária.']);
        exit;
    }
    $permissions = refresh_user_permission_session($pdo, (int) $usuarioId);
    $usuarioPremium = usuario_tem_acesso_premium();

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
    if (library_request_body_exceeds_post_limit()) {
        http_response_code(413);
        echo json_encode(['error' => 'O arquivo excede o limite configurado pelo servidor. Tente um arquivo menor.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    verify_csrf($_POST['csrf'] ?? null);
    $upload = $_FILES['media'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!in_array($uploadError, [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK], true)) {
        throw new InvalidArgumentException(library_upload_error_message($uploadError));
    }
    $action = $_POST['action'] ?? null;
    if (!is_string($action)) {
        throw new InvalidArgumentException('Ação inválida.');
    }

    switch ($action) {
        case 'publish':
        case 'save_draft':
            $publish = $action === 'publish';
            feed_normalize_publication_input($_POST, !$publish, (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);
            if (
                $_POST['kind'] === 'bank_question'
                && $permissions['profile'] !== 'professor'
                && !$permissions['is_admin']
            ) {
                throw new PermissionDeniedException('Bancos de questões são exclusivos de professores aprovados.');
            }
            $category = match ($_POST['kind']) {
                'free_lesson' => 'lesson',
                'bank_question' => 'question',
                'material' => 'material',
                default => null,
            };
            $save = static function (?int $bankId) use ($pdo, $usuarioId, $publish, $upload): int {
                if (!$publish && isset($_POST['id'])) {
                    $draftId = feed_parse_id($_POST['id']);
                    feed_update_draft($pdo, $draftId, (int) $usuarioId, $_POST, $upload);
                    return $draftId;
                }
                return feed_create_publication(
                    $pdo,
                    (int) $usuarioId,
                    $_POST,
                    $publish ? 'publicado' : 'rascunho',
                    $bankId,
                    false,
                    $upload
                );
            };
            if ($publish && $permissions['profile'] === 'professor' && !$permissions['approved']) {
                throw new PermissionDeniedException('O perfil de professor ainda aguarda aprovação administrativa.');
            }
            if ($permissions['profile'] === 'professor' && $category !== null) {
                $publicationId = with_teacher_monthly_quota($pdo, (int) $usuarioId, $category, $publish, $save);
            } else {
                $publicationId = $save(null);
            }
            echo json_encode(['ok' => true, 'id' => $publicationId], JSON_UNESCAPED_UNICODE);
            break;
        case 'publish_draft':
            $draftId = feed_parse_id($_POST['id'] ?? null);
            $kind = $_POST['kind'] ?? null;
            if (!is_string($kind) || !in_array($kind, ['material', 'question', 'bank_question', 'post', 'free_lesson'], true)) {
                throw new InvalidArgumentException('Tipo de publicação inválido.');
            }
            $category = match ($kind) {
                'free_lesson' => 'lesson',
                'bank_question' => 'question',
                'material' => 'material',
                default => null,
            };
            if ($permissions['profile'] === 'professor' && !$permissions['approved']) {
                throw new PermissionDeniedException('O perfil de professor ainda aguarda aprovação administrativa.');
            }
            $publishDraft = static function (?int $bankId) use ($pdo, $draftId, $usuarioId, $upload): bool {
                feed_publish_draft($pdo, $draftId, (int) $usuarioId, $_POST, $bankId, $upload);
                return true;
            };
            if ($permissions['profile'] === 'professor' && $category !== null) {
                with_teacher_monthly_quota($pdo, (int) $usuarioId, $category, true, $publishDraft);
            } else {
                $publishDraft(null);
            }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;
        case 'edit':
            if ($permissions['profile'] === 'professor' && !$permissions['approved']) {
                throw new PermissionDeniedException('O perfil de professor ainda aguarda aprovação administrativa.');
            }
            $publicationId = feed_parse_id($_POST['id'] ?? null);
            feed_update_publication($pdo, $publicationId, $usuarioId, $usuarioPremium, $_POST, false, $upload);
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
        case 'report':
            $contentId = feed_parse_id($_POST['id'] ?? null);
            $contentType = $_POST['content_type'] ?? 'publication';
            $reason = $_POST['reason'] ?? '';
            $details = $_POST['details'] ?? '';
            if (!is_string($contentType) || !is_string($reason) || !is_string($details)) {
                throw new InvalidArgumentException('Dados da denúncia inválidos.');
            }
            $created = feed_report_content(
                $pdo,
                $contentId,
                $usuarioId,
                $usuarioPremium,
                $contentType,
                $reason,
                $details
            );
            echo json_encode(['ok' => true, 'created' => $created], JSON_UNESCAPED_UNICODE);
            break;
        default:
            throw new InvalidArgumentException('Ação inválida.');
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PermissionDeniedException $exception) {
    http_response_code(403);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DomainException $exception) {
    http_response_code(404);
    echo json_encode(['error' => 'Conteúdo indisponível ou sem permissão.'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    error_log('[S.A Education][api/feed] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.']);
} catch (RuntimeException $exception) {
    error_log('[S.A Education][api/feed][storage] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'O armazenamento privado está indisponível. Verifique a pasta de arquivos e as extensões de validação configuradas no servidor.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[S.A Education][api/feed] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.']);
}