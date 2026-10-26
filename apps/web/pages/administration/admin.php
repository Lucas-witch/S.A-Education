<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_auth();
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/permissions.php';
require_admin_permission('admin.dashboard.view');

$actorId = (int) $_SESSION['usuario_id'];
$isRoot = is_admin_user();
$metrics = [];
if (admin_user_can($pdo, $actorId, 'admin.library.review')) {
    $metrics['Materiais aguardando revisão'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM biblioteca WHERE revisao_status = "pending_review"'
    )->fetchColumn();
}
if (admin_user_can($pdo, $actorId, 'admin.reports.manage')) {
    $metrics['Denúncias pendentes'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM room_reports WHERE status = "pending"'
    )->fetchColumn();
}
if (admin_user_can($pdo, $actorId, 'admin.users.manage')) {
    $metrics['Contas cadastradas'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
}
if (admin_user_can($pdo, $actorId, 'admin.plans.manage')) {
    $metrics['Assinaturas confirmadas e vigentes'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM subscriptions
         WHERE status = "active" AND payment_confirmed_at IS NOT NULL
           AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
           AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)'
    )->fetchColumn();
}

$links = [];
if (admin_user_can($pdo, $actorId, 'admin.users.manage')) {
    $links[] = ['admin-permissoes.php', 'Usuários, instituições, planos e operações'];
}
if (admin_user_can($pdo, $actorId, 'admin.roles.manage')) {
    $links[] = ['admin-acessos.php', 'Funções e acessos administrativos'];
}
if (admin_user_can($pdo, $actorId, 'admin.audit.read')) {
    $links[] = ['admin-auditoria.php', 'Registros de auditoria'];
}
if (admin_user_can($pdo, $actorId, 'admin.library.review')) {
    $links[] = ['admin-biblioteca-revisao.php', 'Fila de aprovação da biblioteca'];
}
if (admin_user_can($pdo, $actorId, 'admin.content.official')) {
    $links[] = ['admin-conteudo-oficial.php', 'Conteúdo oficial'];
}
if (
    admin_user_can($pdo, $actorId, 'admin.reports.manage')
    || admin_user_can($pdo, $actorId, 'admin.feed.moderate')
) {
    $links[] = ['admin-moderacao.php', 'Denúncias e moderação'];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administração | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
  <style>
    .admin-dashboard { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
    .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
    .admin-card { background: #fff; border-radius: .75rem; padding: 1.25rem; box-shadow: 0 3px 16px #24113b12; }
    .admin-card strong { display: block; font-size: 1.8rem; color: #5526a8; }
    .admin-links { display: grid; gap: .75rem; margin-top: 1.5rem; }
  </style>
</head>
<body class="reference-shell">
<?php $referenceShell = true; require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main admin-dashboard">
  <header class="topbar">
    <div>
      <h1>Administração da plataforma</h1>
      <p><?= $isRoot ? 'Superadministrador' : 'Acesso administrativo por função' ?> · permissões validadas no servidor.</p>
    </div>
  </header>
  <?php if ($metrics !== []): ?>
    <section class="admin-grid" aria-label="Indicadores administrativos">
      <?php foreach ($metrics as $label => $value): ?>
        <article class="admin-card"><span><?= esc($label) ?></span><strong><?= $value ?></strong></article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
  <nav class="admin-links" aria-label="Módulos administrativos">
    <?php foreach ($links as [$href, $label]): ?>
      <a class="admin-card" href="<?= esc($href) ?>"><?= esc($label) ?> <span aria-hidden="true">→</span></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($links === []): ?>
    <p role="status">Sua conta não possui módulos administrativos disponíveis.</p>
  <?php endif; ?>
</main>
</body>
</html>
