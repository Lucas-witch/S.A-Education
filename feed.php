<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/feed.php';
require_auth();
$referenceShell = true;

$usuarioId = (int) $_SESSION['usuario_id'];
$usuarioPremium = !empty($_SESSION['usuario_premium_ativo']);
$readFilter = static function (string $name, int $limit): string {
  $value = $_GET[$name] ?? '';
  if (!is_string($value) || mb_strlen($value) > $limit) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  return trim($value);
};

$feedError = '';
$publicacoes = [];
$pesquisa = '';
$tipoFiltro = '';
$acessoFiltro = '';
$tiposPermitidos = ['', 'video_aula', 'artigo', 'redacao', 'pdf', 'livro', 'post', 'material'];
$acessosPermitidos = ['', 'publica', 'privada', 'sala', 'instituicao', 'premium'];
$edicao = null;

try {
  $pesquisa = $readFilter('q', 120);
  $tipoFiltro = strtolower($readFilter('tipo', 30));
  $acessoFiltro = strtolower($readFilter('acesso', 20));
  if (!in_array($tipoFiltro, $tiposPermitidos, true) || !in_array($acessoFiltro, $acessosPermitidos, true)) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  $publicacoes = feed_list_publications($pdo, $usuarioId, $usuarioPremium, $pesquisa, $tipoFiltro, $acessoFiltro);

  $editInput = $_GET['edit'] ?? '';
  if ($editInput !== '') {
    $publicationId = feed_parse_id($editInput);
    $candidate = feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium);
    if ($candidate && (int) $candidate['usuario_id'] === $usuarioId && in_array($candidate['visibilidade'], ['publica', 'privada'], true)) {
      $edicao = $candidate;
    } else {
      throw new DomainException('Publicação indisponível.');
    }
  }
} catch (InvalidArgumentException $exception) {
  http_response_code(400);
  $feedError = 'Os filtros enviados são inválidos.';
} catch (DomainException $exception) {
  http_response_code(404);
  $feedError = 'Publicação indisponível para edição.';
} catch (PDOException $exception) {
  error_log('[S.A Education][feed] ' . $exception->getMessage());
  http_response_code(500);
  $feedError = 'Não foi possível carregar o Feed agora.';
}

$editKind = 'material';
if (($edicao['tipo'] ?? '') === 'video_aula') {
  $editKind = 'free_lesson';
} elseif (($edicao['tipo'] ?? '') === 'post' && str_starts_with((string) ($edicao['categoria'] ?? ''), 'Questão')) {
  $editKind = 'question';
}
$editCategory = (string) ($edicao['categoria'] ?? '');
if ($editKind === 'question') {
  $editCategory = trim(preg_replace('/^Questão\s*:?\s*/u', '', $editCategory) ?? '');
}
$typeLabels = [
  'video_aula' => 'Aula gratuita',
  'artigo' => 'Artigo',
  'redacao' => 'Redação',
  'pdf' => 'PDF',
  'livro' => 'Livro',
  'post' => 'Publicação',
  'material' => 'Material',
];
$feedCategories = [];
$freeLessons = [];
foreach ($publicacoes as $publication) {
  $category = trim((string) ($publication['categoria'] ?? ''));
  if ($category !== '' && !in_array($category, $feedCategories, true)) {
    $feedCategories[] = $category;
  }
  if ($publication['tipo'] === 'video_aula' && !$publication['is_premium_only']) {
    $freeLessons[] = $publication;
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title>Feed</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/feed.css">
</head>
<body class="reference-shell" data-user-id="<?= $usuarioId ?>">
  <?php require 'includes/header.php'; ?>
  <main class="main">
    <div class="feed-shell">
      <header class="topbar">
        <div>
          <h1>Feed</h1>
          <p>Compartilhe o que está aprendendo e acompanhe a comunidade.</p>
        </div>
      </header>

      <div class="feed-layout">
      <section class="feed-primary" aria-label="Feed de publicações">

      <form method="get" class="feed-search" data-feed-search aria-label="Buscar publicações">
        <input name="q" type="search" maxlength="120" value="<?= esc($pesquisa) ?>" placeholder="Buscar por #tag, assunto, autor ou tema" aria-label="Buscar publicações">
        <select name="tipo" aria-label="Tipo de publicação">
          <option value="">Tipo</option>
          <option value="video_aula" <?= $tipoFiltro === 'video_aula' ? 'selected' : '' ?>>Aula gratuita</option>
          <option value="artigo" <?= $tipoFiltro === 'artigo' ? 'selected' : '' ?>>Artigo</option>
          <option value="redacao" <?= $tipoFiltro === 'redacao' ? 'selected' : '' ?>>Redação</option>
          <option value="pdf" <?= $tipoFiltro === 'pdf' ? 'selected' : '' ?>>PDF</option>
          <option value="livro" <?= $tipoFiltro === 'livro' ? 'selected' : '' ?>>Livro</option>
          <option value="post" <?= $tipoFiltro === 'post' ? 'selected' : '' ?>>Publicação</option>
          <option value="material" <?= $tipoFiltro === 'material' ? 'selected' : '' ?>>Material</option>
        </select>
        <select name="acesso" aria-label="Filtrar por visibilidade e plano">
          <option value="">Visibilidade</option>
          <option value="publica" <?= $acessoFiltro === 'publica' ? 'selected' : '' ?>>Pública</option>
          <option value="privada" <?= $acessoFiltro === 'privada' ? 'selected' : '' ?>>Privada</option>
          <option value="sala" <?= $acessoFiltro === 'sala' ? 'selected' : '' ?>>Da minha sala</option>
          <option value="instituicao" <?= $acessoFiltro === 'instituicao' ? 'selected' : '' ?>>Da minha instituição</option>
          <option value="premium" <?= $acessoFiltro === 'premium' ? 'selected' : '' ?>>Requer plano Premium</option>
        </select>
        <button class="btn btn-primary" type="submit">Buscar</button>
      </form>

      <p class="feed-status" data-feed-status aria-live="polite"></p>
      <?php if ($feedError !== ''): ?>
        <div class="feed-state feed-state-error" role="alert"><?= esc($feedError) ?></div>
      <?php else: ?>
        <section class="feed-composer" aria-labelledby="composer-title">
          <h2 id="composer-title"><?= $edicao ? 'Editar publicação' : 'Compartilhe algo que aprendeu' ?></h2>
          <form action="api/feed.php" method="post" data-publication-form>
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edicao ? 'edit' : 'publish' ?>">
            <?php if ($edicao): ?><input type="hidden" name="id" value="<?= (int) $edicao['id'] ?>"><?php endif; ?>
            <fieldset class="feed-kind-options" aria-label="Tipo de publicação">
              <?php foreach (['material' => 'Material', 'question' => 'Questão', 'free_lesson' => 'Aula gratuita'] as $kind => $label): ?>
                <label class="feed-kind-option">
                  <input type="radio" name="kind" value="<?= esc($kind) ?>" data-feed-kind <?= $editKind === $kind ? 'checked' : '' ?>>
                  <span><?= esc($label) ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <div class="feed-form-fields">
              <label class="feed-field">Título
                <input type="text" name="title" maxlength="200" value="<?= esc((string) ($edicao['titulo'] ?? '')) ?>" required>
              </label>
              <label class="feed-field">Resumo
                <input type="text" name="summary" maxlength="1000" value="<?= esc((string) ($edicao['resumo'] ?? '')) ?>">
              </label>
              <label class="feed-field">Descrição, contexto ou explicação
                <textarea name="content" maxlength="20000"><?= esc((string) ($edicao['conteudo'] ?? '')) ?></textarea>
              </label>
              <div class="feed-form-row">
                <label class="feed-field">Matéria ou categoria
                  <input type="text" name="category" maxlength="80" value="<?= esc($editCategory) ?>">
                </label>
                <label class="feed-field" data-visibility-field <?= $editKind === 'free_lesson' ? 'hidden' : '' ?>>Visibilidade
                  <select name="visibility">
                    <option value="publica" <?= ($edicao['visibilidade'] ?? 'publica') === 'publica' ? 'selected' : '' ?>>Pública</option>
                    <option value="privada" <?= ($edicao['visibilidade'] ?? '') === 'privada' ? 'selected' : '' ?>>Privada</option>
                  </select>
                </label>
              </div>
              <label class="feed-field" data-material-field <?= $editKind === 'free_lesson' ? 'hidden' : '' ?>>Link do material (opcional)
                <input type="text" name="resource_url" maxlength="255" value="<?= esc((string) ($edicao['arquivo_url'] ?? '')) ?>" placeholder="https://... ou caminho relativo ao site">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Link da aula gratuita
                <input type="url" name="video_url" maxlength="255" value="<?= esc((string) ($edicao['video_url'] ?? '')) ?>" placeholder="https://...">
              </label>
            </div>
            <div class="feed-form-actions">
              <span class="feed-form-status" data-form-status role="status"></span>
              <button class="btn btn-primary" type="submit"><?= $edicao ? 'Salvar alterações' : 'Publicar' ?></button>
            </div>
          </form>
        </section>

        <?php if ($publicacoes === []): ?>
          <div class="feed-state" role="status">Nenhuma publicação disponível para estes filtros. Seja a primeira pessoa a compartilhar algo que aprendeu.</div>
        <?php else: ?>
          <section class="feed-list" aria-label="Publicações">
            <?php foreach ($publicacoes as $pub): ?>
              <?php
              $publicationId = (int) $pub['id'];
              $isAuthor = (int) $pub['usuario_id'] === $usuarioId;
              $canEdit = $isAuthor && in_array($pub['visibilidade'], ['publica', 'privada'], true);
              $authorName = trim((string) ($pub['autor'] ?? ''));
              $authorName = $authorName !== '' ? $authorName : 'Autor não informado';
              $authorImage = trim((string) ($pub['autor_imagem'] ?? ''));
              $typeLabel = $typeLabels[(string) $pub['tipo']] ?? 'Publicação';
              $resourceCandidate = (string) ($pub['tipo'] === 'video_aula' ? ($pub['video_url'] ?? '') : ($pub['arquivo_url'] ?? ''));
              $resourceUrl = '';
              try { $resourceUrl = feed_safe_resource_url($resourceCandidate); } catch (InvalidArgumentException $exception) { $resourceUrl = ''; }
              $mayOpenResource = $resourceUrl !== '' && $pub['visibilidade'] === 'publica' && (empty($pub['is_premium_only']) || $usuarioPremium);
              $publishedAt = (string) ($pub['publicado_em'] ?? '');
              try { $publishedLabel = (new DateTimeImmutable($publishedAt))->format('d/m/Y H:i'); } catch (Throwable $exception) { $publishedLabel = 'Data não disponível'; }
              $accessLabels = ['publica' => 'Pública', 'privada' => 'Privada', 'sala' => 'Acesso pela sala', 'instituicao' => 'Acesso institucional'];
              ?>
              <article class="feed-card" data-publication-id="<?= $publicationId ?>" data-owner-id="<?= (int) $pub['usuario_id'] ?>">
                <header class="feed-card-head">
                  <div class="feed-author">
                    <?php if ($authorImage !== ''): ?>
                      <img class="feed-avatar" src="<?= esc($authorImage) ?>" alt="Foto de <?= esc($authorName) ?>">
                    <?php else: ?>
                      <span class="feed-avatar feed-avatar-initial" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($authorName, 0, 1))) ?></span>
                    <?php endif; ?>
                    <div>
                      <p class="feed-author-name"><?= esc($authorName) ?></p>
                      <p class="feed-author-meta"><?php if ((string) ($pub['autor_username'] ?? '') !== ''): ?>@<?= esc((string) $pub['autor_username']) ?> · <?php endif; ?><?= esc(perfil_label((string) ($pub['autor_perfil'] ?? ''))) ?> · <time datetime="<?= esc($publishedAt) ?>"><?= esc($publishedLabel) ?></time></p>
                    </div>
                  </div>
                  <div class="feed-card-menu">
                    <?php if ($canEdit): ?><a class="feed-action" href="feed.php?<?= esc(http_build_query(array_merge($_GET, ['edit' => $publicationId]))) ?>">Editar</a><?php endif; ?>
                    <?php if ($isAuthor): ?><button class="feed-action" type="button" data-feed-action="delete">Arquivar</button><?php endif; ?>
                  </div>
                </header>
                <span class="feed-card-type"><?= esc($typeLabel) ?> · <?= esc($accessLabels[(string) $pub['visibilidade']] ?? 'Acesso restrito') ?><?= !empty($pub['is_premium_only']) ? ' · Plano Premium' : '' ?></span>
                <h2><?= esc((string) $pub['titulo']) ?></h2>
                <?php if (trim((string) ($pub['resumo'] ?? '')) !== ''): ?><p class="feed-card-copy"><?= esc((string) $pub['resumo']) ?></p><?php endif; ?>
                <?php if (trim((string) ($pub['conteudo'] ?? '')) !== ''): ?><p class="feed-card-copy"><?= esc((string) $pub['conteudo']) ?></p><?php endif; ?>
                <?php if (trim((string) ($pub['categoria'] ?? '')) !== ''): ?><span class="feed-card-category"><?= esc((string) $pub['categoria']) ?></span><?php endif; ?>
                <?php if ($mayOpenResource): ?><div class="feed-card-resource"><a class="btn btn-outline" href="<?= esc($resourceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $pub['tipo'] === 'video_aula' ? 'Assistir aula gratuita' : 'Abrir material' ?></a></div><?php endif; ?>
                <div class="feed-card-actions">
                  <button class="feed-action" type="button" data-feed-action="like" aria-pressed="<?= !empty($pub['usuario_curtiu']) ? 'true' : 'false' ?>"><span data-action-label><?= !empty($pub['usuario_curtiu']) ? 'Curtido' : 'Curtir' ?></span> · <span data-count><?= (int) $pub['curtidas'] ?></span></button>
                  <button class="feed-action" type="button" data-feed-action="comments" aria-expanded="false">Comentários · <span data-comment-count><?= (int) $pub['comentarios'] ?></span></button>
                  <button class="feed-action" type="button" data-feed-action="save" aria-pressed="<?= !empty($pub['usuario_salvou']) ? 'true' : 'false' ?>"><span data-action-label><?= !empty($pub['usuario_salvou']) ? 'Salvo' : 'Salvar' ?></span></button>
                </div>
                <section class="feed-comments" hidden aria-label="Comentários da publicação">
                  <p class="feed-form-status feed-comment-status" data-feed-comment-status role="status"></p>
                  <ul class="feed-comment-list" data-comment-list></ul>
                  <form class="feed-comment-form" action="api/feed.php" method="post" data-comment-form>
                    <input type="hidden" name="action" value="comment">
                    <input type="hidden" name="id" value="<?= $publicationId ?>">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <textarea name="content" maxlength="2000" placeholder="Escreva um comentário" aria-label="Escreva um comentário" required></textarea>
                    <button class="btn btn-primary" type="submit">Comentar</button>
                    <span class="feed-form-status" data-form-status role="status"></span>
                  </form>
                </section>
                <p class="feed-form-status" data-card-status role="status"></p>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>
      </section>

      <aside class="feed-discovery" aria-label="Descoberta no Feed">
        <section class="feed-discovery-panel">
          <h2>Matérias nesta seleção</h2>
          <?php if ($feedCategories === []): ?>
            <p class="feed-discovery-empty">As matérias aparecerão quando houver publicações disponíveis.</p>
          <?php else: ?>
            <ul class="feed-discovery-list">
              <?php foreach (array_slice($feedCategories, 0, 8) as $category): ?>
                <li><a href="feed.php?<?= esc(http_build_query(array_merge($_GET, ['q' => $category]))) ?>"><?= esc($category) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
        <section class="feed-discovery-panel">
          <h2>Aulas gratuitas</h2>
          <?php if ($freeLessons === []): ?>
            <p class="feed-discovery-empty">Nenhuma aula gratuita encontrada nesta seleção.</p>
          <?php else: ?>
            <ul class="feed-discovery-list feed-lesson-list">
              <?php foreach (array_slice($freeLessons, 0, 4) as $lesson): ?>
                <li>
                  <a href="feed.php?<?= esc(http_build_query(array_merge($_GET, ['tipo' => 'video_aula', 'q' => (string) $lesson['titulo']]))) ?>">
                    <span>Aula gratuita</span><strong><?= esc((string) $lesson['titulo']) ?></strong>
                    <small><?= esc((string) ($lesson['categoria'] ?? '')) ?></small>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </aside>
      </div>
    </div>
  </main>
  <?php require 'includes/footer.php'; ?>
  <script src="assets/js/feed.js" defer></script>
</body>
</html>
