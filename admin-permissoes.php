<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/admin_console.php';
require_once __DIR__ . '/conexao.php';

if (!is_admin_user()) {
    http_response_code(403);
    exit('Acesso exclusivo da administração.');
}

$flash = $_SESSION['admin_permissions_flash'] ?? null;
unset($_SESSION['admin_permissions_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? null;
    $targetId = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
    if (is_string($action) && in_array($action, ['suspend_institution', 'restore_institution'], true)) {
        if ($targetId === false || $targetId <= 0) {
            http_response_code(400);
            exit('Instituição inválida.');
        }
        $reasonInput = $_POST['reason'] ?? '';
        if (!is_string($reasonInput)) {
            http_response_code(400);
            exit('Motivo de suspensão inválido.');
        }
        $reason = trim($reasonInput);
        if ($action === 'suspend_institution' && ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000)) {
            http_response_code(400);
            exit('Informe o motivo da suspensão (até 1.000 caracteres).');
        }
        try {
            $pdo->beginTransaction();
            $institutionQuery = $pdo->prepare(
                'SELECT validation_status FROM instituicoes WHERE id = :id FOR UPDATE'
            );
            $institutionQuery->execute(['id' => $targetId]);
            if ($institutionQuery->fetchColumn() !== 'validated') {
                throw new DomainException('Somente instituições validadas podem ser suspensas ou reativadas.');
            }
            if ($action === 'suspend_institution') {
                $suspend = $pdo->prepare(
                    'INSERT INTO institution_portal_suspensions (institution_id, suspended_by, reason)
                     VALUES (:institution_id, :suspended_by, :reason)
                     ON DUPLICATE KEY UPDATE suspended_by = VALUES(suspended_by),
                         reason = VALUES(reason), suspended_at = CURRENT_TIMESTAMP'
                );
                $suspend->execute([
                    'institution_id' => $targetId,
                    'suspended_by' => (int) $_SESSION['usuario_id'],
                    'reason' => $reason,
                ]);
            } else {
                $restore = $pdo->prepare(
                    'DELETE FROM institution_portal_suspensions WHERE institution_id = :institution_id'
                );
                $restore->execute(['institution_id' => $targetId]);
                if ($restore->rowCount() !== 1) {
                    throw new DomainException('A instituição não está suspensa.');
                }
            }
            record_admin_action(
                $pdo,
                $action,
                'institution',
                $targetId,
                ['suspended' => $action === 'suspend_institution']
            );
            $pdo->commit();
            $_SESSION['admin_permissions_flash'] = [
                'type' => 'success',
                'message' => $action === 'suspend_institution'
                    ? 'Acesso institucional suspenso e ação registrada.'
                    : 'Suspensão removida e ação registrada.',
            ];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof DomainException) {
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
            } else {
                error_log('[S.A Education][admin_institution_suspension] ' . $error->getMessage());
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => 'Não foi possível alterar o acesso institucional.'];
            }
        }
        header('Location: admin-permissoes.php', true, 303);
        exit;
    }
    if (is_string($action) && in_array($action, ['grant_official_editor', 'revoke_official_editor'], true)) {
        if ($targetId === false || $targetId <= 0) {
            http_response_code(400);
            exit('Professor inválido.');
        }
        try {
            admin_set_official_content_editor($pdo, $targetId, $action === 'grant_official_editor');
            $_SESSION['admin_permissions_flash'] = [
                'type' => 'success',
                'message' => $action === 'grant_official_editor'
                    ? 'Permissão de produção oficial concedida e auditada.'
                    : 'Permissão de produção oficial revogada e auditada.',
            ];
        } catch (Throwable $error) {
            if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
            } else {
                error_log('[S.A Education][admin_official_editor] ' . $error->getMessage());
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => 'Não foi possível alterar a permissão do professor.'];
            }
        }
        header('Location: admin-permissoes.php#official-editors', true, 303);
        exit;
    }
    if (is_string($action) && in_array($action, ['review_room_report', 'dismiss_room_report'], true)) {
        $reportId = $targetId;
        $roomIdInput = $_POST['room_id'] ?? null;
        $roomId = is_string($roomIdInput) && ctype_digit($roomIdInput) ? (int) $roomIdInput : 0;
        if ($reportId === false || $reportId <= 0 || $roomId <= 0) {
            http_response_code(400);
            exit('Denúncia inválida.');
        }
        try {
            $pdo->beginTransaction();
            $status = $action === 'review_room_report' ? 'reviewed' : 'dismissed';
            room_review_report(
                $pdo,
                $roomId,
                $reportId,
                $status,
                is_admin_user(),
                (int) $_SESSION['usuario_id']
            );
            record_admin_action($pdo, 'room_report_' . $status, 'room_report', $reportId, [
                'room_id' => $roomId,
            ]);
            $pdo->commit();
            $_SESSION['admin_permissions_flash'] = ['type' => 'success', 'message' => 'Denúncia analisada e registrada.'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
            } else {
                error_log('[S.A Education][admin_room_report] ' . $error->getMessage());
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => 'Não foi possível analisar a denúncia.'];
            }
        }
        header('Location: admin-permissoes.php#reports', true, 303);
        exit;
    }
    if ($action === 'update_plan_limits') {
        $planCode = $_POST['plan_code'] ?? null;
        $priceInput = $_POST['monthly_price'] ?? null;
        $lowIncomePriceInput = $_POST['low_income_price'] ?? '';
        if (
            !is_string($planCode) || preg_match('/^[a-z0-9-]{1,80}$/D', $planCode) !== 1
            || !is_string($priceInput) || preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $priceInput) !== 1
            || !is_string($lowIncomePriceInput)
            || ($lowIncomePriceInput !== '' && preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $lowIncomePriceInput) !== 1)
        ) {
            http_response_code(400);
            exit('Dados do plano inválidos.');
        }
        $limits = [];
        foreach (['monthly_lesson_limit', 'monthly_material_limit', 'monthly_question_bank_limit', 'questions_per_bank_limit'] as $field) {
            $value = $_POST[$field] ?? '';
            if (!is_string($value) || ($value !== '' && (!ctype_digit($value) || (int) $value > 65535))) {
                http_response_code(400);
                exit('Os limites devem ser números entre 0 e 65.535 ou ficar vazios para ilimitado.');
            }
            $limits[$field] = $value === '' ? null : (int) $value;
        }
        $canCreateCourses = $_POST['can_create_courses'] ?? null;
        $enabled = $_POST['is_enabled'] ?? null;
        $requiresIncomeVerification = $_POST['requires_income_verification'] ?? null;
        if (
            !in_array($canCreateCourses, ['0', '1'], true)
            || !in_array($enabled, ['0', '1'], true)
            || !in_array($requiresIncomeVerification, ['0', '1'], true)
        ) {
            http_response_code(400);
            exit('Revise as opções de disponibilidade do plano.');
        }
        try {
            $pdo->beginTransaction();
            $existingPlan = $pdo->prepare(
                'SELECT monthly_price, low_income_price, requires_income_verification,
                        monthly_lesson_limit, monthly_material_limit,
                        monthly_question_bank_limit, questions_per_bank_limit,
                        can_create_courses, is_enabled
                 FROM product_plans WHERE code = :code FOR UPDATE'
            );
            $existingPlan->execute(['code' => $planCode]);
            $beforePlan = $existingPlan->fetch(PDO::FETCH_ASSOC);
            if (!is_array($beforePlan)) {
                throw new DomainException('Plano não encontrado.');
            }
            $update = $pdo->prepare(
                'UPDATE product_plans
                 SET monthly_price = :monthly_price,
                     low_income_price = :low_income_price,
                     requires_income_verification = :requires_income_verification,
                     monthly_lesson_limit = :monthly_lesson_limit,
                     monthly_material_limit = :monthly_material_limit,
                     monthly_question_bank_limit = :monthly_question_bank_limit,
                     questions_per_bank_limit = :questions_per_bank_limit,
                     can_create_courses = :can_create_courses,
                     is_enabled = :is_enabled
                 WHERE code = :code'
            );
            $update->execute([
                'monthly_price' => $priceInput,
                'low_income_price' => $lowIncomePriceInput === '' ? null : $lowIncomePriceInput,
                'requires_income_verification' => (int) $requiresIncomeVerification,
                'monthly_lesson_limit' => $limits['monthly_lesson_limit'],
                'monthly_material_limit' => $limits['monthly_material_limit'],
                'monthly_question_bank_limit' => $limits['monthly_question_bank_limit'],
                'questions_per_bank_limit' => $limits['questions_per_bank_limit'],
                'can_create_courses' => (int) $canCreateCourses,
                'is_enabled' => (int) $enabled,
                'code' => $planCode,
            ]);
            record_admin_action($pdo, 'update_product_plan', 'product_plan', null, [
                'plan_code' => $planCode,
                'before' => $beforePlan,
                'after' => [
                    'monthly_price' => $priceInput,
                    'low_income_price' => $lowIncomePriceInput === '' ? null : $lowIncomePriceInput,
                    'requires_income_verification' => (int) $requiresIncomeVerification,
                    'limits' => $limits,
                    'can_create_courses' => (int) $canCreateCourses,
                    'is_enabled' => (int) $enabled,
                ],
            ]);
            $pdo->commit();
            $_SESSION['admin_permissions_flash'] = ['type' => 'success', 'message' => 'Plano atualizado e alteração registrada.'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof DomainException) {
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
            } else {
                error_log('[S.A Education][admin_plan_update] ' . $error->getMessage());
                $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => 'Não foi possível atualizar o plano.'];
            }
        }
        header('Location: admin-permissoes.php#plans', true, 303);
        exit;
    }
    $actions = [
        'approve_teacher' => ['usuarios', 'professor_status', 'approved'],
        'reject_teacher' => ['usuarios', 'professor_status', 'rejected'],
        'validate_institution' => ['instituicoes', 'validation_status', 'validated'],
        'reject_institution' => ['instituicoes', 'validation_status', 'rejected'],
    ];
    if (!is_string($action) || !isset($actions[$action]) || $targetId === false || $targetId <= 0) {
        http_response_code(400);
        exit('Solicitação administrativa inválida.');
    }

    [$table, $statusColumn, $newStatus] = $actions[$action];
    $idColumn = $table === 'usuarios' ? 'id' : 'id';
    try {
        $pdo->beginTransaction();
        $selectSql = $table === 'usuarios'
            ? 'SELECT perfil, professor_status AS current_status FROM usuarios WHERE id = :id FOR UPDATE'
            : 'SELECT validation_status AS current_status FROM instituicoes WHERE id = :id FOR UPDATE';
        $target = $pdo->prepare($selectSql);
        $target->execute(['id' => $targetId]);
        $record = $target->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($record)
            || ($table === 'usuarios' && $record['perfil'] !== 'professor')
        ) {
            throw new DomainException('Registro não encontrado para esta ação.');
        }
        if ($table === 'usuarios' && $targetId === (int) $_SESSION['usuario_id']) {
            throw new DomainException('A administração não pode aprovar ou rejeitar o próprio perfil.');
        }
        $reason = $_POST['reason'] ?? '';
        if (!is_string($reason) || mb_strlen(trim($reason), 'UTF-8') > 1000) {
            throw new InvalidArgumentException('A justificativa pode conter até 1.000 caracteres.');
        }
        $reason = trim($reason);
        if (in_array($action, ['reject_teacher', 'reject_institution'], true) && $reason === '') {
            throw new InvalidArgumentException('Informe a justificativa para rejeitar a solicitação.');
        }
        if ($table === 'instituicoes') {
            $ownerQuery = $pdo->prepare('SELECT usuario_id FROM instituicoes WHERE id = :id');
            $ownerQuery->execute(['id' => $targetId]);
            if ((int) $ownerQuery->fetchColumn() === (int) $_SESSION['usuario_id']) {
                throw new DomainException('A administração não pode validar a própria instituição.');
            }
        }

        $update = $pdo->prepare(
            "UPDATE {$table} SET {$statusColumn} = :new_status WHERE {$idColumn} = :id"
        );
        $update->execute(['new_status' => $newStatus, 'id' => $targetId]);
        record_admin_action(
            $pdo,
            $action,
            $table === 'usuarios' ? 'teacher' : 'institution',
            $targetId,
            ['from' => $record['current_status'], 'to' => $newStatus, 'reason' => $reason]
        );
        $pdo->commit();
        $_SESSION['admin_permissions_flash'] = ['type' => 'success', 'message' => 'Permissões atualizadas e ação registrada.'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof DomainException) {
            $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
        } else {
            error_log('[S.A Education][admin_permissions] ' . $error->getMessage());
            $_SESSION['admin_permissions_flash'] = ['type' => 'error', 'message' => 'Não foi possível atualizar as permissões agora.'];
        }
    }
    header('Location: admin-permissoes.php', true, 303);
    exit;
}

$teachers = $pdo->query(
    "SELECT id, nome_completo, email, professor_status
     FROM usuarios WHERE perfil = 'professor' AND professor_status IN ('pending','rejected')
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$institutions = $pdo->query(
    "SELECT i.id, i.nome, i.natureza, i.tipo, i.cidade, i.validation_status, u.email
     FROM instituicoes i INNER JOIN usuarios u ON u.id = i.usuario_id
     WHERE i.validation_status IN ('pending','rejected')
     ORDER BY i.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$activeInstitutions = $pdo->query(
    'SELECT i.id, i.nome, i.natureza, i.tipo, i.validation_status,
            suspension.reason AS suspension_reason,
            (SELECT CASE
                WHEN s.status = "expired" OR (s.expires_at IS NOT NULL AND s.expires_at <= CURRENT_TIMESTAMP)
                    THEN "expired"
                WHEN s.status = "active" AND s.payment_confirmed_at IS NOT NULL
                     AND (s.starts_at IS NULL OR s.starts_at <= CURRENT_TIMESTAMP)
                     AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)
                    THEN "active"
                WHEN s.status IN ("pending", "active") THEN "pending"
                ELSE s.status
             END
             FROM subscriptions s
             WHERE s.institution_id = i.id ORDER BY s.created_at DESC LIMIT 1) AS subscription_status
     FROM instituicoes i
     LEFT JOIN institution_portal_suspensions suspension ON suspension.institution_id = i.id
     WHERE i.validation_status = "validated"
     ORDER BY i.nome'
)->fetchAll(PDO::FETCH_ASSOC);
$plans = $pdo->query(
    'SELECT code, account_type, tier, institution_type, monthly_price, low_income_price,
            requires_income_verification,
            monthly_lesson_limit, monthly_material_limit, monthly_question_bank_limit,
            questions_per_bank_limit, can_create_courses, is_enabled
     FROM product_plans ORDER BY account_type, institution_type, tier, code'
)->fetchAll(PDO::FETCH_ASSOC);
$subscriptions = $pdo->query(
    'SELECT s.id, s.user_id, s.institution_id, s.plan_code, s.status,
            s.payment_reference, s.payment_confirmed_at, s.starts_at, s.expires_at, s.created_at
     FROM subscriptions s ORDER BY s.created_at DESC, s.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
$auditLog = $pdo->query(
    'SELECT a.id, a.actor_user_id, u.nome_completo AS actor_name, a.action,
            a.target_type, a.target_id, a.details, a.created_at
     FROM admin_audit_log a INNER JOIN usuarios u ON u.id = a.actor_user_id
     ORDER BY a.created_at DESC, a.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
$officialEditors = $pdo->query(
    'SELECT e.user_id, e.status, e.granted_at, e.revoked_at,
            u.nome_completo, u.email, u.professor_status
     FROM official_content_editors e
     INNER JOIN usuarios u ON u.id = e.user_id
     ORDER BY e.status, u.nome_completo'
)->fetchAll(PDO::FETCH_ASSOC);
$eligibleOfficialEditors = $pdo->query(
    'SELECT u.id, u.nome_completo, u.email
     FROM usuarios u
     LEFT JOIN official_content_editors e ON e.user_id = u.id AND e.status = "active"
     WHERE u.perfil = "professor" AND u.professor_status = "approved"
       AND e.user_id IS NULL
     ORDER BY u.nome_completo LIMIT 500'
)->fetchAll(PDO::FETCH_ASSOC);
$roomReports = $pdo->query(
    'SELECT r.id, r.room_id, r.target_type, r.target_id, r.reason, r.details,
            r.created_at, s.nome AS room_name, u.nome_completo AS reporter_name
     FROM room_reports r
     INNER JOIN salas s ON s.id = r.room_id
     INNER JOIN usuarios u ON u.id = r.reporter_user_id
     WHERE r.status = "pending"
     ORDER BY r.created_at ASC, r.id ASC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
$operationalMetrics = [
    'teachers_pending' => (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE perfil = "professor" AND professor_status = "pending"')->fetchColumn(),
    'institutions_pending' => (int) $pdo->query('SELECT COUNT(*) FROM instituicoes WHERE validation_status = "pending"')->fetchColumn(),
    'subscriptions_active' => (int) $pdo->query(
        'SELECT COUNT(*) FROM subscriptions
         WHERE status = "active" AND payment_confirmed_at IS NOT NULL
           AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
           AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)'
    )->fetchColumn(),
    'reports_pending' => count($roomReports),
];

$referenceShell = true;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permissões administrativas | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
</head>
<body class="reference-shell">
  <div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main">
      <section class="library-wrap" aria-labelledby="admin-permissions-title">
        <header class="topbar">
          <div>
            <h1 id="admin-permissions-title">Aprovações e validações</h1>
            <p>Revise solicitações de professores e valide as instituições cadastradas.</p>
          </div>
        </header>

        <?php if (is_array($flash) && isset($flash['message'])): ?>
          <div class="library-state" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= esc((string) $flash['message']) ?></div>
        <?php endif; ?>

        <section class="library-admin" aria-label="Métricas operacionais">
          <p>Solicitações docentes pendentes: <?= $operationalMetrics['teachers_pending'] ?> · Instituições pendentes: <?= $operationalMetrics['institutions_pending'] ?> · Assinaturas ativas com pagamento confirmado: <?= $operationalMetrics['subscriptions_active'] ?> · Denúncias pendentes: <?= $operationalMetrics['reports_pending'] ?></p>
        </section>
        <nav aria-label="Ferramentas administrativas">
          <a class="btn btn-primary" href="admin-sandbox.php">Ambiente de testes (somente leitura)</a>
          <a class="btn" href="admin-conteudo-oficial.php">Conteúdo oficial</a>
          <a class="btn" href="admin-biblioteca-revisao.php">Revisão da biblioteca</a>
        </nav>
        <p class="library-state">Pendência do sistema: não existe uma entidade de cursos ou fila de revisão acadêmica para publicações/aulas. A biblioteca mantém sua própria revisão; conteúdo oficial publicado nesta área segue a autorização e a autoria da equipe cadastrada.</p>

        <section class="library-admin" aria-labelledby="teachers-title">
          <h2 id="teachers-title">Professores</h2>
          <?php if ($teachers === []): ?>
            <p>Nenhuma solicitação de professor pendente ou rejeitada.</p>
          <?php else: ?>
            <?php foreach ($teachers as $teacher): ?>
              <article class="library-state">
                <h3><?= esc((string) $teacher['nome_completo']) ?></h3>
                <p><?= esc((string) $teacher['email']) ?> · Situação: <?= esc((string) $teacher['professor_status']) ?></p>
                <form method="post" class="library-admin-form">
                  <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                  <input type="hidden" name="target_id" value="<?= (int) $teacher['id'] ?>">
                  <label for="teacher-reason-<?= (int) $teacher['id'] ?>">Justificativa (obrigatória para rejeitar)</label>
                  <input id="teacher-reason-<?= (int) $teacher['id'] ?>" name="reason" maxlength="1000">
                  <button class="btn btn-primary" type="submit" name="action" value="approve_teacher">Aprovar professor</button>
                  <button class="btn" type="submit" name="action" value="reject_teacher" onclick="return confirm('Rejeitar esta solicitação? A justificativa será registrada na auditoria.')">Rejeitar solicitação</button>
                </form>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <section class="library-admin" aria-labelledby="institutions-title">
          <h2 id="institutions-title">Instituições</h2>
          <?php if ($institutions === []): ?>
            <p>Nenhuma instituição pendente ou rejeitada.</p>
          <?php else: ?>
            <?php foreach ($institutions as $institution): ?>
              <article class="library-state">
                <h3><?= esc((string) $institution['nome']) ?></h3>
                <p><?= esc((string) $institution['email']) ?> · <?= esc((string) $institution['cidade']) ?> · <?= esc((string) $institution['natureza']) ?> · Situação: <?= esc((string) $institution['validation_status']) ?></p>
                <form method="post" class="library-admin-form">
                  <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                  <input type="hidden" name="target_id" value="<?= (int) $institution['id'] ?>">
                  <label for="institution-reason-<?= (int) $institution['id'] ?>">Justificativa (obrigatória para rejeitar)</label>
                  <input id="institution-reason-<?= (int) $institution['id'] ?>" name="reason" maxlength="1000">
                  <button class="btn btn-primary" type="submit" name="action" value="validate_institution">Validar instituição</button>
                  <button class="btn" type="submit" name="action" value="reject_institution" onclick="return confirm('Rejeitar esta instituição? A justificativa será registrada na auditoria.')">Rejeitar instituição</button>
                </form>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
        <section class="library-admin" aria-labelledby="active-institutions-title">
          <h2 id="active-institutions-title">Acesso institucional</h2>
          <?php if ($activeInstitutions === []): ?>
            <p>Nenhuma instituição validada.</p>
          <?php else: ?>
            <?php foreach ($activeInstitutions as $institution): ?>
              <article class="library-state">
                <h3><?= esc((string) $institution['nome']) ?></h3>
                <p>
                  <?= esc((string) $institution['natureza']) ?> · <?= esc((string) $institution['tipo']) ?>
                  · Assinatura: <?= esc(match ((string) ($institution['subscription_status'] ?? 'none')) {
                      'active' => 'ativa',
                      'expired' => 'expirada',
                      'pending' => 'pendente de confirmação',
                      'canceled' => 'cancelada',
                      default => 'não cadastrada',
                  }) ?>
                  · Acesso: <?= $institution['suspension_reason'] !== null ? 'suspenso' : 'não suspenso' ?>
                </p>
                <?php if ($institution['suspension_reason'] !== null): ?>
                  <p>Motivo registrado: <?= esc((string) $institution['suspension_reason']) ?></p>
                  <form method="post" class="library-admin-form">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="target_id" value="<?= (int) $institution['id'] ?>">
                    <button class="btn btn-primary" type="submit" name="action" value="restore_institution">Reativar acesso</button>
                  </form>
                <?php else: ?>
                  <form method="post" class="library-admin-form">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="target_id" value="<?= (int) $institution['id'] ?>">
                    <label for="suspension-reason-<?= (int) $institution['id'] ?>">Motivo da suspensão</label>
                    <input id="suspension-reason-<?= (int) $institution['id'] ?>" name="reason" maxlength="1000" required>
                    <button class="btn" type="submit" name="action" value="suspend_institution">Suspender acesso</button>
                  </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
        <section class="library-admin" id="reports" aria-labelledby="reports-title">
          <h2 id="reports-title">Denúncias pendentes de salas e conteúdo</h2>
          <?php if ($roomReports === []): ?><p>Nenhuma denúncia pendente.</p><?php endif; ?>
          <?php foreach ($roomReports as $report): ?>
            <article class="library-state">
              <h3><?= esc((string) $report['reason']) ?> · <?= esc((string) $report['target_type']) ?> #<?= (int) $report['target_id'] ?></h3>
              <p>Sala <?= esc((string) $report['room_name']) ?> (#<?= (int) $report['room_id'] ?>) · Denunciado por <?= esc((string) $report['reporter_name']) ?> · <?= esc((string) $report['created_at']) ?></p>
              <?php if (!empty($report['details'])): ?><p><?= esc((string) $report['details']) ?></p><?php endif; ?>
              <form method="post" class="library-admin-form">
                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                <input type="hidden" name="target_id" value="<?= (int) $report['id'] ?>">
                <input type="hidden" name="room_id" value="<?= (int) $report['room_id'] ?>">
                <button class="btn btn-primary" type="submit" name="action" value="review_room_report">Marcar como analisada</button>
                <button class="btn" type="submit" name="action" value="dismiss_room_report" onclick="return confirm('Descartar esta denúncia? A ação será auditada.')">Descartar denúncia</button>
              </form>
            </article>
          <?php endforeach; ?>
        </section>
        <section class="library-admin" id="official-editors" aria-labelledby="official-editors-title">
          <h2 id="official-editors-title">Equipe de conteúdo oficial</h2>
          <p>Esta permissão permite somente produzir e administrar publicações oficiais próprias; não concede acesso administrativo à plataforma. O professor precisa permanecer aprovado.</p>
          <?php foreach ($officialEditors as $editor): ?>
            <article class="library-state">
              <h3><?= esc((string) $editor['nome_completo']) ?></h3>
              <p><?= esc((string) $editor['email']) ?> · Professor <?= esc((string) $editor['professor_status']) ?> · Permissão <?= esc((string) $editor['status']) ?></p>
              <?php if ($editor['status'] === 'active'): ?>
                <form method="post" class="library-admin-form" onsubmit="return confirm('Revogar a permissão de produção de conteúdo oficial deste professor?')">
                  <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                  <input type="hidden" name="target_id" value="<?= (int) $editor['user_id'] ?>">
                  <button class="btn" type="submit" name="action" value="revoke_official_editor">Revogar permissão</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
          <?php if ($eligibleOfficialEditors !== []): ?>
            <form method="post" class="library-admin-form">
              <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
              <label for="official-editor-user">Adicionar professor aprovado</label>
              <select id="official-editor-user" name="target_id" required>
                <option value="">Selecione</option>
                <?php foreach ($eligibleOfficialEditors as $eligibleEditor): ?>
                  <option value="<?= (int) $eligibleEditor['id'] ?>"><?= esc((string) $eligibleEditor['nome_completo']) ?> · <?= esc((string) $eligibleEditor['email']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary" type="submit" name="action" value="grant_official_editor">Conceder permissão de conteúdo</button>
            </form>
          <?php else: ?>
            <p>Não há professores aprovados elegíveis para adicionar.</p>
          <?php endif; ?>
        </section>
        <section class="library-admin" id="plans" aria-labelledby="plans-title">
          <h2 id="plans-title">Catálogo de planos e limites</h2>
          <p>Alterações afetam as verificações do catálogo. Campos de limite vazios significam ilimitado; não confirme pagamentos nem crie assinaturas nesta tela. Tipo de conta e categoria institucional são metadados fixos do plano.</p>
          <?php foreach ($plans as $plan): ?>
            <details class="library-state">
              <summary><?= esc((string) $plan['code']) ?> · <?= esc((string) $plan['account_type']) ?> · <?= esc((string) $plan['tier']) ?> · R$ <?= esc(number_format((float) $plan['monthly_price'], 2, ',', '.')) ?><?= (int) $plan['is_enabled'] === 1 ? '' : ' · desativado' ?></summary>
              <form method="post" class="library-admin-form" onsubmit="return confirm('Salvar alterações neste plano? Isso altera limites e disponibilidade efetivos.')">
                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_plan_limits">
                <input type="hidden" name="plan_code" value="<?= esc((string) $plan['code']) ?>">
                <label>Preço mensal (R$)
                  <input name="monthly_price" type="number" min="0" max="99999999.99" step="0.01" value="<?= esc((string) $plan['monthly_price']) ?>" required>
                </label>
                <label>Preço para baixa renda (R$; vazio se não aplicável)
                  <input name="low_income_price" type="number" min="0" max="99999999.99" step="0.01" value="<?= $plan['low_income_price'] === null ? '' : esc((string) $plan['low_income_price']) ?>">
                </label>
                <label>Exigir verificação de renda
                  <select name="requires_income_verification">
                    <option value="0" <?= (int) $plan['requires_income_verification'] === 0 ? 'selected' : '' ?>>Não</option>
                    <option value="1" <?= (int) $plan['requires_income_verification'] === 1 ? 'selected' : '' ?>>Sim</option>
                  </select>
                </label>
                <?php foreach ([
                    'monthly_lesson_limit' => 'Videoaulas por mês',
                    'monthly_material_limit' => 'Materiais por mês',
                    'monthly_question_bank_limit' => 'Bancos de questões por mês',
                    'questions_per_bank_limit' => 'Questões por banco',
                ] as $field => $label): ?>
                  <label><?= esc($label) ?>
                    <input name="<?= esc($field) ?>" type="number" min="0" max="65535" value="<?= $plan[$field] === null ? '' : (int) $plan[$field] ?>">
                  </label>
                <?php endforeach; ?>
                <label>Criar cursos
                  <select name="can_create_courses">
                    <option value="0" <?= (int) $plan['can_create_courses'] === 0 ? 'selected' : '' ?>>Não</option>
                    <option value="1" <?= (int) $plan['can_create_courses'] === 1 ? 'selected' : '' ?>>Sim</option>
                  </select>
                </label>
                <label>Disponibilidade
                  <select name="is_enabled">
                    <option value="1" <?= (int) $plan['is_enabled'] === 1 ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= (int) $plan['is_enabled'] === 0 ? 'selected' : '' ?>>Desativado</option>
                  </select>
                </label>
                <button class="btn btn-primary" type="submit">Salvar plano</button>
              </form>
            </details>
          <?php endforeach; ?>
        </section>
        <section class="library-admin" aria-labelledby="subscriptions-title">
          <h2 id="subscriptions-title">Assinaturas e estado financeiro</h2>
          <p>Estados e referências abaixo são somente consulta; a integração financeira é a origem dos pagamentos. Nenhuma ação manual ativa Premium.</p>
          <?php if ($subscriptions === []): ?><p>Nenhuma assinatura registrada.</p><?php endif; ?>
          <?php foreach ($subscriptions as $subscription): ?>
            <article class="library-state">
              <p>Assinatura #<?= (int) $subscription['id'] ?> · Plano <?= esc((string) $subscription['plan_code']) ?> · Estado <?= esc((string) $subscription['status']) ?></p>
              <p>Conta <?= $subscription['institution_id'] !== null ? 'institucional #' . (int) $subscription['institution_id'] : 'usuário #' . (int) ($subscription['user_id'] ?? 0) ?> · Pagamento confirmado: <?= esc((string) ($subscription['payment_confirmed_at'] ?? 'não confirmado')) ?></p>
              <?php if (!empty($subscription['payment_reference'])): ?><p>Referência externa: <?= esc((string) $subscription['payment_reference']) ?></p><?php endif; ?>
              <p>Vigência: <?= esc((string) ($subscription['starts_at'] ?? 'sem início registrado')) ?> a <?= esc((string) ($subscription['expires_at'] ?? 'sem expiração registrada')) ?></p>
            </article>
          <?php endforeach; ?>
        </section>
        <section class="library-admin" aria-labelledby="audit-title">
          <h2 id="audit-title">Auditoria administrativa recente</h2>
          <?php if ($auditLog === []): ?><p>Nenhuma ação administrativa registrada.</p><?php endif; ?>
          <?php foreach ($auditLog as $entry): ?>
            <article class="library-state">
              <p><?= esc((string) $entry['created_at']) ?> · <?= esc((string) $entry['actor_name']) ?> · <?= esc((string) $entry['action']) ?> · <?= esc((string) $entry['target_type']) ?> #<?= (int) ($entry['target_id'] ?? 0) ?></p>
              <?php if (!empty($entry['details'])): ?><pre><?= esc((string) $entry['details']) ?></pre><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </section>
        <p>Esta tela não confirma pagamentos nem ativa assinaturas Premium.</p>
      </section>
    </main>
  </div>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
