<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/search.php';
require_auth();
$referenceShell = true;

$readFilter = static function (string $name, int $limit): string {
  $value = $_GET[$name] ?? '';
  if (!is_string($value) || mb_strlen($value) > $limit) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  return trim($value);
};

$queryError = '';
$materiais = [];
$busca = '';
$materia = '';
$tipo = '';
$usuarioPremium = false;
$tiposPermitidos = ['', 'livro', 'apostila', 'pdf', 'artigo', 'video_aula'];
try {
  $busca = $readFilter('q', 120);
  $materia = $readFilter('materia', 80);
  $tipo = strtolower($readFilter('tipo', 30));
  if (!in_array($tipo, $tiposPermitidos, true)) {
    throw new InvalidArgumentException('Tipo inválido.');
  }
  $usuarioId = (int) $_SESSION['usuario_id'];
  $usuarioPremium = !empty($_SESSION['usuario_premium_ativo']);
  $materiais = buscar_materiais_biblioteca($pdo, $usuarioId, $usuarioPremium, $busca, $materia, $tipo);
} catch (InvalidArgumentException $exception) {
  http_response_code(400);
  $queryError = 'Os filtros enviados são inválidos.';
} catch (PDOException $exception) {
  error_log('[S.A Education][biblioteca] ' . $exception->getMessage());
  http_response_code(500);
  $queryError = 'Não foi possível carregar os materiais agora.';
}

$typeLabels = [
  'livro' => 'Livro',
  'apostila' => 'Apostila',
  'pdf' => 'PDF',
  'artigo' => 'Artigo',
  'video_aula' => 'Videoaula',
  'material' => 'Material',
];

$safeCatalogUrl = static function (string $value): string {
  $value = trim($value);
  if ($value === '' || preg_match('/[\x00-\x20]/', $value)) {
    return '';
  }
  $parts = parse_url($value);
  if (!is_array($parts)) {
    return '';
  }
  if (isset($parts['scheme'])) {
    return in_array(strtolower($parts['scheme']), ['http', 'https'], true) ? $value : '';
  }
  if (isset($parts['host']) || str_starts_with($value, '/') || str_contains($value, '\\')) {
    return '';
  }
  foreach (explode('/', (string) ($parts['path'] ?? '')) as $segment) {
    if ($segment === '..' || $segment === '.') {
      return '';
    }
  }
  return $value;
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Biblioteca</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
</head>
<body class="reference-shell">
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <div class="library-wrap">
      <header class="topbar">
        <div>
          <h1>Biblioteca de conteúdos</h1>
          <p>Materiais para estudar, ensinar e compartilhar conhecimento.</p>
        </div>
      </header>

      <form class="library-filters" method="get" action="biblioteca.php" aria-label="Filtrar biblioteca">
        <label class="library-query">
          <span aria-hidden="true">#</span>
          <input type="search" name="q" maxlength="120" value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>" placeholder="Pesquisar por título, autor, disciplina ou palavra-chave" aria-label="Pesquisar materiais">
        </label>
        <input type="search" name="materia" maxlength="80" value="<?= htmlspecialchars($materia, ENT_QUOTES, 'UTF-8') ?>" placeholder="Matéria" aria-label="Filtrar por matéria">
        <button class="btn btn-primary" type="submit">Buscar</button>
      </form>

      <nav class="library-tabs" aria-label="Categorias da biblioteca">
        <?php foreach (['' => 'Todos', 'livro' => 'Livros', 'apostila' => 'Apostilas', 'pdf' => 'PDFs', 'artigo' => 'Artigos', 'video_aula' => 'Videoaulas'] as $categoryType => $categoryLabel): ?>
          <?php $categoryUrl = 'biblioteca.php?' . http_build_query(array_merge($_GET, ['tipo' => $categoryType])); ?>
          <a class="<?= $tipo === $categoryType ? 'active' : '' ?>" href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>" <?= $tipo === $categoryType ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>

      <?php if ($queryError !== ''): ?>
        <div class="library-state" role="alert"><?= htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php elseif ($materiais === []): ?>
        <div class="library-state" role="status">Nenhum material disponível corresponde aos filtros. Materiais em revisão ou sem acesso autorizado não são exibidos.</div>
      <?php else: ?>
      <div class="library-grid">
        <?php foreach ($materiais as $material): ?>
          <?php
          $type = (string) ($material['content_type'] ?? 'material');
          $access = (string) ($material['access'] ?? '');
          $availability = (string) ($material['availability'] ?? '');
          $resourceUrl = $safeCatalogUrl((string) ($material['resource_url'] ?? ''));
          $coverUrl = $safeCatalogUrl((string) ($material['cover_url'] ?? ''));
          $premiumOnly = (int) ($material['premium_only'] ?? 0) === 1;
          $canOpen = $resourceUrl !== '' && $access === 'publica' && (!$premiumOnly || $usuarioPremium) && $availability !== 'emprestimo';
          $actionLabel = $type === 'video_aula' ? 'Assistir' : ($availability === 'leitura_online' ? 'Ler online' : 'Abrir material');
          ?>
          <article class="book-card">
            <?php if ($coverUrl !== ''): ?>
              <img class="book-cover" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Capa de <?= htmlspecialchars((string) $material['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
            <?php else: ?>
              <div class="book-cover-placeholder" aria-label="Capa tipográfica">
                <strong><?= htmlspecialchars((string) $material['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars((string) (($material['subject'] ?? '') !== '' ? $material['subject'] : ($typeLabels[$type] ?? 'Material')), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            <?php endif; ?>
            <span class="book-badge"><?= htmlspecialchars($typeLabels[$type] ?? 'Material', ENT_QUOTES, 'UTF-8') ?></span>
            <h2><?= htmlspecialchars((string) $material['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="book-meta"><?= htmlspecialchars((string) (($material['responsible'] ?? '') !== '' ? $material['responsible'] : 'Autor não informado'), ENT_QUOTES, 'UTF-8') ?><?php if (($material['subject'] ?? '') !== ''): ?> · <?= htmlspecialchars((string) $material['subject'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
            <?php if (($material['description'] ?? '') !== ''): ?><p class="book-description"><?= htmlspecialchars((string) $material['description'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <span class="book-availability"><?= $availability === 'gratis' ? 'Gratuito' : ($availability === 'leitura_online' ? 'Leitura online' : 'Empréstimo') ?></span>
            <?php if ($premiumOnly): ?><span class="book-badge"><?= $usuarioPremium ? 'Plano Premium ativo' : 'Requer plano Premium' ?></span><?php endif; ?>
            <div class="book-action">
              <?php if ($canOpen): ?>
                <a class="btn btn-outline" href="<?= htmlspecialchars($resourceUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></a>
              <?php elseif ($access !== 'publica' || ($premiumOnly && !$usuarioPremium)): ?>
                <span class="book-meta">Acesso restrito; abertura integrada indisponível.</span>
              <?php elseif ($availability === 'emprestimo'): ?>
                <span class="book-meta">Fluxo de solicitação de empréstimo indisponível.</span>
              <?php else: ?>
                <span class="book-meta">Arquivo ou leitura online indisponível.</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
  <script>
    document.querySelector('.library-filters')?.addEventListener('submit', event => {
      const button = event.currentTarget.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Carregando...';
      }
    });
  </script>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
