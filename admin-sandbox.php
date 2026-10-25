<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/admin_console.php';
require_once __DIR__ . '/conexao.php';

if (!is_admin_user()) {
    http_response_code(403);
    exit('Acesso exclusivo da administração.');
}

$mode = $_SESSION['admin_test_mode'] ?? null;
if (is_array($mode) && (int) ($mode['expires_at'] ?? 0) <= time()) {
    record_admin_action($pdo, 'test_mode_expired', 'permission_sandbox', null, [
        'scenario' => (string) ($mode['scenario'] ?? ''),
    ]);
    unset($_SESSION['admin_test_mode']);
    $mode = null;
}
$flash = $_SESSION['admin_sandbox_flash'] ?? null;
unset($_SESSION['admin_sandbox_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? null;
    try {
        if ($action === 'start' || $action === 'change') {
            $scenarioKey = $_POST['scenario'] ?? null;
            if (!is_string($scenarioKey)) {
                throw new InvalidArgumentException('Selecione um cenário válido.');
            }
            $preview = admin_simulate_account_permissions($pdo, $scenarioKey);
            if ($action === 'change' && !is_array($mode)) {
                throw new DomainException('Inicie o ambiente de testes antes de trocar de cenário.');
            }
            record_admin_action($pdo, $action === 'start' ? 'test_mode_started' : 'test_mode_scenario_changed', 'permission_sandbox', null, [
                'scenario' => $scenarioKey,
                'plan_code' => $preview['plan']['code'],
                'read_only' => true,
                'payment_created' => false,
            ]);
            $_SESSION['admin_test_mode'] = [
                'scenario' => $scenarioKey,
                'started_at' => is_array($mode) ? (int) $mode['started_at'] : time(),
                'expires_at' => time() + 1800,
            ];
            session_regenerate_id(true);
            $_SESSION['admin_sandbox_flash'] = [
                'type' => 'success',
                'message' => 'Simulação somente de leitura iniciada. Nenhuma conta, assinatura ou transação foi alterada.',
            ];
        } elseif ($action === 'end') {
            if (is_array($mode)) {
                record_admin_action($pdo, 'test_mode_ended', 'permission_sandbox', null, [
                    'scenario' => (string) ($mode['scenario'] ?? ''),
                    'duration_seconds' => max(0, time() - (int) ($mode['started_at'] ?? time())),
                ]);
            }
            unset($_SESSION['admin_test_mode']);
            session_regenerate_id(true);
            $_SESSION['admin_sandbox_flash'] = ['type' => 'success', 'message' => 'Ambiente de testes encerrado.'];
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (Throwable $error) {
        if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
            $_SESSION['admin_sandbox_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
        } else {
            error_log('[S.A Education][admin_test_mode] ' . $error->getMessage());
            $_SESSION['admin_sandbox_flash'] = ['type' => 'error', 'message' => 'Não foi possível atualizar o ambiente de testes.'];
        }
    }
    header('Location: admin-sandbox.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$scenarios = admin_test_scenarios();
$preview = null;
if (is_array($mode) && isset($mode['scenario']) && is_string($mode['scenario'])) {
    try {
        $preview = admin_simulate_account_permissions($pdo, $mode['scenario']);
    } catch (Throwable $error) {
        error_log('[S.A Education][admin_test_preview] ' . $error->getMessage());
        $flash = ['type' => 'error', 'message' => 'O plano deste cenário deixou de estar disponível. Encerre a simulação.'];
    }
}
$formatLimit = static fn (?int $value): string => $value === null ? 'Ilimitado' : (string) $value;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ambiente de testes | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
</head>
<body class="reference-shell">
<div class="app">
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <section class="library-wrap" aria-labelledby="sandbox-title">
      <header class="topbar">
        <div>
          <h1 id="sandbox-title">Ambiente administrativo de testes</h1>
          <p>Pré-visualize permissões calculadas com o catálogo real de planos, sem assumir a identidade de uma conta nem alterar dados de produção.</p>
        </div>
        <a class="btn" href="admin-permissoes.php">Voltar à administração</a>
      </header>
      <div class="library-state" role="note">
        <strong>Simulação isolada e somente de leitura.</strong>
        Este painel não impersona uma sessão comum, não habilita operações no restante da plataforma, não cria assinaturas e não registra pagamentos. Para testes destrutivos, use homologação com dados fictícios.
      </div>
      <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="library-state" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= esc((string) $flash['message']) ?></div>
      <?php endif; ?>
      <?php if (!is_array($mode)): ?>
        <form method="post" class="library-state">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <input type="hidden" name="action" value="start">
          <label for="scenario">Cenário a simular</label>
          <select id="scenario" name="scenario" required>
            <?php foreach ($scenarios as $key => $scenario): ?>
              <option value="<?= esc($key) ?>"><?= esc($scenario['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit">Iniciar simulação</button>
        </form>
      <?php elseif (is_array($preview)): ?>
        <section class="library-state" aria-labelledby="scenario-active-title">
          <h2 id="scenario-active-title">Cenário ativo: <?= esc($preview['scenario']['label']) ?></h2>
          <p>Expira 30 minutos após a última seleção de cenário. Iniciada às <?= esc(date('d/m/Y H:i:s', (int) $mode['started_at'])) ?>. O administrador real mantém suas permissões normais fora desta página.</p>
          <dl>
            <dt>Perfil simulado</dt><dd><?= esc($preview['scenario']['profile']) ?></dd>
            <dt>Plano de catálogo</dt><dd><?= esc((string) $preview['plan']['code']) ?></dd>
            <dt>Premium ativo na simulação</dt><dd><?= $preview['premium'] ? 'Sim' : 'Não' ?></dd>
            <dt>Perfil aprovado</dt><dd><?= $preview['approved'] ? 'Sim' : 'Não' ?></dd>
            <dt>Instituição validada</dt><dd><?= $preview['institution_validated'] ? 'Sim' : 'Não aplicável/não validada' ?></dd>
            <dt>Assinatura simulada ativa</dt><dd><?= $preview['subscription_active'] ? 'Sim (hipotética)' : 'Não' ?></dd>
            <dt>Criar cursos</dt><dd><?= $preview['can_create_courses'] ? 'Permitido' : 'Bloqueado' ?></dd>
            <dt>Limite mensal de videoaulas</dt><dd><?= esc($formatLimit($preview['monthly_lesson_limit'])) ?></dd>
            <dt>Limite mensal de materiais</dt><dd><?= esc($formatLimit($preview['monthly_material_limit'])) ?></dd>
            <dt>Bancos de questões por mês</dt><dd><?= esc($formatLimit($preview['monthly_question_bank_limit'])) ?></dd>
            <dt>Questões por banco</dt><dd><?= esc($formatLimit($preview['questions_per_bank_limit'])) ?></dd>
          </dl>
        </section>
        <form method="post" class="library-admin-form">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <label for="scenario-change">Trocar cenário</label>
          <select id="scenario-change" name="scenario" required>
            <?php foreach ($scenarios as $key => $scenario): ?>
              <option value="<?= esc($key) ?>" <?= $key === $mode['scenario'] ? 'selected' : '' ?>><?= esc($scenario['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit" name="action" value="change">Atualizar permissões simuladas</button>
          <button class="btn" type="submit" name="action" value="end" onclick="return confirm('Encerrar o ambiente de testes?')">Encerrar simulação</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
