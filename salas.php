<?php
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

    <div class="search-row" aria-label="Busca de salas">
      <span class="search-icon">⌕</span>
      <input id="searchInput" class="search-input" type="search" placeholder="Buscar por #tag, assunto, matéria ou curso..." aria-label="Buscar salas">
      <div class="search-filters">
        <select id="filterSubject" class="search-select" aria-label="Filtrar por matéria">
          <option value="">Matéria</option>
          <option value="matematica">Matemática</option>
          <option value="biologia">Biologia</option>
          <option value="historia">História</option>
          <option value="portugues">Português</option>
          <option value="quimica">Química</option>
        </select>
        <select id="filterType" class="search-select" aria-label="Filtrar por tipo de oferta">
          <option value="">Tipo</option>
          <option value="curso">Curso</option>
          <option value="mentoria">Mentoria</option>
          <option value="aula">Aula</option>
        </select>
        <select id="filterAccess" class="search-select" aria-label="Filtrar por tipo de acesso">
          <option value="">Acesso</option>
          <option value="gratis">Gratuito</option>
          <option value="particular">Particular</option>
        </select>
      </div>
    </div>

    <?php if (empty($salas)): ?>
      <div class="card" style="margin-top:20px;padding:26px;text-align:center;color:var(--muted);">
        Nenhuma sala encontrada no momento.
      </div>
    <?php else: ?>
      <div class="section-title"><h2>Salas disponíveis</h2></div>
      <div class="grid grid-3" id="roomsGrid">
        <?php foreach ($salas as $s): ?>
          <?php $roomIcon = !empty($s['icone']) ? (string) $s['icone'] : subject_icon_for((string) ($s['materia'] ?? '')); ?>
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
          <a class="card room-card" href="sala.php" data-room-name="<?= esc((string) ($s['nome'] ?? '')) ?>" data-room-materia="<?= esc(strtolower((string) ($s['materia'] ?? ''))) ?>" data-room-access="<?= htmlspecialchars($roomAccess, ENT_QUOTES, 'UTF-8') ?>" data-room-type="<?= htmlspecialchars($roomType, ENT_QUOTES, 'UTF-8') ?>" data-room-keywords="<?= esc((string) ($s['descricao'] ?? '') . ' ' . ($s['materia'] ?? '') . ' ' . ($s['prof'] ?? '') . ' ' . $roomTags) ?>" data-room-tags="<?= htmlspecialchars($roomTags, ENT_QUOTES, 'UTF-8') ?>">
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
<script>
  const searchInput = document.getElementById('searchInput');
  const subjectFilter = document.getElementById('filterSubject');
  const typeFilter = document.getElementById('filterType');
  const accessFilter = document.getElementById('filterAccess');
  const roomCards = Array.from(document.querySelectorAll('[data-room-name]'));

  function normalizeValue(value) {
    return String(value ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function parseHashTags(value) {
    return (String(value ?? '').match(/#\w+/g) || []).map(tag => normalizeValue(tag.replace('#', '')));
  }

  function applyRoomFilters() {
    const rawQuery = (searchInput?.value ?? '').trim();
    const query = normalizeValue(rawQuery);
    const subject = normalizeValue(subjectFilter?.value ?? '');
    const access = normalizeValue(accessFilter?.value ?? '');
    const type = normalizeValue(typeFilter?.value ?? '');
    const tagTerms = parseHashTags(rawQuery);
    const cleanedQuery = query.replace(/#\w+/g, '').trim();

    roomCards.forEach(card => {
      const text = [
        card.dataset.roomName ?? '',
        card.dataset.roomMateria ?? '',
        card.dataset.roomKeywords ?? '',
        card.dataset.roomTags ?? '',
      ].join(' ');
      const normalizedText = normalizeValue(text);

      const hasTagMatch = tagTerms.length === 0 || tagTerms.some(tag => normalizedText.includes(tag));
      const matchesQuery = !query || normalizedText.includes(cleanedQuery) || hasTagMatch;
      const matchesSubject = !subject || normalizeValue(card.dataset.roomMateria).includes(subject);
      const matchesAccess = !access || normalizeValue(card.dataset.roomAccess) === access;
      const matchesType = !type || normalizeValue(card.dataset.roomType).includes(type);

      card.style.display = matchesQuery && matchesSubject && matchesAccess && matchesType ? '' : 'none';
    });
  }

  searchInput?.addEventListener('input', applyRoomFilters);
  subjectFilter?.addEventListener('change', applyRoomFilters);
  typeFilter?.addEventListener('change', applyRoomFilters);
  accessFilter?.addEventListener('change', applyRoomFilters);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
