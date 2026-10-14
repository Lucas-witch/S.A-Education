<?php
require 'includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require 'includes/auth.php';
require 'includes/data.php';
require_auth();

$pesquisa = trim((string) ($_GET['q'] ?? ''));
$tipoFiltro = strtolower(trim((string) ($_GET['tipo'] ?? '')));
$acessoFiltro = strtolower(trim((string) ($_GET['acesso'] ?? '')));
$usuarioPremium = !empty($_SESSION['usuario_premium_ativo']) || strtolower((string) ($_SESSION['usuario_plano'] ?? 'free')) === 'premium';

$where = ['p.status = :status'];
$params = [':status' => 'publicado'];

if ($pesquisa !== '') {
    $where[] = '(LOWER(p.titulo) LIKE :pesquisa OR LOWER(COALESCE(p.categoria, "")) LIKE :pesquisa OR LOWER(COALESCE(p.resumo, "")) LIKE :pesquisa OR LOWER(COALESCE(p.conteudo, "")) LIKE :pesquisa OR LOWER(COALESCE(u.nome_completo, "")) LIKE :pesquisa)';
    $params[':pesquisa'] = '%' . strtolower($pesquisa) . '%';
}

if ($tipoFiltro !== '') {
    $where[] = 'LOWER(p.tipo) LIKE :tipo';
    $params[':tipo'] = '%' . strtolower(str_replace(' ', '_', $tipoFiltro)) . '%';
}

if ($acessoFiltro === 'gratuito') {
    $where[] = 'p.is_premium_only = 0';
} elseif ($acessoFiltro === 'premium') {
    $where[] = 'p.is_premium_only = 1';
}

if (!$usuarioPremium) {
    $where[] = 'p.is_premium_only = 0';
}

$sql = 'SELECT p.*, u.nome_completo AS autor, u.perfil_imagem AS autor_imagem FROM publicacoes p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.publicado_em DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$publicacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feed</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <style>
    body{background:#f7f3ee}
    .feed-wrap{max-width:760px;margin:0 auto;padding:20px 0 90px}
    .feed-card{
      background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px;margin-bottom:18px
    }
    .feed-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .feed-author{display:flex;align-items:center;gap:10px}
    .avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#71308b,#a566ba);color:#fff;display:grid;place-items:center;font-weight:700}
    .feed-meta{font-size:12px;color:var(--muted)}
    .feed-tag{display:inline-block;padding:6px 10px;border-radius:999px;background:#f2eaf6;color:var(--purple);font-size:11px;font-weight:700;margin-top:8px}
    .feed-card h2{margin:8px 0 10px;font-size:20px}
    .feed-card p{margin:0;color:#3f3745;line-height:1.7;font-size:14px}
    .feed-badge{padding:6px 8px;border-radius:999px;background:#fff3d5;color:#7a5200;font-size:11px;font-weight:700}
    @media(max-width:900px){.feed-wrap{padding:12px 12px 84px}}
  </style>
</head>
<body>
  <?php require 'includes/header.php'; ?>
  <main class="main">
    <div class="feed-wrap">
      <header class="topbar">
        <div>
          <h1>Feed</h1>
          <p>Publicações recentes da comunidade</p>
        </div>
      </header>

      <form method="get" class="feed-search" aria-label="Buscar publicações">
        <span class="search-icon">⌕</span>
        <input id="feedSearch" name="q" type="search" value="<?= htmlspecialchars((string) $pesquisa, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por #tag, assunto, autor ou tema" aria-label="Buscar publicações">
        <select name="tipo" aria-label="Tipo de publicação" style="padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;">
          <option value="">Tipo</option>
          <option value="video_aula" <?= $tipoFiltro === 'video_aula' ? 'selected' : '' ?>>Vídeo aula</option>
          <option value="artigo" <?= $tipoFiltro === 'artigo' ? 'selected' : '' ?>>Artigo</option>
          <option value="redacao" <?= $tipoFiltro === 'redacao' ? 'selected' : '' ?>>Redação</option>
          <option value="material" <?= $tipoFiltro === 'material' ? 'selected' : '' ?>>Material</option>
        </select>
        <select name="acesso" aria-label="Filtrar por acesso" style="padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;">
          <option value="">Acesso</option>
          <option value="gratuito" <?= $acessoFiltro === 'gratuito' ? 'selected' : '' ?>>Gratuito</option>
          <option value="premium" <?= $acessoFiltro === 'premium' ? 'selected' : '' ?>>Premium</option>
        </select>
      </form>

      <?php foreach ($publicacoes as $pub): ?>
        <?php
        $titulo = (string) ($pub['titulo'] ?? 'Publicação');
        $autor = (string) ($pub['autor'] ?? 'Equipe S.A');
        $tipo = str_replace('_', ' ', strtolower((string) ($pub['tipo'] ?? 'post')));
        $tipoFormatado = ucwords($tipo);
        $tempo = $pub['publicado_em'] ?? 'agora';
        $texto = trim((string) ($pub['resumo'] ?? $pub['conteudo'] ?? '')) ?: 'Publicação disponível na biblioteca da plataforma.';
        $tag = (string) ($pub['categoria'] ?? 'Geral');
        $premiumOnly = !empty($pub['is_premium_only']);
        ?>
        <article class="feed-card" data-feed-text="<?= htmlspecialchars((string) ($titulo . ' ' . $autor . ' ' . $tag . ' ' . $tipoFormatado . ' ' . $texto), ENT_QUOTES, 'UTF-8') ?>" data-feed-tags="<?= htmlspecialchars((string) ('#' . strtolower(str_replace(' ', '', $tag)) . ' #' . strtolower(str_replace(' ', '', $tipoFormatado))), ENT_QUOTES, 'UTF-8') ?>">
          <div class="feed-head">
            <div class="feed-author">
              <div class="avatar"><?= strtoupper(substr($autor,0,1)) ?></div>
              <div>
                <strong><?= htmlspecialchars($autor) ?></strong>
                <div class="feed-meta"><?= htmlspecialchars($tipoFormatado) ?> · <?= htmlspecialchars((string) $tempo) ?></div>
              </div>
            </div>
            <?php if ($premiumOnly): ?>
              <span class="feed-badge">Premium</span>
            <?php endif; ?>
          </div>

          <div class="feed-tag"><?= htmlspecialchars($tag) ?></div>
          <h2><?= htmlspecialchars($titulo) ?></h2>
          <p><?= htmlspecialchars($texto) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </main>
  <script>
    const feedSearch = document.getElementById('feedSearch');
    const feedCards = Array.from(document.querySelectorAll('.feed-card'));

    function normalizeSearch(value) {
      return String(value ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function extractTags(value) {
      return (String(value ?? '').match(/#\w+/g) || []).map(tag => normalizeSearch(tag).replace('#', ''));
    }

    function applyFeedFilter() {
      const rawTerm = (feedSearch?.value ?? '').trim();
      const term = normalizeSearch(rawTerm);
      const tags = extractTags(rawTerm);
      const cleanTerm = term.replace(/#\w+/g, '').trim();

      feedCards.forEach(card => {
        const text = normalizeSearch(card.dataset.feedText ?? '');
        const tagText = normalizeSearch(card.dataset.feedTags ?? '');
        const hasTagMatch = tags.length === 0 || tags.some(tag => tagText.includes(tag));
        const matches = !term || text.includes(cleanTerm) || hasTagMatch;
        card.style.display = matches ? '' : 'none';
      });
    }

    feedSearch?.addEventListener('input', applyFeedFilter);
  </script>
  <?php require 'includes/footer.php'; ?>
</body>
</html>
