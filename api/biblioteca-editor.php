<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/digital_library.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
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

    $institutionId = null;
    if (isset($_SESSION['instituicao_usuario_id'])) {
        require_institution_auth();
        $userId = (int) $_SESSION['instituicao_usuario_id'];
        if (($_SESSION['instituicao_validada'] ?? false) !== true || ($_SESSION['instituicao_assinatura_ativa'] ?? false) !== true) {
            throw new PermissionDeniedException('A instituição precisa estar validada e com assinatura ativa para cadastrar materiais.');
        }
        $institutionQuery = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
        $institutionQuery->execute(['user_id' => $userId]);
        $institutionId = (int) $institutionQuery->fetchColumn();
        if ($institutionId <= 0) {
            throw new PermissionDeniedException('A instituição da conta autenticada não foi encontrada.');
        }
        $permissions = resolve_account_permissions($pdo, $userId);
    } else {
        require_auth();
        $userId = (int) $_SESSION['usuario_id'];
        $permissions = resolve_account_permissions($pdo, $userId);
    }

    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['save_draft', 'submit_review'], true)) {
        throw new InvalidArgumentException('Ação de edição inválida.');
    }
    $submitting = $action === 'submit_review';
    if (
        $submitting
        && $institutionId === null
        && !$permissions['is_admin']
        && $permissions['profile'] === 'professor'
        && !$permissions['approved']
    ) {
        throw new PermissionDeniedException('O perfil de professor precisa ser aprovado antes do envio para revisão.');
    }
    $upload = $_FILES['arquivo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $save = static fn (): int => save_library_submission(
        $pdo,
        $userId,
        $institutionId,
        $_POST,
        $upload,
        $submitting
    );
    if ($submitting && $permissions['profile'] === 'professor' && !$permissions['is_admin']) {
        $materialId = with_teacher_monthly_quota($pdo, $userId, 'material', true, $save);
    } else {
        $materialId = $save();
    }

    echo json_encode([
        'ok' => true,
        'id' => $materialId,
        'status' => $submitting ? 'pending_review' : 'draft',
        'message' => $submitting
            ? 'Material enviado para revisão. Ele só ficará disponível após aprovação.'
            : 'Rascunho salvo.',
        'html' => library_sanitize_rich_text((string) ($_POST['texto_html'] ?? '')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PermissionDeniedException | DomainException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][library_editor][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o material agora.']);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][library_editor][storage] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'O armazenamento privado está indisponível. Verifique a pasta de arquivos e as extensões de validação configuradas no servidor.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][library_editor] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o material agora.']);
}
