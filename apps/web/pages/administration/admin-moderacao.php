<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_auth();
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/permissions.php';
require_once SA_PROJECT_ROOT . '/includes/feed.php';
require_once SA_PROJECT_ROOT . '/includes/room_actions.php';

$actorId = (int) $_SESSION['usuario_id'];
$canReviewReports = admin_user_can($pdo, $actorId, 'admin.reports.manage');
$canModerateFeed = admin_user_can($pdo, $actorId, 'admin.feed.moderate');
if (!$canReviewReports && !$canModerateFeed) {
    http_response_code(403);
    exit('Você não possui permissão para moderar denúncias.');
}

$flash = $_SESSION['admin_moderation_flash'] ?? null;
unset($_SESSION['admin_moderation_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    try {
        $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? null;
        $reason = $_POST['decision_reason'] ?? null;
        if ($reportId === false || $reportId <= 0 || !is_string($action) || !is_string($reason)) {
            throw new InvalidArgumentException('Dados da decisão inválidos.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('Informe o motivo da decisão (até 1.000 caracteres).');
        }
        if (!in_array($action, ['reviewed', 'dismissed', 'hide_publication'], true)) {
            throw new InvalidArgumentException('Decisão de moderação inválida.');
        }
        if ($action === 'hide_publication') {
            require_admin_permission('admin.feed.moderate');
        } else {
            require_admin_permission('admin.reports.manage');
        }

        $pdo->beginTransaction();
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $reportQuery = $pdo->prepare(
            'SELECT id, room_id, target_type, target_id, status
             FROM room_reports WHERE id = :id LIMIT 1' . $lock
        );
        $reportQuery->execute(['id' => $reportId]);
        $report = $reportQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($report) || (string) $report['status'] !== 'pending') {
            throw new DomainException('A denúncia já foi analisada ou não existe.');
        }

        if ($action === 'hide_publication') {
            if ((string) $report['target_type'] !== 'publication') {
                throw new DomainException('Somente uma publicação denunciada pode ser ocultada por esta ação.');
            }
            $targetQuery = $pdo->prepare(
                'SELECT id FROM publicacoes
                 WHERE id = :publication_id AND sala_id = :room_id
                   AND visibilidade = "sala" AND status = "publicado"
                 LIMIT 1' . $lock
            );
            $targetQuery->execute([
                'publication_id' => (int) $report['target_id'],
                'room_id' => (int) $report['room_id'],
            ]);
            if (!$targetQuery->fetchColumn()) {
                throw new DomainException('A publicação denunciada não está mais disponível nesta sala.');
            }
            feed_archive_publication($pdo, (int) $report['target_id'], $actorId, true);
            room_review_report(
                $pdo,
                (int) $report['room_id'],
                $reportId,
                'reviewed',
                admin_user_can($pdo, $actorId, 'admin.reports.manage'),
                $actorId
            );
        } else {
            room_review_report(
                $pdo,
                (int) $report['room_id'],
                $reportId,
                $action,
                admin_user_can($pdo, $actorId, 'admin.reports.manage'),
                $actorId
            );
        }
        record_admin_action($pdo, 'moderation_' . $action, 'room_report', $reportId, [
            'target_type' => (string) $report['target_type'],
            'target_id' => (int) $report['target_id'],
            'reason' => $reason,
        ]);
        $pdo->commit();
        $_SESSION['admin_moderation_flash'] = ['type' => 'success', 'message' => 'Decisão registrada e auditada.'];
    } catch (DomainException | InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_moderation_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[S.A Education][admin_moderation] ' . $error->getMessage());
        $_SESSION['admin_moderation_flash'] = ['type' => 'error', 'message' => 'Não foi possível registrar a decisão.'];
    }
    header('Location: admin-moderacao.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$reports = $pdo->query(
    'SELECT report.id, report.room_id, report.target_type, report.target_id,
            report.reason, report.details, report.created_at,
            room.nome AS room_name, reporter.username AS reporter_username,
            publication.titulo AS publication_title,
            comment.conteudo AS comment_content,
            comment_publication.titulo AS comment_publication_title
     FROM room_reports report
     INNER JOIN salas room ON room.id = report.room_id
     INNER JOIN usuarios reporter ON reporter.id = report.reporter_user_id
     LEFT JOIN publicacoes publication
        ON report.target_type = "publication" AND publication.id = report.target_id
           AND publication.sala_id = report.room_id AND publication.visibilidade = "sala"
     LEFT JOIN publicacao_comentarios comment
        ON report.target_type = "comment" AND comment.id = report.target_id
     LEFT JOIN publicacoes comment_publication
        ON comment_publication.id = comment.publicacao_id
           AND comment_publication.sala_id = report.room_id
           AND comment_publication.visibilidade = "sala"
     WHERE report.status = "pending"
     ORDER BY report.created_at ASC, report.id ASC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Denúncias e moderação | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
  <style>
    .admin-moderation { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
    .report-card { background: #fff; padding: 1.25rem; border-radius: .75rem; margin: 1rem 0; overflow-wrap: anywhere; }
    .report-card form { display: grid; gap: .75rem; }
    .report-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
  </style>
</head>
<body class="reference-shell">
<?php $referenceShell = true; require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main admin-moderation">
  <header class="topbar">
    <div><h1>Denúncias e moderação</h1><p>Analise o contexto antes da decisão. Cada resultado exige motivo e é registrado na auditoria.</p></div>
    <a class="btn" href="admin.php">Painel</a>
  </header>
  <?php if (is_array($flash) && isset($flash['message'])): ?>
    <p role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= esc((string) $flash['message']) ?></p>
  <?php endif; ?>
  <?php if ($reports === []): ?><p>Nenhuma denúncia pendente.</p><?php endif; ?>
  <?php foreach ($reports as $report): ?>
    <article class="report-card">
      <h2>Denúncia #<?= (int) $report['id'] ?> · <?= esc((string) $report['target_type']) ?></h2>
      <p>Sala: <?= esc((string) $report['room_name']) ?> · Denunciante: @<?= esc((string) $report['reporter_username']) ?> · <?= esc((string) $report['created_at']) ?></p>
      <p>Motivo informado: <?= esc((string) $report['reason']) ?></p>
      <?php if (is_string($report['details']) && $report['details'] !== ''): ?>
        <p>Detalhes: <?= nl2br(esc((string) $report['details'])) ?></p>
      <?php endif; ?>
      <?php if ($report['target_type'] === 'publication'): ?>
        <p>Publicação #<?= (int) $report['target_id'] ?>: <?= esc((string) ($report['publication_title'] ?? 'indisponível ou removida')) ?></p>
      <?php elseif ($report['target_type'] === 'comment'): ?>
        <p>Comentário na publicação “<?= esc((string) ($report['comment_publication_title'] ?? 'indisponível')) ?>”: <?= esc((string) ($report['comment_content'] ?? 'comentário indisponível')) ?></p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
        <label>Motivo da decisão <textarea name="decision_reason" maxlength="1000" required></textarea></label>
        <div class="report-actions">
          <?php if ($canReviewReports): ?>
            <button class="btn btn-primary" name="action" value="reviewed">Marcar como analisada</button>
            <button class="btn btn-outline" name="action" value="dismissed">Descartar denúncia</button>
          <?php endif; ?>
          <?php if ($canModerateFeed && $report['target_type'] === 'publication' && $report['publication_title'] !== null): ?>
            <button class="btn btn-outline" name="action" value="hide_publication">Ocultar publicação e concluir análise</button>
          <?php endif; ?>
        </div>
      </form>
    </article>
  <?php endforeach; ?>
</main>
</body>
</html>
