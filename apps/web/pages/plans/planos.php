<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/user_preferences.php';
require_once SA_PROJECT_ROOT . '/includes/permissions.php';
require_once SA_PROJECT_ROOT . '/includes/subscriptions.php';
require_once SA_PROJECT_ROOT . '/conexao.php';

$account = require_settings_account();
$userId = $account['user_id'];
$isInstitutionAccount = $account['institution_account'];
$isPlatformAdmin = !$isInstitutionAccount && is_admin_user();
$flash = $_SESSION['plans_flash'] ?? null;
unset($_SESSION['plans_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    if (($_POST['action'] ?? null) !== 'cancel_subscription') {
        http_response_code(400);
        exit('Ação de assinatura inválida.');
    }
    $subscriptionId = filter_var($_POST['subscription_id'] ?? null, FILTER_VALIDATE_INT);
    if ($subscriptionId === false || $subscriptionId <= 0) {
        http_response_code(400);
        exit('Assinatura inválida.');
    }
    try {
        cancel_account_subscription($pdo, $userId, $isInstitutionAccount, $subscriptionId);
        $_SESSION['plans_flash'] = [
            'type' => 'success',
            'message' => 'Assinatura cancelada. O acesso Premium foi removido imediatamente.',
        ];
    } catch (DomainException | InvalidArgumentException $error) {
        $_SESSION['plans_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    } catch (Throwable $error) {
        error_log('[S.A Education][subscription_cancel] ' . $error->getMessage());
        $_SESSION['plans_flash'] = [
            'type' => 'error',
            'message' => 'Não foi possível cancelar a assinatura agora.',
        ];
    }
    header('Location: planos.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$permissions = resolve_account_permissions($pdo, $userId);
$tipoConta = match ((string) ($permissions['profile'] ?? '')) {
    'estudante' => 'student',
    'professor' => 'teacher',
    'instituicao' => 'institution',
    default => null,
};
$institutionType = null;
if ($tipoConta === 'institution') {
    $institutionQuery = $pdo->prepare('SELECT tipo, natureza FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
    $institutionQuery->execute(['user_id' => $userId]);
    $institution = $institutionQuery->fetch(PDO::FETCH_ASSOC);
    if (is_array($institution)) {
        $institutionType = resolve_institution_plan_type(
            (string) $institution['tipo'],
            (string) $institution['natureza']
        );
    }
}

$planoAtual = $isPlatformAdmin ? 'administrator' : (string) ($permissions['plan_code'] ?? '');
$planQuery = 'SELECT code, display_name, account_type, tier, institution_type, monthly_price,
                     low_income_price, requires_income_verification, billing_period, description,
                     monthly_lesson_limit, monthly_material_limit, monthly_question_bank_limit,
                     questions_per_bank_limit, institution_member_limit, institution_class_limit,
                     can_create_courses
              FROM product_plans
              WHERE is_enabled = 1 AND account_type = :account_type';
$planParameters = ['account_type' => $tipoConta];
if ($tipoConta === 'institution') {
    $planQuery .= ' AND institution_type = :institution_type';
    $planParameters['institution_type'] = $institutionType;
}
$planQuery .= ' ORDER BY monthly_price, code';
$planStatement = $pdo->prepare($planQuery);
$planStatement->execute($planParameters);
$planos = $planStatement->fetchAll(PDO::FETCH_ASSOC);
$planoAtualNome = $isPlatformAdmin ? 'Acesso administrativo' : ($planoAtual !== '' ? $planoAtual : 'Não informado');
foreach ($planos as $plan) {
    if ((string) $plan['code'] === $planoAtual) {
        $planoAtualNome = (string) $plan['display_name'];
        break;
    }
}

$benefitsByPlan = [];
if ($planos !== []) {
    $benefitsStatement = $pdo->prepare(
        'SELECT plan_code, label FROM product_plan_benefits
         WHERE plan_code = :plan_code ORDER BY position, id'
    );
    foreach ($planos as $plan) {
        $benefitsStatement->execute(['plan_code' => $plan['code']]);
        $benefitsByPlan[(string) $plan['code']] = array_column(
            $benefitsStatement->fetchAll(PDO::FETCH_ASSOC),
            'label'
        );
    }
}

$subscription = $isPlatformAdmin
    ? null
    : find_account_subscription($pdo, $userId, $isInstitutionAccount);
$subscriptionStatus = is_array($subscription) ? (string) $subscription['status'] : '';
$statusLabels = [
    'pending' => 'Aguardando confirmação',
    'active' => 'Ativa',
    'expired' => 'Expirada',
    'canceled' => 'Cancelada',
    'suspended' => 'Suspensa',
];
$periodLabels = ['monthly' => '/ mês', 'annual' => '/ ano', 'one_time' => ' pagamento único'];
$institutionLabels = [
    'public_school' => 'Escola pública',
    'public_university' => 'Universidade pública',
    'private_school' => 'Escola particular',
    'private_university' => 'Universidade particular',
];
$referenceShell = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planos | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/propaganda.css">
</head>
<body class="reference-shell">
  <div class="app">
    <?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>

    <main class="main">
      <section class="plans-page" aria-labelledby="plans-title">
        <header class="plans-heading">
          <p class="plans-eyebrow">S.A Education</p>
          <h1 id="plans-title">Planos disponíveis</h1>
          <p>Consulte os planos, benefícios e limites cadastrados para sua conta.</p>
          <?php if ($isPlatformAdmin): ?>
            <p class="plans-state" role="status">Sua conta administradora tem acesso administrativo próprio, sem depender de assinatura Premium.</p>
          <?php elseif ($planoAtual !== ''): ?>
            <p class="plans-state" role="status">Plano atual: <?= esc($planoAtualNome) ?>.</p>
          <?php endif; ?>
          <p class="plans-state">Não há provedor de pagamentos configurado. Esta página não inicia cobranças nem ativa planos pagos.</p>
          <?php if (is_array($flash) && isset($flash['message'])): ?>
            <p class="plans-state <?= ($flash['type'] ?? '') === 'error' ? 'is-error' : 'is-success' ?>"
               role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
              <?= esc((string) $flash['message']) ?>
            </p>
          <?php endif; ?>
          <?php if (is_array($subscription)): ?>
            <p class="plans-state">Estado da assinatura: <?= esc($statusLabels[$subscriptionStatus] ?? 'Indisponível') ?>.
              <?php if (!empty($subscription['expires_at'])): ?>
                Válida até <?= esc(date('d/m/Y', strtotime((string) $subscription['expires_at']))) ?>.
              <?php endif; ?>
            </p>
          <?php endif; ?>
          <?php if ($subscriptionStatus === 'suspended'): ?>
            <p class="plans-state is-error" role="alert">A assinatura está suspensa e não concede benefícios Premium.</p>
          <?php elseif ($subscriptionStatus === 'canceled'): ?>
            <p class="plans-state" role="status">A assinatura cancelada não concede benefícios Premium.</p>
          <?php endif; ?>
        </header>

        <?php if (!$isPlatformAdmin && is_array($subscription) && in_array($subscriptionStatus, ['active', 'pending'], true)): ?>
          <form class="plan-cancel-form" method="post" action="planos.php"
                onsubmit="return confirm('Cancelar esta assinatura agora? O acesso Premium será removido imediatamente.')">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="cancel_subscription">
            <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
            <button class="btn btn-outline" type="submit">Cancelar assinatura</button>
          </form>
        <?php endif; ?>

        <?php if ($planos === []): ?>
          <p class="plans-state" role="status">Nenhum plano compatível com esta conta está disponível no momento.</p>
        <?php else: ?>
          <div class="plans-grid">
            <?php foreach ($planos as $plano): ?>
              <?php
                $features = $benefitsByPlan[(string) $plano['code']] ?? [];
                if ($plano['monthly_lesson_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['monthly_lesson_limit'] . ' aulas em vídeo por mês';
                }
                if ($plano['monthly_material_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['monthly_material_limit'] . ' materiais por mês';
                }
                if ($plano['monthly_question_bank_limit'] !== null) {
                    $features[] = (int) $plano['monthly_question_bank_limit'] . ' bancos de questões por mês';
                }
                if ($plano['questions_per_bank_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['questions_per_bank_limit'] . ' questões por banco';
                }
                if ($plano['institution_member_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['institution_member_limit'] . ' membros institucionais, incluindo convites pendentes';
                }
                if ($plano['institution_class_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['institution_class_limit'] . ' turmas ativas';
                }
                if ((bool) $plano['can_create_courses']) {
                    $features[] = 'Criação de cursos';
                }
                $isCurrent = !$isPlatformAdmin && $planoAtual === (string) $plano['code'];
                $price = (float) $plano['monthly_price'];
                $priceLabel = (string) $plano['tier'] === 'free'
                    ? 'Gratuito'
                    : ($price === 0.0
                        ? 'Preço não configurado'
                        : 'R$ ' . number_format($price, 2, ',', '.') . ' ' . ($periodLabels[(string) $plano['billing_period']] ?? ''));
              ?>
              <article class="plan-card <?= $isCurrent ? 'is-current' : '' ?>">
                <?php if ($isCurrent): ?>
                  <span class="plan-current-label">Seu plano atual</span>
                <?php endif; ?>
                <h2><?= esc((string) $plano['display_name']) ?></h2>
                <?php if ($plano['institution_type'] !== null): ?>
                  <p><?= esc($institutionLabels[(string) $plano['institution_type']] ?? '') ?></p>
                <?php endif; ?>
                <p class="plan-price">
                  <?= esc($priceLabel) ?>
                </p>
                <?php if ($plano['description'] !== null && trim((string) $plano['description']) !== ''): ?>
                  <p class="plan-description"><?= nl2br(esc((string) $plano['description'])) ?></p>
                <?php endif; ?>
                <?php if ($plano['low_income_price'] !== null): ?>
                  <p class="plan-description">
                    <?php if ((float) $plano['low_income_price'] === 0.0): ?>
                      Preço alternativo de baixa renda não configurado.
                    <?php else: ?>
                      R$ <?= number_format((float) $plano['low_income_price'], 2, ',', '.') ?>
                      <?= match ((string) $plano['billing_period']) {
                          'annual' => 'por ano',
                          'one_time' => 'em pagamento único',
                          default => 'por mês',
                      } ?>
                      <?= (bool) $plano['requires_income_verification'] ? 'após verificação de renda.' : '(preço de baixa renda).' ?>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
                <?php if ($features !== []): ?>
                  <ul class="plan-features">
                    <?php foreach ($features as $feature): ?>
                      <li><?= esc((string) $feature) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (!$isPlatformAdmin && !$isCurrent): ?>
                  <button class="btn btn-outline plan-unavailable" type="button" disabled>
                    Contratação indisponível
                  </button>
                  <p class="plan-availability">Contratação ou alteração disponível quando o pagamento estiver configurado.</p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p class="plans-settings-link"><a href="configuracoes.php#conta">Gerenciar conta e assinatura nas configurações</a></p>
      </section>
    </main>
  </div>
  <?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>
</body>
</html>
