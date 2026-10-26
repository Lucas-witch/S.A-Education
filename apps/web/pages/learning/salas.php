<?php
require SA_PROJECT_ROOT . '/includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/room_search.php';
require_once SA_PROJECT_ROOT . '/includes/room_access.php';

$isInstitutionSession = isset($_SESSION['instituicao_usuario_id']);
if ($isInstitutionSession) {
  require_institution_auth();
  $usuarioId = (int) $_SESSION['instituicao_usuario_id'];
} else {
  require_auth();
  $usuarioId = (int) $_SESSION['usuario_id'];
}
$permissions = resolve_account_permissions($pdo, $usuarioId);
$canCreateRooms = room_creation_is_allowed($permissions);

$readFilter = static function (string $name, int $maxLength): string {
  $value = $_GET[$name] ?? '';
  if (!is_string($value) || mb_strlen($value) > $maxLength) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  return trim($value);
};

$queryError = '';
$salas = [];
try {
  $pesquisa = $readFilter('q', 120);
  $assuntoFiltro = $readFilter('assunto', 80);
  $materiaFiltro = $readFilter('materia', 80);
  $acessoFiltro = strtolower($readFilter('acesso', 12));
  $salas = buscar_salas($pdo, $usuarioId, $pesquisa, $assuntoFiltro, $materiaFiltro, $acessoFiltro);
} catch (InvalidArgumentException $exception) {
  http_response_code(400);
  $queryError = 'Os filtros enviados são inválidos. Ajuste os campos e tente novamente.';
  $pesquisa = $assuntoFiltro = $materiaFiltro = $acessoFiltro = '';
} catch (PDOException $exception) {
  error_log('[S.A Education][salas] ' . $exception->getMessage());
  http_response_code(500);
  $queryError = 'Não foi possível carregar as salas agora. Tente novamente.';
  $pesquisa = $assuntoFiltro = $materiaFiltro = $acessoFiltro = '';
}

$perfilAtual = (string) ($permissions['profile'] ?? '');
$perfilTitulo = [
    'estudante' => 'Salas para aprender',
    'professor' => 'Minhas salas e acompanhamento',
  'instituicao' => 'Salas disponíveis',
][$perfilAtual] ?? 'Salas';

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Salas</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="app">
  <?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
  <main class="main">
    <header class="topbar">
      <div>
        <h1>Salas</h1>
        <p><?= htmlspecialchars($perfilTitulo) ?></p>
      </div>
      <?php if ($canCreateRooms): ?>
        <a href="criar-sala.php" class="btn btn-primary">Criar sala ou comunidade</a>
      <?php elseif (in_array($perfilAtual, ['estudante', 'professor'], true)): ?>
        <a href="entrar-sala.php" class="btn btn-primary">Entrar em sala</a>
      <?php endif; ?>
    </header>

    <form id="room-search" method="get" class="search-row" aria-label="Busca de salas">
      <span class="search-icon">⌕</span>
      <input id="searchInput" name="q" class="search-input" type="search" value="<?= htmlspecialchars((string) $pesquisa, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome, professor ou palavra-chave" aria-label="Buscar salas">
      <div class="search-filters">
        <input name="assunto" class="search-select" type="search" maxlength="80" value="<?= htmlspecialchars((string) $assuntoFiltro, ENT_QUOTES, 'UTF-8') ?>" placeholder="Assunto" aria-label="Filtrar por assunto">
        <input name="materia" class="search-select" type="search" maxlength="80" value="<?= htmlspecialchars((string) $materiaFiltro, ENT_QUOTES, 'UTF-8') ?>" placeholder="Matéria" aria-label="Filtrar por matéria">
        <select id="filterAccess" name="acesso" class="search-select" aria-label="Filtrar por tipo de acesso">
          <option value="">Acesso</option>
          <option value="publica" <?= $acessoFiltro === 'publica' ? 'selected' : '' ?>>Pública</option>
          <option value="privada" <?= $acessoFiltro === 'privada' ? 'selected' : '' ?>>Privada</option>
          <option value="instituicao" <?= $acessoFiltro === 'instituicao' ? 'selected' : '' ?>>Institucional</option>
        </select>
        <button type="submit" class="btn btn-primary" data-search-submit>Buscar</button>
      </div>
    </form>

    <?php if ($queryError !== ''): ?>
      <div class="card" role="alert" style="margin-top:20px;padding:26px;text-align:center;color:var(--muted);">
        <?= htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php elseif (empty($salas)): ?>
      <div class="card" style="margin-top:20px;padding:26px;text-align:center;color:var(--muted);">
        Nenhuma sala encontrada no momento.
      </div>
    <?php else: ?>
      <div class="section-title"><h2>Salas disponíveis</h2></div>
      <div class="grid grid-3" id="roomsGrid">
        <?php foreach ($salas as $s): ?>
          <?php $roomId = (int) ($s['id'] ?? 0); $roomIcon = subject_icon_for((string) ($s['materia'] ?? '')); ?>
          <?php $roomAccess = strtolower((string) ($s['privacidade'] ?? 'publica')) === 'privada' ? 'privada' : 'publica'; ?>
          <a class="card room-card" href="sala.php?id=<?= $roomId ?>">
            <div class="room-icon">
              <img src="<?= esc((string) $roomIcon) ?>" alt="" aria-hidden="true" class="room-subject-icon">
            </div>
            <div>
              <h3><?= esc((string) ($s['nome'] ?? 'Sala')) ?></h3>
              <p><?= esc((string) ($s['prof'] ?? 'Responsável não informado')) ?> · <?= intval($s['alunos'] ?? 0) ?> participantes</p>
              <?php if (trim((string) ($s['categoria'] ?? '')) !== ''): ?>
                <p><?= esc((string) $s['categoria']) ?><?= trim((string) ($s['area_conhecimento'] ?? '')) !== '' ? ' · ' . esc((string) $s['area_conhecimento']) : '' ?></p>
              <?php endif; ?>
              <small class="room-badge room-badge-<?= $roomAccess === 'privada' ? 'private' : 'public' ?>"><?= $roomAccess === 'privada' ? 'Privada' : 'Pública' ?></small>
            </div>
            <span class="arrow">›</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<script>
  document.getElementById('room-search')?.addEventListener('submit', () => {
    const button = document.querySelector('[data-search-submit]');
    if (button) {
      button.disabled = true;
      button.textContent = 'Buscando...';
    }
  });
</script>
<?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>
</body>
</html>
