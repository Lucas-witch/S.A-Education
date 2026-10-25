<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/user_preferences.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/permissions.php';

$account = require_settings_account();
$userId = $account['user_id'];
$preferences = load_user_preferences($pdo, $userId);
$accountStatement = $pdo->prepare(
    'SELECT u.nome_completo, u.email, u.username, u.perfil, u.criado_em,
            i.id AS institution_id, i.nome AS institution_name, i.validation_status
     FROM usuarios u
     LEFT JOIN instituicoes i ON i.usuario_id = u.id
     WHERE u.id = :id
     LIMIT 1'
);
$accountStatement->execute(['id' => $userId]);
$accountDetails = $accountStatement->fetch(PDO::FETCH_ASSOC);
if (!is_array($accountDetails)) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

$permissions = resolve_account_permissions($pdo, $userId);
$subscriptionQuery = $pdo->prepare(
    'SELECT plan_code, status, starts_at, expires_at, payment_confirmed_at
     FROM subscriptions
     WHERE ' . ($account['institution_account'] ? 'institution_id = :account_id' : 'user_id = :account_id') . '
     ORDER BY created_at DESC
     LIMIT 1'
);
$subscriptionQuery->execute(['account_id' => $account['institution_account']
    ? (int) ($accountDetails['institution_id'] ?? 0)
    : $userId]);
$subscription = $subscriptionQuery->fetch(PDO::FETCH_ASSOC);
$subscription = is_array($subscription) ? $subscription : null;

$profileLabels = [
    'estudante' => 'Estudante',
    'professor' => 'Professor',
    'instituicao' => 'Instituição',
];
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
    'administrator' => 'Acesso administrativo',
];
$subscriptionLabels = [
    'pending' => 'Aguardando confirmação',
    'active' => 'Ativa',
    'expired' => 'Expirada',
    'canceled' => 'Cancelada',
];
$planCode = is_admin_user() ? 'administrator' : (string) ($permissions['plan_code'] ?? '');
$subscriptionStatus = 'Sem assinatura registrada';
if (is_admin_user()) {
    $subscriptionStatus = 'Acesso administrativo (sem assinatura)';
} elseif ($subscription !== null) {
    $subscriptionStatus = $subscriptionLabels[(string) $subscription['status']] ?? 'Status indisponível';
    if ((string) $subscription['status'] === 'active') {
        if (empty($subscription['payment_confirmed_at'])) {
            $subscriptionStatus = 'Pagamento não confirmado';
        } elseif (!empty($subscription['starts_at']) && new DateTimeImmutable((string) $subscription['starts_at']) > new DateTimeImmutable()) {
            $subscriptionStatus = 'Agendada';
        } elseif (!empty($subscription['expires_at']) && new DateTimeImmutable((string) $subscription['expires_at']) <= new DateTimeImmutable()) {
            $subscriptionStatus = 'Expirada';
        } else {
            $subscriptionStatus = 'Ativa';
        }
    }
}
$settingsFlash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);
$settingsFlashIsSuccess = is_array($settingsFlash) && ($settingsFlash['type'] ?? '') === 'success';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= esc($preferences['theme']) ?>" data-font-size="<?= esc($preferences['font_size']) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title>Configurações | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/configuracao.css">
</head>
<body class="reference-shell settings-body">
  <div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="main">
      <div class="settings-page" data-settings-page>
        <header class="settings-heading">
          <p class="settings-eyebrow">S.A Education</p>
          <h1>Configurações</h1>
          <p>Gerencie como você usa a plataforma. As alterações de aparência são aplicadas imediatamente e sincronizadas com sua conta.</p>
        </header>

        <?php if (is_array($settingsFlash) && isset($settingsFlash['message'])): ?>
          <p class="settings-flash <?= $settingsFlashIsSuccess ? 'is-success' : 'is-error' ?>"
             role="<?= $settingsFlashIsSuccess ? 'status' : 'alert' ?>">
            <?= esc((string) $settingsFlash['message']) ?>
          </p>
        <?php endif; ?>

        <label class="settings-mobile-nav" for="settings-section-select">
          Ir para a seção
          <select id="settings-section-select" data-settings-select>
            <option value="aparencia">Aparência e acessibilidade</option>
            <option value="notificacoes">Notificações</option>
            <option value="privacidade">Privacidade</option>
            <option value="seguranca">Segurança da conta</option>
            <option value="conta">Conta e assinatura</option>
            <option value="aprendizagem">Preferências de aprendizagem</option>
            <option value="dados">Dados pessoais</option>
          </select>
        </label>

        <div class="settings-layout">
          <nav class="settings-nav" aria-label="Seções de configurações" data-settings-nav>
            <a href="#aparencia" aria-current="location">Aparência e acessibilidade</a>
            <a href="#notificacoes">Notificações</a>
            <a href="#privacidade">Privacidade</a>
            <a href="#seguranca">Segurança da conta</a>
            <a href="#conta">Conta e assinatura</a>
            <a href="#aprendizagem">Preferências de aprendizagem</a>
            <a href="#dados">Dados pessoais</a>
          </nav>

          <div class="settings-content">
            <section class="settings-panel settings-appearance" id="aparencia" aria-labelledby="appearance-heading">
              <div class="settings-panel-heading">
                <div>
                  <p class="settings-eyebrow">Sua experiência</p>
                  <h2 id="appearance-heading">Aparência e acessibilidade</h2>
                  <p>A identidade visual oficial, incluindo as cores roxa e verde, os gradientes e os componentes da plataforma, não pode ser personalizada.</p>
                </div>
                <span class="settings-save-state" role="status" aria-live="polite" data-save-status></span>
              </div>

              <fieldset class="settings-choice-group">
                <legend>Tema de exibição</legend>
                <div class="settings-choice-grid settings-theme-choices">
                  <?php foreach (['light' => 'Claro', 'dark' => 'Escuro', 'auto' => 'Automático'] as $value => $label): ?>
                    <label class="settings-choice-card">
                      <input type="radio" name="theme" value="<?= esc($value) ?>" data-preference <?= $preferences['theme'] === $value ? 'checked' : '' ?>>
                      <span class="settings-choice-title"><?= esc($label) ?></span>
                      <span class="settings-choice-description">
                        <?= $value === 'light' ? 'Superfícies claras da identidade original.' : ($value === 'dark' ? 'Superfícies escuras com as cores de marca preservadas.' : 'Acompanha o tema do dispositivo.') ?>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>

              <fieldset class="settings-choice-group">
                <legend>Tamanho das fontes</legend>
                <p class="settings-help">O padrão mantém a escala original. A alteração é aplicada globalmente aos estilos tipográficos compatíveis.</p>
                <div class="settings-choice-grid settings-font-choices">
                  <?php foreach (['small' => 'Pequeno', 'standard' => 'Padrão', 'large' => 'Grande', 'extra-large' => 'Extragrande'] as $value => $label): ?>
                    <label class="settings-choice-card settings-font-choice">
                      <input type="radio" name="font_size" value="<?= esc($value) ?>" data-preference <?= $preferences['font_size'] === $value ? 'checked' : '' ?>>
                      <span class="settings-choice-title"><?= esc($label) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <p class="settings-preview" aria-live="polite">Prévia: textos e controles ajustam a escala tipográfica sem remover a possibilidade de zoom do navegador.</p>
              </fieldset>

              <fieldset class="settings-choice-group">
                <legend>Leitura e navegação</legend>
                <label class="settings-toggle">
                  <input type="checkbox" name="high_contrast" data-preference <?= $preferences['high_contrast'] ? 'checked' : '' ?>>
                  <span><strong>Alto contraste</strong><small>Reforça a distinção entre textos, superfícies e bordas.</small></span>
                </label>
                <label class="settings-toggle">
                  <input type="checkbox" name="reduce_motion" data-preference <?= $preferences['reduce_motion'] ? 'checked' : '' ?>>
                  <span><strong>Reduzir animações</strong><small>Minimiza movimentos e transições da interface.</small></span>
                </label>
                <label class="settings-toggle">
                  <input type="checkbox" name="underline_links" data-preference <?= $preferences['underline_links'] ? 'checked' : '' ?>>
                  <span><strong>Sublinhar links</strong><small>Ajuda a identificar links sem depender apenas da cor.</small></span>
                </label>
                <label class="settings-field">
                  <span>Espaçamento entre linhas</span>
                  <select name="line_spacing" data-preference>
                    <option value="normal" <?= $preferences['line_spacing'] === 'normal' ? 'selected' : '' ?>>Padrão</option>
                    <option value="relaxed" <?= $preferences['line_spacing'] === 'relaxed' ? 'selected' : '' ?>>Ampliado</option>
                  </select>
                </label>
              </fieldset>
              <button class="btn btn-secondary settings-reset" type="button" data-reset-preferences>Restaurar aparência padrão</button>
            </section>

            <section class="settings-panel" id="notificacoes" aria-labelledby="notifications-heading">
              <h2 id="notifications-heading">Notificações</h2>
              <p>O código atual não possui um sistema de notificações de respostas, salas, cursos, avaliações, mensagens, comunicados, e-mail ou push com preferências por canal.</p>
              <p class="settings-notice">Nenhum controle de notificação é exibido até que exista uma integração funcional. Avisos críticos de segurança não podem ser silenciados por estas configurações.</p>
            </section>

            <section class="settings-panel" id="privacidade" aria-labelledby="privacy-heading">
              <h2 id="privacy-heading">Privacidade</h2>
              <p>A participação no ranking público é opcional e exibe somente nome de usuário e pontuação. O nome completo e o e-mail não são publicados no ranking.</p>
              <p>Essa opção já faz parte da edição do perfil e permanece em <a href="perfil-dashboard.php#editar-perfil">Meu perfil</a>; ela não é duplicada nesta página.</p>
              <p>Não há controles adicionais de visibilidade de perfil ou compartilhamento de atividades implementados. O acesso a conteúdos privados continua sujeito às permissões atuais da conta e da instituição.</p>
              <p class="settings-notice">Não foi encontrada uma política de privacidade publicada ou configurada neste repositório. O texto e o link oficiais precisam ser fornecidos pela instituição responsável.</p>
            </section>

            <section class="settings-panel" id="seguranca" aria-labelledby="security-heading">
              <h2 id="security-heading">Segurança da conta</h2>
              <p>Use uma senha exclusiva. A nova senha deve ter ao menos 8 caracteres, uma letra maiúscula e um número ou símbolo.</p>
              <form class="settings-security-form" action="processa_configuracoes.php" method="post">
                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <label class="settings-field">
                  <span>Senha atual</span>
                  <input type="password" name="current_password" autocomplete="current-password" required maxlength="4096">
                </label>
                <label class="settings-field">
                  <span>Nova senha</span>
                  <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                </label>
                <label class="settings-field">
                  <span>Confirme a nova senha</span>
                  <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                </label>
                <button class="btn btn-primary" type="submit">Alterar senha</button>
              </form>
              <div class="settings-security-session">
                <div>
                  <h3>Outras sessões</h3>
                  <p>O sistema não mantém uma lista consultável de sessões ativas, mas permite invalidar as demais sessões desta conta.</p>
                </div>
                <form action="processa_configuracoes.php" method="post">
                  <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                  <input type="hidden" name="action" value="revoke_sessions">
                  <label class="settings-field">
                    <span>Confirme sua senha atual</span>
                    <input type="password" name="current_password" autocomplete="current-password" required maxlength="4096">
                  </label>
                  <button class="btn btn-secondary" type="submit">Encerrar outras sessões</button>
                </form>
              </div>
              <p class="settings-notice">Autenticação em dois fatores não está implementada. Não há configuração para ativá-la.</p>
            </section>

            <section class="settings-panel" id="conta" aria-labelledby="account-heading">
              <h2 id="account-heading">Conta e assinatura</h2>
              <dl class="settings-details">
                <div><dt>Tipo de conta</dt><dd><?= esc($profileLabels[(string) $accountDetails['perfil']] ?? 'Conta') ?></dd></div>
                <div><dt>Plano atual</dt><dd><?= esc($planLabels[$planCode] ?? ($planCode !== '' ? $planCode : 'Não informado')) ?></dd></div>
                <div><dt>Estado da assinatura</dt><dd><?= esc($subscriptionStatus) ?></dd></div>
                <?php if ($subscription !== null && !empty($subscription['expires_at'])): ?>
                  <div><dt>Expiração</dt><dd><time datetime="<?= esc((string) $subscription['expires_at']) ?>"><?= esc(date('d/m/Y', strtotime((string) $subscription['expires_at']))) ?></time></dd></div>
                <?php endif; ?>
                <?php if ($account['institution_account'] && !empty($accountDetails['validation_status'])): ?>
                  <div><dt>Validação institucional</dt><dd><?= esc((string) $accountDetails['validation_status']) ?></dd></div>
                <?php endif; ?>
              </dl>
              <a class="btn btn-secondary" href="planos.php">Consultar planos</a>
              <p class="settings-help">Alterações de plano, validação institucional e permissões administrativas não podem ser feitas por configurações comuns.</p>
            </section>

            <section class="settings-panel" id="aprendizagem" aria-labelledby="learning-heading">
              <h2 id="learning-heading">Preferências de aprendizagem</h2>
              <p>As etapas de ensino, áreas favoritas, disciplinas e recomendações personalizadas ainda não possuem preferências de conta integradas.</p>
              <p>Não há preferências persistentes de reprodução no player atual. Quando houver opções suportadas pelo player, elas poderão ser adicionadas aqui.</p>
              <p class="settings-help">Os controles de leitura disponíveis (fonte, contraste e espaçamento) estão em Aparência e acessibilidade.</p>
            </section>

            <section class="settings-panel" id="dados" aria-labelledby="data-heading">
              <h2 id="data-heading">Dados pessoais</h2>
              <dl class="settings-details">
                <div><dt>Nome</dt><dd><?= esc((string) $accountDetails['nome_completo']) ?></dd></div>
                <div><dt>Nome de usuário</dt><dd><?= esc((string) $accountDetails['username']) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= esc((string) $accountDetails['email']) ?></dd></div>
                <?php if ($account['institution_account'] && !empty($accountDetails['institution_name'])): ?>
                  <div><dt>Instituição</dt><dd><?= esc((string) $accountDetails['institution_name']) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($accountDetails['criado_em'])): ?>
                  <div><dt>Conta criada</dt><dd><time datetime="<?= esc((string) $accountDetails['criado_em']) ?>"><?= esc(date('d/m/Y', strtotime((string) $accountDetails['criado_em']))) ?></time></dd></div>
                <?php endif; ?>
              </dl>
              <p>A edição de nome, usuário, e-mail e foto permanece no formulário de <a href="perfil-dashboard.php#editar-perfil">Meu perfil</a>.</p>
              <a class="btn btn-secondary" href="api/exportar-dados.php">Baixar dados básicos disponíveis</a>
              <p class="settings-help">O arquivo inclui os dados básicos da conta e preferências salvas. A exclusão da conta não está automatizada nesta plataforma; nenhuma solicitação de exclusão será marcada como concluída aqui.</p>
            </section>
          </div>
        </div>
      </div>
    </main>
  </div>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
