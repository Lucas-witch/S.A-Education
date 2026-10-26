<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/permissions.php';

try {
    require_auth();
    require_admin_permission('admin.library.review');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }
    verify_csrf($_POST['csrf'] ?? null);

    $materialId = filter_var($_POST['material_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? null;
    $reason = $_POST['reason'] ?? '';
    if ($materialId === false || $materialId === null || $materialId <= 0) {
        throw new InvalidArgumentException('Material inválido.');
    }
    if (!is_string($decision) || !in_array($decision, ['approve', 'reject', 'archive'], true)) {
        throw new InvalidArgumentException('Decisão de revisão inválida.');
    }
    if (!is_string($reason) || mb_strlen($reason, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('O motivo excede o tamanho permitido.');
    }
    $reason = trim($reason);
    if ($decision !== 'approve' && $reason === '') {
        throw new InvalidArgumentException('Informe o motivo da decisão.');
    }

    $pdo->beginTransaction();
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $materialQuery = $pdo->prepare(
        'SELECT id, direitos_status, direitos_evidencia, direitos_declarados, revisao_status
         FROM biblioteca WHERE id = :id LIMIT 1' . $lock
    );
    $materialQuery->execute(['id' => $materialId]);
    $material = $materialQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($material) || $material['revisao_status'] !== 'pending_review') {
        throw new DomainException('O material não está aguardando revisão.');
    }
    if (
        $decision === 'approve'
        && (
            (int) $material['direitos_declarados'] !== 1
            || $material['direitos_status'] === 'unknown'
            || ($material['direitos_status'] !== 'original' && trim((string) $material['direitos_evidencia']) === '')
        )
    ) {
        throw new DomainException('A declaração e a evidência dos direitos precisam ser verificadas antes da aprovação.');
    }

    $reviewStatus = match ($decision) {
        'approve' => 'approved',
        'reject' => 'rejected',
        'archive' => 'archived',
    };
    $legacyStatus = $decision === 'approve' ? 'ativo' : 'indisponivel';
    $update = $pdo->prepare(
        'UPDATE biblioteca
         SET revisao_status = :review_status, revisao_motivo = :reason, status = :legacy_status
         WHERE id = :id AND revisao_status = "pending_review"'
    );
    $update->execute([
        'review_status' => $reviewStatus,
        'reason' => $reason !== '' ? $reason : null,
        'legacy_status' => $legacyStatus,
        'id' => $materialId,
    ]);
    if ($update->rowCount() !== 1) {
        throw new DomainException('O estado do material foi alterado por outra sessão. Atualize a página.');
    }
    record_admin_action($pdo, 'review_library_material_' . $decision, 'library_material', (int) $materialId, [
        'reason' => $reason,
        'rights_status' => (string) $material['direitos_status'],
    ]);
    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => $reviewStatus], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DomainException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][library_review][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar a revisão agora.']);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][library_review] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar a revisão agora.']);
}
