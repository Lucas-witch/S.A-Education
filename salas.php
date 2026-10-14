<?php
require __DIR__ . '/includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

function subject_icon_for(string $materia): string {
    $map = [
        'matematica' => 'assets/images/icons/matematica-icon.png',
        'biologia' => 'assets/images/icons/biologia-icon.png',
        'historia' => 'assets/images/icons/historia.png',
        'portugues' => 'assets/images/icons/portugues.png',
        'quimica' => 'assets/images/icons/química.png',
        'química' => 'assets/images/icons/química.png',
    ];

    $normalized = strtolower(trim($materia));
    return $map[$normalized] ?? 'assets/images/icons/user-icon.png';
}

$pesquisa = trim((string) ($_GET['q'] ?? ''));
$materiaFiltro = strtolower(trim((string) ($_GET['materia'] ?? '')));
$tipoFiltro = strtolower(trim((string) ($_GET['tipo'] ?? '')));
$acessoFiltro = strtolower(trim((string) ($_GET['acesso'] ?? '')));

$where = ['1=1'];
$params = [];

$usuarioAtualId = (int) ($_SESSION['usuario_id'] ?? 0);
if ($usuarioAtualId > 0) {
    $where[] = '(s.privacidade <> :privada OR EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = s.id AND su.usuario_id = :usuario_id AND su.status = "ativo"))';
    $params[':privada'] = 'privada';
    $params[':usuario_id'] = $usuarioAtualId;
} else {
    $where[] = 's.privacidade <> :privada';
    $params[':privada'] = 'privada';
}

if ($pesquisa !== '') {
    $where[] = '(LOWER(s.nome) LIKE :pesquisa OR LOWER(COALESCE(s.prof, "")) LIKE :pesquisa OR LOWER(COALESCE(s.materia, "")) LIKE :pesquisa OR LOWER(COALESCE(s.descricao, "")) LIKE :pesquisa OR LOWER(COALESCE(s.codigo, "")) LIKE :pesquisa)';
    $params[':pesquisa'] = '%' . strtolower($pesquisa) . '%';
}

if ($materiaFiltro !== '') {
    $where[] = 'LOWER(s.materia) LIKE :materia';
    $params[':materia'] = '%' . $materiaFiltro . '%';
}

if ($tipoFiltro !== '') {
    $where[] = '(LOWER(COALESCE(s.nome, "")) LIKE :tipo OR LOWER(COALESCE(s.descricao, "")) LIKE :tipo OR LOWER(COALESCE(s.materia, "")) LIKE :tipo OR LOWER(COALESCE(s.prof, "")) LIKE :tipo)';
    $params[':tipo'] = '%' . $tipoFiltro . '%';
}

if ($acessoFiltro === 'gratis') {
    $where[] = 's.privacidade = :acesso';
    $params[':acesso'] = 'publica';
} elseif ($acessoFiltro === 'particular') {
    $where[] = 's.privacidade = :acesso';
    $params[':acesso'] = 'privada';
}

$sql = 'SELECT s.id, s.nome, s.prof, s.alunos, s.materia, s.icone, s.codigo, s.privacidade, s.descricao FROM salas s WHERE ' . implode(' AND ', $where) . ' ORDER BY s.criado_em DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$perfilAtual = current_user_perfil();
$perfilTitulo = [
    'estudante' => 'Salas para aprender',
    'professor' => 'Minhas salas e acompanhamento',
    'instituicao' => 'Salas da instituição',
][$perfilAtual] ?? 'Salas';

$acaoTexto = [
    'estudante' => 'Entrar em sala',
    'professor' => 'Criar sala',
    'instituicao' => 'Gerenciar salas',
][$perfilAtual] ?? 'Entrar em sala';

$acaoLink = [
    'estudante' => 'entrar-sala.php',
    'professor' => 'criar-sala.php',
    'instituicao' => 'criar-sala.php',
][$perfilAtual] ?? 'entrar-sala.php';
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
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <header class="topbar">
      <div>
        <h1>Salas</h1>
        <p><?= htmlspecialchars($perfilTitulo) ?></p>
      </div>
      <a href="<?= htmlspecialchars($acaoLink) ?>" class="btn btn-primary"><?= htmlspecialchars($acaoTexto) ?></a>
    </header>

    <form method="get" class="search-row" aria-label="Busca de salas">
      <span class="search-icon">⌕</span>
      <input id="searchInput" name="q" class="search-input" type="search" value="<?= htmlspecialchars((string) $pesquisa, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por #tag, assunto, matéria ou curso..." aria-label="Buscar salas">
      <div class="search-filters">
        <select id="filterSubject" name="materia" class="search-select" aria-label="Filtrar por matéria">
          <option value="">Matéria</option>
          <option value="matematica" <?= $materiaFiltro === 'matematica' ? 'selected' : '' ?>>Matemática</option>
          <option value="biologia" <?= $materiaFiltro === 'biologia' ? 'selected' : '' ?>>Biologia</option>
          <option value="historia" <?= $materiaFiltro === 'historia' ? 'selected' : '' ?>>História</option>
          <option value="portugues" <?= $materiaFiltro === 'portugues' ? 'selected' : '' ?>>Português</option>
          <option value="quimica" <?= $materiaFiltro === 'quimica' ? 'selected' : '' ?>>Química</option>
        </select>
        <select id="filterType" name="tipo" class="search-select" aria-label="Filtrar por tipo de oferta">
          <option value="">Tipo</option>
          <option value="curso" <?= $tipoFiltro === 'curso' ? 'selected' : '' ?>>Curso</option>
          <option value="mentoria" <?= $tipoFiltro === 'mentoria' ? 'selected' : '' ?>>Mentoria</option>
          <option value="aula" <?= $tipoFiltro === 'aula' ? 'selected' : '' ?>>Aula</option>
        </select>
        <select id="filterAccess" name="acesso" class="search-select" aria-label="Filtrar por tipo de acesso">
          <option value="">Acesso</option>
          <option value="gratis" <?= $acessoFiltro === 'gratis' ? 'selected' : '' ?>>Gratuito</option>
          <option value="particular" <?= $acessoFiltro === 'particular' ? 'selected' : '' ?>>Particular</option>
        </select>
      </div>
    </form>

    <?php if (empty($salas)): ?>
      <div class="card" style="margin-top:20px;padding:26px;text-align:center;color:var(--muted);">
        Nenhuma sala encontrada no momento.
      </div>
    <?php else: ?>
      <div class="section-title"><h2>Salas disponíveis</h2></div>
      <div class="grid grid-3" id="roomsGrid">
        <?php foreach ($salas as $s): ?>
          <?php $roomId = (int) ($s['id'] ?? 0); $roomIcon = !empty($s['icone']) ? (string) $s['icone'] : subject_icon_for((string) ($s['materia'] ?? '')); ?>
          <?php $roomAccess = strtolower((string) ($s['privacidade'] ?? 'publica')) === 'privada' ? 'particular' : 'gratis'; ?>
          <?php $roomType = strtolower((string) ($s['tipo'] ?? ($s['categoria'] ?? ($s['formato'] ?? 'curso')))); ?>
          <?php $roomTags = implode(' ', array_filter([
              '#' . strtolower(str_replace(' ', '', (string) ($s['materia'] ?? ''))),
              '#' . strtolower(str_replace(' ', '', (string) ($s['nome'] ?? ''))),
              '#' . strtolower(str_replace(' ', '', (string) ($s['prof'] ?? ''))),
              '#' . strtolower(str_replace(' ', '', (string) ($s['tipo'] ?? 'curso'))),
              '#' . $roomAccess,
              '#' . $roomType,
          ])); ?>
          <a class="card room-card" href="sala.php?id=<?= $roomId ?>" data-room-name="<?= esc((string) ($s['nome'] ?? '')) ?>" data-room-materia="<?= esc(strtolower((string) ($s['materia'] ?? ''))) ?>" data-room-access="<?= htmlspecialchars($roomAccess, ENT_QUOTES, 'UTF-8') ?>" data-room-type="<?= htmlspecialchars($roomType, ENT_QUOTES, 'UTF-8') ?>" data-room-keywords="<?= esc((string) ($s['descricao'] ?? '') . ' ' . ($s['materia'] ?? '') . ' ' . ($s['prof'] ?? '') . ' ' . $roomTags) ?>" data-room-tags="<?= htmlspecialchars($roomTags, ENT_QUOTES, 'UTF-8') ?>">
            <div class="room-icon">
              <img src="<?= esc((string) $roomIcon) ?>" alt="Ícone da matéria" style="width:38px;height:38px;object-fit:contain;display:block">
            </div>
            <div>
              <h3><?= esc((string) ($s['nome'] ?? 'Sala')) ?></h3>
              <p><?= esc((string) ($s['prof'] ?? 'Professor')) ?> · <?= intval($s['alunos'] ?? 0) ?> alunos</p>
              <small class="room-badge room-badge-<?= $roomAccess === 'particular' ? 'private' : 'free' ?>"><?= $roomAccess === 'particular' ? 'Particular' : 'Gratuito' ?></small>
            </div>
            <span class="arrow">›</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
