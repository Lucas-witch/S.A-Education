<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_auth();
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/permissions.php';
require_admin_permission('admin.roles.manage');

$flash = $_SESSION['admin_access_flash'] ?? null;
unset($_SESSION['admin_access_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    try {
        $action = $_POST['action'] ?? null;
        $reason = $_POST['reason'] ?? null;
        if (!is_string($reason)) {
            throw new InvalidArgumentException('Motivo inválido.');
        }
        if ($action === 'assign') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $roleCode = $_POST['role_code'] ?? null;
            if ($targetId === false || $targetId <= 0 || !is_string($roleCode)) {
                throw new InvalidArgumentException('Conta ou função inválida.');
            }
            admin_assign_role($pdo, $targetId, $roleCode, $reason);
            $message = 'Função atribuída e ação auditada.';
        } elseif ($action === 'revoke') {
            $assignmentId = filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT);
            if ($assignmentId === false || $assignmentId <= 0) {
                throw new InvalidArgumentException('Atribuição inválida.');
            }
            admin_revoke_role($pdo, $assignmentId, $reason);
            $message = 'Função revogada e ação auditada.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
        $_SESSION['admin_access_flash'] = ['type' => 'success', 'message' => $message];
    } catch (DomainException | InvalidArgumentException $error) {
        $_SESSION['admin_access_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    } catch (Throwable $error) {
        error_log('[S.A Education][admin_access] ' . $error->getMessage());
        $_SESSION['admin_access_flash'] = ['type' => 'error', 'message' => 'Não foi possível atualizar as funções administrativas.'];
    }
    header('Location: admin-acessos.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$roles = $pdo->query('SELECT id, code, label FROM admin_roles WHERE is_enabled = 1 ORDER BY label')
    ->fetchAll(PDO::FETCH_ASSOC);
if (!is_admin_user()) {
    $roles = array_values(array_filter(
        $roles,
        static fn (array $role): bool => (string) $role['code'] !== 'platform_admin'
    ));
}
$users = $pdo->query(
    'SELECT id, nome_completo, username, perfil
     FROM usuarios WHERE perfil IN ("estudante", "professor")
     ORDER BY nome_completo LIMIT 500'
)->fetchAll(PDO::FETCH_ASSOC);
$assignments = $pdo->query(
    'SELECT assignment.id, assignment.user_id, assignment.created_at,
            user.nome_completo, user.username, admin_role.code AS role_code, admin_role.label AS role_label,
            grantor.username AS assigned_by_username
     FROM platform_admin_assignments assignment
     INNER JOIN usuarios user ON user.id = assignment.user_id
     INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id
     LEFT JOIN usuarios grantor ON grantor.id = assignment.assigned_by
     WHERE assignment.status = "active" AND admin_role.is_enabled = 1
     ORDER BY admin_role.label, user.nome_completo'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Funções administrativas | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
  <style>
    .admin-access { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
    .admin-access form, .admin-assignment { background: #fff; border-radius: .75rem; padding: 1rem; margin: 1rem 0; }
    .admin-access form { display: grid; gap: .8rem; }
    .admin-assignment { display: flex; justify-content: space-between; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .admin-access input, .admin-access select, .admin-access textarea { max-width: 100%; }
  </style>
</head>
<body class="reference-shell">
<?php $referenceShell = true; require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main admin-access">
  <header class="topbar">
    <div><h1>Funções e acessos administrativos</h1><p>Conceda apenas o acesso necessário e informe o motivo. As alterações ficam auditadas.</p></div>
    <a class="btn" href="admin.php">Painel</a>
  </header>
  <?php if (is_array($flash) && isset($flash['message'])): ?>
    <p role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= esc((string) $flash['message']) ?></p>
  <?php endif; ?>
  <p>O ID configurado em <code>APP_ADMIN_USER_ID</code>, se definido no ambiente do servidor, mantém acesso de superadministrador independente das atribuições abaixo.</p>
  <section aria-labelledby="assign-role-title">
    <h2 id="assign-role-title">Conceder função</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
      <input type="hidden" name="action" value="assign">
      <label for="user-id">Conta existente (estudante ou professor)</label>
      <select id="user-id" name="user_id" required>
        <option value="">Selecione uma conta</option>
        <?php foreach ($users as $user): ?>
          <option value="<?= (int) $user['id'] ?>"><?= esc((string) $user['nome_completo']) ?> (@<?= esc((string) $user['username']) ?> · <?= esc((string) $user['perfil']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <label for="role-code">Função</label>
      <select id="role-code" name="role_code" required>
        <?php foreach ($roles as $role): ?>
          <option value="<?= esc((string) $role['code']) ?>"><?= esc((string) $role['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="assign-reason">Motivo (obrigatório; até 500 caracteres)</label>
      <textarea id="assign-reason" name="reason" maxlength="500" required></textarea>
      <button class="btn btn-primary" type="submit">Conceder função</button>
    </form>
  </section>
  <section aria-labelledby="active-roles-title">
    <h2 id="active-roles-title">Atribuições ativas</h2>
    <?php if ($assignments === []): ?><p>Nenhuma atribuição administrativa ativa. A conta definida em APP_ADMIN_USER_ID permanece superadministradora.</p><?php endif; ?>
    <?php foreach ($assignments as $assignment): ?>
      <article class="admin-assignment">
        <div>
          <strong><?= esc((string) $assignment['role_label']) ?></strong>
          <span><?= esc((string) $assignment['nome_completo']) ?> (@<?= esc((string) $assignment['username']) ?>)</span>
          <small>Concedida em <?= esc((string) $assignment['created_at']) ?></small>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
          <label>Motivo da revogação
            <input name="reason" maxlength="500" required>
          </label>
          <button class="btn btn-outline" type="submit">Revogar</button>
        </form>
      </article>
    <?php endforeach; ?>
  </section>
</main>
</body>
</html>
