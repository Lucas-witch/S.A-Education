<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_auth();
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/permissions.php';
require_admin_permission('admin.audit.read');

$administrativeEvents = $pdo->query(
    'SELECT audit.id, audit.actor_source, audit.action, audit.target_user_id,
            audit.details, audit.created_at, actor.username AS actor_username,
            target.username AS target_username
     FROM platform_admin_audit audit
     LEFT JOIN usuarios actor ON actor.id = audit.actor_user_id
     LEFT JOIN usuarios target ON target.id = audit.target_user_id
     ORDER BY audit.created_at DESC, audit.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
$operations = $pdo->query(
    'SELECT audit.id, audit.actor_user_id, actor.username AS actor_username,
            audit.action, audit.target_type, audit.target_id, audit.details, audit.created_at
     FROM admin_audit_log audit
     INNER JOIN usuarios actor ON actor.id = audit.actor_user_id
     ORDER BY audit.created_at DESC, audit.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Auditoria administrativa | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
  <style>
    .admin-audit { max-width: 1200px; margin: 2rem auto; padding: 1rem; }
    .audit-row { background: #fff; border-radius: .75rem; padding: 1rem; margin: .75rem 0; overflow-wrap: anywhere; }
    .audit-row pre { white-space: pre-wrap; }
  </style>
</head>
<body class="reference-shell">
<?php $referenceShell = true; require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main admin-audit">
  <header class="topbar">
    <div><h1>Registros de auditoria</h1><p>Exibição limitada aos 100 registros mais recentes de cada origem. Senhas e tokens não são incluídos.</p></div>
    <a class="btn" href="admin.php">Painel</a>
  </header>
  <section aria-labelledby="access-audit-title">
    <h2 id="access-audit-title">Atribuições e alterações de funções</h2>
    <?php if ($administrativeEvents === []): ?><p>Nenhum evento registrado.</p><?php endif; ?>
    <?php foreach ($administrativeEvents as $event): ?>
      <article class="audit-row">
        <strong><?= esc((string) $event['action']) ?></strong>
        <p>Origem: <?= esc((string) $event['actor_source']) ?> · Ator: <?= esc((string) ($event['actor_username'] ?? 'Ferramenta CLI')) ?> · Alvo: <?= esc((string) ($event['target_username'] ?? 'indisponível')) ?> · <?= esc((string) $event['created_at']) ?></p>
        <?php if (is_string($event['details']) && $event['details'] !== ''): ?>
          <pre><?= esc((string) $event['details']) ?></pre>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
  <section aria-labelledby="operations-audit-title">
    <h2 id="operations-audit-title">Operações administrativas</h2>
    <?php if ($operations === []): ?><p>Nenhuma operação registrada.</p><?php endif; ?>
    <?php foreach ($operations as $event): ?>
      <article class="audit-row">
        <strong><?= esc((string) $event['action']) ?></strong>
        <p>Ator: <?= esc((string) $event['actor_username']) ?> · Recurso: <?= esc((string) $event['target_type']) ?> #<?= (int) ($event['target_id'] ?? 0) ?> · <?= esc((string) $event['created_at']) ?></p>
        <?php if (is_string($event['details']) && $event['details'] !== ''): ?>
          <pre><?= esc((string) $event['details']) ?></pre>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
</main>
</body>
</html>
