<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/conexao.php';

$referenceShell = true;
$contaAdministradora = is_admin_user();
$tipoConta = $contaAdministradora ? null : match (current_user_perfil()) {
    'estudante' => 'student',
    'professor' => 'teacher',
    default => null,
};
$stmt = $pdo->query(
    'SELECT code, account_type, tier, institution_type, monthly_price, low_income_price,
            requires_income_verification, monthly_lesson_limit, monthly_material_limit,
            monthly_question_bank_limit, questions_per_bank_limit, can_create_courses
     FROM product_plans WHERE is_enabled = 1
     ORDER BY account_type, monthly_price, code'
);
$planos = array_values(array_filter(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    static fn (array $plan): bool => $tipoConta === null || $plan['account_type'] === $tipoConta
));
$planoAtual = $contaAdministradora ? 'administrator' : (string) ($_SESSION['usuario_plano'] ?? '');

$planLabels = [
    'student-free' => 'Estudante gratuito',
    'student-premium' => 'Estudante Premium',
    'student-premium-low-income' => 'Estudante Premium com desconto de renda',
    'teacher-free' => 'Professor gratuito',
    'teacher-premium' => 'Professor Premium',
    'institution-public-school' => 'Escola pública',
    'institution-public-university' => 'Universidade pública',
    'institution-private-school' => 'Escola particular',
    'institution-private-university' => 'Universidade particular',
];
$institutionLabels = [
    'public_school' => 'Escola pública',
    'public_university' => 'Universidade pública',
    'private_school' => 'Escola particular',
    'private_university' => 'Universidade particular',
];
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
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="main">
      <section class="plans-page" aria-labelledby="plans-title">
        <header class="plans-heading">
          <p class="plans-eyebrow">S.A Education</p>
          <h1 id="plans-title">Planos disponíveis</h1>
          <p>Consulte os valores e as regras configurados para cada tipo de conta.</p>
          <?php if ($contaAdministradora): ?>
            <p class="plans-state" role="status">Sua conta administradora possui acesso administrativo sem necessidade de assinatura pessoal.</p>
          <?php elseif ($planoAtual !== ''): ?>
            <p class="plans-state" role="status">Plano aplicado: <?= esc($planLabels[$planoAtual] ?? $planoAtual) ?>.</p>
          <?php endif; ?>
          <p class="plans-state">A assinatura Premium só é ativada após confirmação do pagamento. Esta tela não processa pagamentos.</p>
        </header>

        <?php if ($planos === []): ?>
          <p class="plans-state" role="status">Nenhum plano está disponível no momento.</p>
        <?php else: ?>
          <div class="plans-grid">
            <?php foreach ($planos as $plano): ?>
              <?php
                $features = [];
                if ($plano['monthly_lesson_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['monthly_lesson_limit'] . ' aulas em vídeo publicadas por mês';
                }
                if ($plano['monthly_material_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['monthly_material_limit'] . ' material(is) ou livro(s) publicado(s) por mês';
                }
                if ($plano['monthly_question_bank_limit'] !== null) {
                    $features[] = (int) $plano['monthly_question_bank_limit'] . ' banco(s) de questões por mês';
                }
                if ($plano['questions_per_bank_limit'] !== null) {
                    $features[] = 'Até ' . (int) $plano['questions_per_bank_limit'] . ' questões por banco';
                }
                if ((bool) $plano['can_create_courses']) {
                    $features[] = 'Criação de cursos';
                }
                $isCurrent = $planoAtual === (string) $plano['code'];
              ?>
              <article class="plan-card <?= $isCurrent ? 'is-current' : '' ?>">
                <?php if ($isCurrent): ?>
                  <span class="plan-current-label">Seu plano atual</span>
                <?php endif; ?>
                <h2><?= esc($planLabels[(string) $plano['code']] ?? (string) $plano['code']) ?></h2>
                <?php if ($plano['institution_type'] !== null): ?>
                  <p><?= esc($institutionLabels[(string) $plano['institution_type']] ?? '') ?></p>
                <?php endif; ?>
                <p class="plan-price">
                  <?= (float) $plano['monthly_price'] === 0.0 ? 'Gratuito' : 'R$ ' . number_format((float) $plano['monthly_price'], 2, ',', '.') . ' / mês' ?>
                </p>
                <?php if ($plano['low_income_price'] !== null): ?>
                  <p class="plan-description">R$ <?= number_format((float) $plano['low_income_price'], 2, ',', '.') ?> / mês após validação de renda. O método de comprovação ainda será definido.</p>
                <?php endif; ?>
                <?php if ($features !== []): ?>
                  <ul class="plan-features">
                    <?php foreach ($features as $feature): ?>
                      <li><?= esc($feature) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
