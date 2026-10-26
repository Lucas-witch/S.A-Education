<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/feed.php';
require_once SA_PROJECT_ROOT . '/includes/room_access.php';
require_auth();
$referenceShell = true;

$usuarioId = (int) $_SESSION['usuario_id'];
$usuarioPremium = usuario_tem_acesso_premium();
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
$paginaFeed = 1;
$feedHasMore = false;
$tiposPermitidos = ['', 'video_aula', 'artigo', 'redacao', 'pdf', 'livro', 'post', 'material'];
$acessosPermitidos = ['', 'publica', 'privada', 'sala', 'instituicao', 'premium'];
$edicao = null;
$rascunhos = [];
$editingDraft = false;
$feedRoomId = null;
$feedRoomTitle = '';
$roomsForFeed = [];
$feedKinds = [
  'post' => 'Publicação no Feed',
  'question' => 'Dúvida no Feed',
  'material' => 'Material',
  'free_lesson' => 'Aula gratuita',
];
if (is_professor() || is_admin_user()) {
  $feedKinds['bank_question'] = 'Questão para banco';
}

try {
  $pesquisa = $readFilter('q', 120);
  $tipoFiltro = strtolower($readFilter('tipo', 30));
  $acessoFiltro = strtolower($readFilter('acesso', 20));
  $paginaInput = $readFilter('pagina', 5);
  if ($paginaInput !== '') {
    if (preg_match('/^[1-9][0-9]{0,4}$/', $paginaInput) !== 1 || (int) $paginaInput > 10000) {
      throw new InvalidArgumentException('Página inválida.');
    }
    $paginaFeed = (int) $paginaInput;
  }
  $roomFilter = $_GET['sala'] ?? '';
  if (!is_string($roomFilter)) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  if ($roomFilter !== '') {
    $feedRoomId = feed_parse_id($roomFilter);
    $roomQuery = $pdo->prepare(
      'SELECT id, nome, criador_id, institution_id, privacidade
       FROM salas WHERE id = :room_id LIMIT 1'
    );
    $roomQuery->execute(['room_id' => $feedRoomId]);
    $feedRoom = $roomQuery->fetch(PDO::FETCH_ASSOC);
    if (
      !is_array($feedRoom)
      || !room_user_can_access($pdo, $feedRoom, $usuarioId, is_admin_user())
      || (!is_admin_user() && !room_user_is_member($pdo, $feedRoomId, $usuarioId))
    ) {
      throw new DomainException('Sala indisponível para o Feed.');
    }
    $feedRoomTitle = (string) $feedRoom['nome'];
  }
  if (!in_array($tipoFiltro, $tiposPermitidos, true) || !in_array($acessoFiltro, $acessosPermitidos, true)) {
    throw new InvalidArgumentException('Filtro inválido.');
  }
  $publicacoes = feed_list_publications(
    $pdo,
    $usuarioId,
    $usuarioPremium,
    $pesquisa,
    $tipoFiltro,
    $acessoFiltro,
    40,
    $feedRoomId,
    $paginaFeed,
    $feedHasMore
  );
  $rascunhos = feed_list_drafts($pdo, $usuarioId);
  $roomListQuery = $pdo->prepare(
    'SELECT s.id, s.nome, s.criador_id, s.institution_id, s.privacidade
     FROM salas s
     INNER JOIN salas_usuarios su ON su.sala_id = s.id
     WHERE su.usuario_id = :user_id AND su.status = "ativo"
     ORDER BY s.nome'
  );
  $roomListQuery->execute(['user_id' => $usuarioId]);
  foreach ($roomListQuery->fetchAll(PDO::FETCH_ASSOC) as $roomOption) {
    if (room_user_can_access($pdo, $roomOption, $usuarioId, is_admin_user())) {
      $roomsForFeed[] = $roomOption;
    }
  }

  $editInput = $_GET['edit'] ?? '';
  if ($editInput !== '') {
    $publicationId = feed_parse_id($editInput);
    $candidate = feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium);
    if ($candidate && (int) $candidate['usuario_id'] === $usuarioId && in_array($candidate['visibilidade'], ['publica', 'privada', 'sala'], true)) {
      $edicao = $candidate;
    } else {
      throw new DomainException('Publicação indisponível.');
    }
  }
  $draftInput = $_GET['draft'] ?? '';
  if ($draftInput !== '') {
    $draftId = feed_parse_id($draftInput);
    foreach ($rascunhos as $draft) {
      if ((int) $draft['id'] === $draftId) {
        $edicao = $draft;
        $editingDraft = true;
        break;
      }
    }
    if (!$editingDraft) {
      throw new DomainException('Rascunho indisponível.');
    }
  }
} catch (InvalidArgumentException $exception) {
  http_response_code(400);
  $feedError = 'Os filtros enviados são inválidos.';
} catch (DomainException $exception) {
  http_response_code(404);
  $feedError = 'Publicação indisponível para edição.';
} catch (PDOException $exception) {
  $failure = database_request_failure($exception, 'feed');
  http_response_code($failure['status']);
  $feedError = $failure['message'];
}

$editKind = 'material';
if (($edicao['tipo'] ?? '') === 'video_aula') {
  $editKind = 'free_lesson';
} elseif (($edicao['tipo'] ?? '') === 'post') {
  $existingCategory = (string) ($edicao['categoria'] ?? '');
  if (str_starts_with($existingCategory, 'Banco de questões')) {
    $editKind = 'bank_question';
  } elseif (str_starts_with($existingCategory, 'Questão') || str_starts_with($existingCategory, 'Dúvida')) {
    $editKind = 'question';
  } else {
    $editKind = 'post';
  }
}
$editCategory = (string) ($edicao['categoria'] ?? '');
if (in_array($editKind, ['question', 'bank_question'], true)) {
  $prefix = $editKind === 'bank_question' ? '/^Banco de questões\s*:?\s*/u' : '/^(Questão|Dúvida)\s*:?\s*/u';
  $editCategory = trim(preg_replace($prefix, '', $editCategory) ?? '');
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
  <?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
  <main class="main">
    <div class="feed-shell">
      <header class="topbar">
        <div>
          <h1><?= $feedRoomId !== null ? 'Discussões da sala' : 'Feed' ?></h1>
          <p><?= $feedRoomId !== null ? esc($feedRoomTitle) . ' · publicações visíveis somente aos participantes' : 'Compartilhe o que está aprendendo e acompanhe a comunidade.' ?></p>
        </div>
      </header>

      <div class="feed-layout">
      <section class="feed-primary" aria-label="Feed de publicações">

      <form method="get" class="feed-search" data-feed-search aria-label="Buscar publicações">
        <?php if ($feedRoomId !== null): ?><input type="hidden" name="sala" value="<?= $feedRoomId ?>"><?php endif; ?>
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
          <form action="api/feed.php" method="post" enctype="multipart/form-data" data-publication-form>
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editingDraft ? 'publish_draft' : ($edicao ? 'edit' : 'publish') ?>">
            <?php if ($edicao): ?><input type="hidden" name="id" value="<?= (int) $edicao['id'] ?>"><?php endif; ?>
            <fieldset class="feed-kind-options" aria-label="Tipo de publicação">
              <?php foreach ($feedKinds as $kind => $label): ?>
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
              <div class="feed-field">
                <span>Texto, contexto ou explicação</span>
                <div class="feed-editor-toolbar" role="toolbar" aria-label="Formatação do texto da publicação">
                  <button type="button" data-rich-command="undo">Desfazer</button>
                  <button type="button" data-rich-command="redo">Refazer</button>
                  <select data-rich-block aria-label="Estilo do parágrafo">
                    <option value="p">Parágrafo</option><option value="h2">Título</option>
                    <option value="h3">Subtítulo</option><option value="blockquote">Citação</option>
                  </select>
                  <button type="button" data-rich-command="bold"><strong>B</strong></button>
                  <button type="button" data-rich-command="italic"><em>I</em></button>
                  <button type="button" data-rich-command="underline"><u>U</u></button>
                  <button type="button" data-rich-command="strikeThrough"><s>ABC</s></button>
                  <button type="button" data-rich-command="insertUnorderedList">Lista</button>
                  <button type="button" data-rich-command="insertOrderedList">Lista numerada</button>
                  <button type="button" data-rich-command="justifyLeft" aria-label="Alinhar à esquerda">Esquerda</button>
                  <button type="button" data-rich-command="justifyCenter" aria-label="Centralizar">Centralizar</button>
                  <button type="button" data-rich-command="justifyFull" aria-label="Justificar">Justificar</button>
                  <button type="button" data-rich-link>Adicionar link</button>
                  <button type="button" data-rich-command="removeFormat">Limpar formato</button>
                </div>
                <div class="feed-rich-editor" data-rich-editor contenteditable="true" role="textbox" aria-multiline="true" aria-label="Texto da publicação"><?= library_sanitize_rich_text((string) ($edicao['conteudo'] ?? '')) ?></div>
                <textarea hidden name="content" data-rich-source maxlength="20000"><?= esc((string) ($edicao['conteudo'] ?? '')) ?></textarea>
                <small>Use o editor para estruturar artigos e publicações longas. O texto pode ser acompanhado de arquivos no mesmo Feed.</small>
              </div>
              <div class="feed-form-row">
                <label class="feed-field">Publicar em
                  <select name="room_id" data-room-select>
                    <option value="">Feed geral</option>
                    <?php foreach ($roomsForFeed as $roomOption): ?>
                      <option value="<?= (int) $roomOption['id'] ?>" <?= (int) ($edicao['sala_id'] ?? $feedRoomId ?? 0) === (int) $roomOption['id'] ? 'selected' : '' ?>><?= esc((string) $roomOption['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="feed-field">Matéria ou categoria
                  <input type="text" name="category" maxlength="80" value="<?= esc($editCategory) ?>">
                </label>
                <label class="feed-field" data-visibility-field <?= $editKind === 'free_lesson' ? 'hidden' : '' ?>>Visibilidade
                  <select name="visibility">
                    <option value="publica" <?= ($edicao['visibilidade'] ?? 'publica') === 'publica' ? 'selected' : '' ?>>Pública</option>
                    <option value="privada" <?= ($edicao['visibilidade'] ?? '') === 'privada' ? 'selected' : '' ?>>Privada</option>
                    <?php if ($roomsForFeed !== []): ?>
                      <option value="sala" <?= ($edicao['visibilidade'] ?? '') === 'sala' || $feedRoomId !== null ? 'selected' : '' ?>>Participantes da sala</option>
                    <?php endif; ?>
                  </select>
                </label>
              </div>
              <label class="feed-field" data-material-field <?= $editKind === 'free_lesson' ? 'hidden' : '' ?>>Link do material (opcional)
                <input type="text" name="resource_url" maxlength="255" value="<?= esc((string) ($edicao['arquivo_url'] ?? '')) ?>" placeholder="https://... ou caminho relativo ao site">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Link da aula gratuita
                <input type="url" name="video_url" maxlength="255" value="<?= esc((string) ($edicao['video_url'] ?? '')) ?>" placeholder="https://...">
              </label>
              <label class="feed-field" data-attachment-field>Arquivo ou vídeo (opcional, até 20 MB)
                <input type="file" name="media" data-media-upload accept=".pdf,.epub,.docx,.pptx,.xlsx,.odt,.odp,.ods,.txt,.rtf,.csv,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm">
                <small data-media-help>Documentos, imagens e vídeos MP4/WEBM são armazenados em área privada. Para aulas, selecione apenas MP4 ou WEBM.</small>
                <?php if (!empty($edicao['media_storage_key'])): ?><small>Já existe um anexo. Envie outro arquivo para substituí-lo ou mantenha este campo vazio para preservá-lo.</small><?php endif; ?>
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Etapa de ensino
                <input type="text" name="education_stage" maxlength="100" value="<?= esc((string) ($edicao['education_stage'] ?? '')) ?>" placeholder="Fundamental, vestibular, graduação, pós-graduação, pesquisa…">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Área do conhecimento
                <input type="text" name="knowledge_area" maxlength="120" value="<?= esc((string) ($edicao['knowledge_area'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Disciplina
                <input type="text" name="subject" maxlength="120" value="<?= esc((string) ($edicao['subject'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Tema
                <input type="text" name="topic" maxlength="180" value="<?= esc((string) ($edicao['topic'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Subtópico
                <input type="text" name="subtopic" maxlength="180" value="<?= esc((string) ($edicao['subtopic'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>Curso ou sequência de aulas (opcional)
                <input type="text" name="course_title" maxlength="180" value="<?= esc((string) ($edicao['course_title'] ?? '')) ?>" placeholder="Mesmo nome para vincular aulas em sequência">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>URL de material complementar
                <input type="url" name="complementary_materials" maxlength="1000" value="<?= esc((string) ($edicao['complementary_materials'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>URL de exercícios relacionados
                <input type="url" name="related_exercises" maxlength="1000" value="<?= esc((string) ($edicao['related_exercises'] ?? '')) ?>">
              </label>
              <label class="feed-field" data-lesson-field <?= $editKind === 'free_lesson' ? '' : 'hidden' ?>>URL de legendas WebVTT (opcional)
                <input type="url" name="captions_url" maxlength="1000" value="<?= esc((string) ($edicao['captions_url'] ?? '')) ?>" placeholder="https://…/legendas.vtt">
              </label>
            </div>
            <div class="feed-form-actions">
              <span class="feed-form-status" data-form-status role="status"></span>
              <?php if (!$edicao || $editingDraft): ?>
                <button class="btn" type="button" data-save-draft>Salvar rascunho</button>
              <?php endif; ?>
              <button class="btn btn-primary" type="submit"><?= $editingDraft ? 'Publicar rascunho' : ($edicao ? 'Salvar alterações' : 'Publicar') ?></button>
            </div>
          </form>
        </section>

        <?php if ($rascunhos !== []): ?>
          <section class="feed-list" aria-labelledby="feed-drafts-title">
            <h2 id="feed-drafts-title">Meus rascunhos</h2>
            <?php foreach ($rascunhos as $draft): ?>
              <article class="feed-card">
                <h3><?= esc((string) $draft['titulo']) ?></h3>
                <p><?= esc((string) ($draft['resumo'] ?? '')) ?></p>
                <a class="btn" href="feed.php?draft=<?= (int) $draft['id'] ?>">Continuar rascunho</a>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>

        <?php if ($publicacoes === []): ?>
          <div class="feed-state" role="status"><?= $paginaFeed > 1 ? 'Não há mais publicações nesta página.' : 'Nenhuma publicação disponível para estes filtros. Seja a primeira pessoa a compartilhar algo que aprendeu.' ?></div>
        <?php else: ?>
          <section class="feed-list" aria-label="Publicações">
            <?php foreach ($publicacoes as $pub): ?>
              <?php
              $publicationId = (int) $pub['id'];
              $isAuthor = (int) $pub['usuario_id'] === $usuarioId;
              $canModerateRoom = $pub['sala_id'] !== null
                && feed_user_can_moderate_room($pdo, (int) $pub['sala_id'], $usuarioId);
              $canArchive = $isAuthor || is_admin_user() || $canModerateRoom;
              $canEdit = $isAuthor && in_array($pub['visibilidade'], ['publica', 'privada', 'sala'], true);
              $authorName = trim((string) ($pub['autor'] ?? ''));
              $authorName = $authorName !== '' ? $authorName : 'Autor não informado';
              $authorImage = trim((string) ($pub['autor_imagem'] ?? ''));
              $typeLabel = $typeLabels[(string) $pub['tipo']] ?? 'Publicação';
              $resourceCandidate = (string) ($pub['tipo'] === 'video_aula' ? ($pub['video_url'] ?? '') : ($pub['arquivo_url'] ?? ''));
              $resourceUrl = '';
              try { $resourceUrl = feed_safe_resource_url($resourceCandidate); } catch (InvalidArgumentException $exception) { $resourceUrl = ''; }
              $isVideoLesson = $pub['tipo'] === 'video_aula';
              $playerUrl = $isVideoLesson ? 'videoaula.php?id=' . $publicationId : $resourceUrl;
              $mayOpenResource = $resourceUrl !== ''
                && ($isVideoLesson || $pub['visibilidade'] === 'publica')
                && (empty($pub['is_premium_only']) || $usuarioPremium || $isAuthor);
              $mediaKey = (string) ($pub['media_storage_key'] ?? '');
              $mediaMime = (string) ($pub['media_mime'] ?? '');
              $mediaExtension = strtolower(pathinfo($mediaKey, PATHINFO_EXTENSION));
              $supportedFeedUploads = library_supported_uploads();
              $hasMedia = preg_match('/^[a-f0-9]{64}\.[a-z0-9]+$/', $mediaKey) === 1
                && isset($supportedFeedUploads[$mediaExtension])
                && $supportedFeedUploads[$mediaExtension]['mime'] === $mediaMime
                && in_array($supportedFeedUploads[$mediaExtension]['kind'], ['document', 'image', 'video'], true);
              $mayOpenMedia = $hasMedia && (empty($pub['is_premium_only']) || $usuarioPremium || $isAuthor);
              $mediaUrl = 'feed-media.php?id=' . $publicationId;
              $lessonMetadata = [];
              if ($isVideoLesson) {
                foreach (['education_stage' => 'Etapa', 'knowledge_area' => 'Área', 'subject' => 'Disciplina', 'topic' => 'Tema', 'course_title' => 'Curso'] as $field => $label) {
                  $value = trim((string) ($pub[$field] ?? ''));
                  if ($value !== '') {
                    $lessonMetadata[] = $label . ': ' . $value;
                  }
                }
              }
              $publishedAt = (string) ($pub['publicado_em'] ?? '');
              try { $publishedLabel = (new DateTimeImmutable($publishedAt))->format('d/m/Y H:i'); } catch (Throwable $exception) { $publishedLabel = 'Data não disponível'; }
              $accessLabels = ['publica' => 'Pública', 'privada' => 'Privada', 'sala' => 'Acesso pela sala', 'instituicao' => 'Acesso institucional'];
              ?>
              <article class="feed-card" data-publication-id="<?= $publicationId ?>" data-owner-id="<?= (int) $pub['usuario_id'] ?>" data-room-id="<?= (int) ($pub['sala_id'] ?? 0) ?>" data-can-moderate="<?= $canModerateRoom || is_admin_user() ? '1' : '0' ?>">
                <header class="feed-card-head">
                  <div class="feed-author">
                    <?php if ($authorImage !== ''): ?>
                      <img class="feed-avatar" src="<?= esc($authorImage) ?>" alt="Foto de <?= esc($authorName) ?>">
                    <?php else: ?>
                      <span class="feed-avatar feed-avatar-initial" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($authorName, 0, 1))) ?></span>
                    <?php endif; ?>
                    <div>
                      <p class="feed-author-name"><?= esc($authorName) ?><?php if (!empty($pub['is_official'])): ?> <span class="feed-official-badge">Oficial S.A Education</span><?php endif; ?></p>
                      <p class="feed-author-meta"><?php if ((string) ($pub['autor_username'] ?? '') !== ''): ?>@<?= esc((string) $pub['autor_username']) ?> · <?php endif; ?><?= esc(perfil_label((string) ($pub['autor_perfil'] ?? ''))) ?> · <time datetime="<?= esc($publishedAt) ?>"><?= esc($publishedLabel) ?></time></p>
                    </div>
                  </div>
                  <div class="feed-card-menu">
                    <?php if ($canEdit): ?><a class="feed-action" href="feed.php?<?= esc(http_build_query(array_merge($_GET, ['edit' => $publicationId]))) ?>">Editar</a><?php endif; ?>
                    <?php if ($canArchive): ?><button class="feed-action" type="button" data-feed-action="delete">Arquivar</button><?php endif; ?>
                  </div>
                </header>
                <span class="feed-card-type"><?= esc($typeLabel) ?> · <?= esc($accessLabels[(string) $pub['visibilidade']] ?? 'Acesso restrito') ?><?= !empty($pub['is_premium_only']) ? ' · Plano Premium' : '' ?></span>
                <h2><?= esc((string) $pub['titulo']) ?></h2>
                <?php if (trim((string) ($pub['resumo'] ?? '')) !== ''): ?><p class="feed-card-copy"><?= esc((string) $pub['resumo']) ?></p><?php endif; ?>
                <?php if (trim((string) ($pub['conteudo'] ?? '')) !== ''): ?>
                  <?php if ($pub['tipo'] === 'post'): ?>
                    <div class="feed-card-copy feed-card-rich"><?= library_sanitize_rich_text((string) $pub['conteudo']) ?></div>
                  <?php else: ?>
                    <p class="feed-card-copy"><?= esc((string) $pub['conteudo']) ?></p>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (trim((string) ($pub['categoria'] ?? '')) !== ''): ?><span class="feed-card-category"><?= esc((string) $pub['categoria']) ?></span><?php endif; ?>
                <?php if ($isVideoLesson): ?>
                  <?php if ($lessonMetadata !== []): ?><p class="feed-card-copy"><?= esc(implode(' · ', $lessonMetadata)) ?></p><?php endif; ?>
                <?php endif; ?>
                <?php if ($mayOpenMedia && str_starts_with($mediaMime, 'video/')): ?>
                  <video class="feed-card-media" controls preload="metadata" playsinline>
                    <source src="<?= esc($mediaUrl) ?>" type="<?= esc($mediaMime) ?>">
                    Seu navegador não suporta a reprodução deste vídeo.
                  </video>
                <?php elseif ($mayOpenMedia && str_starts_with($mediaMime, 'image/')): ?>
                  <img class="feed-card-media feed-card-image" src="<?= esc($mediaUrl) ?>" alt="Imagem anexada à publicação" loading="lazy" referrerpolicy="no-referrer">
                <?php elseif ($mayOpenMedia): ?>
                  <div class="feed-card-resource"><a class="btn btn-outline" href="<?= esc($mediaUrl) ?>">Abrir anexo <?= esc(strtoupper($mediaExtension)) ?></a></div>
                <?php endif; ?>
                <?php if ($mayOpenResource): ?><div class="feed-card-resource"><a class="btn btn-outline" href="<?= esc($playerUrl) ?>"<?= $isVideoLesson ? '' : ' target="_blank" rel="noopener noreferrer"' ?>><?= $isVideoLesson ? 'Assistir videoaula' : 'Abrir material' ?></a></div><?php endif; ?>
                <div class="feed-card-actions">
                  <button class="feed-action" type="button" data-feed-action="like" aria-pressed="<?= !empty($pub['usuario_curtiu']) ? 'true' : 'false' ?>"><span data-action-label><?= !empty($pub['usuario_curtiu']) ? 'Curtido' : 'Curtir' ?></span> · <span data-count><?= (int) $pub['curtidas'] ?></span></button>
                  <button class="feed-action" type="button" data-feed-action="comments" aria-expanded="false">Comentários · <span data-comment-count><?= (int) $pub['comentarios'] ?></span></button>
                  <button class="feed-action" type="button" data-feed-action="save" aria-pressed="<?= !empty($pub['usuario_salvou']) ? 'true' : 'false' ?>"><span data-action-label><?= !empty($pub['usuario_salvou']) ? 'Salvo' : 'Salvar' ?></span></button>
                </div>
                <?php if ($pub['visibilidade'] === 'sala' && $pub['sala_id'] !== null): ?>
                  <details class="feed-report">
                    <summary>Denunciar conteúdo</summary>
                    <form action="api/feed.php" method="post" data-report-form>
                      <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                      <input type="hidden" name="action" value="report">
                      <input type="hidden" name="content_type" value="publication">
                      <input type="hidden" name="id" value="<?= $publicationId ?>">
                      <label>Motivo
                        <select name="reason" required>
                          <option value="spam">Spam</option>
                          <option value="assédio">Assédio</option>
                          <option value="conteúdo inadequado">Conteúdo inadequado</option>
                          <option value="outro">Outro</option>
                        </select>
                      </label>
                      <label>Detalhes (opcional)
                        <textarea name="details" maxlength="1000"></textarea>
                      </label>
                      <button class="btn btn-outline" type="submit">Enviar denúncia</button>
                      <span class="feed-form-status" data-report-status role="status"></span>
                    </form>
                  </details>
                <?php endif; ?>
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
        <?php if ($paginaFeed > 1 || $feedHasMore): ?>
          <?php
            $feedPaginationQuery = array_filter([
              'q' => $pesquisa,
              'tipo' => $tipoFiltro,
              'acesso' => $acessoFiltro,
              'sala' => $feedRoomId,
            ], static fn ($value): bool => $value !== '' && $value !== null);
          ?>
          <nav class="feed-pagination" aria-label="Paginação do Feed">
            <span>Página <?= $paginaFeed ?></span>
            <?php if ($paginaFeed > 1): ?>
              <a class="btn btn-outline" href="feed.php?<?= esc(http_build_query($feedPaginationQuery + ['pagina' => $paginaFeed - 1])) ?>">Página anterior</a>
            <?php endif; ?>
            <?php if ($feedHasMore): ?>
              <a class="btn btn-outline" href="feed.php?<?= esc(http_build_query($feedPaginationQuery + ['pagina' => $paginaFeed + 1])) ?>">Próxima página</a>
            <?php endif; ?>
          </nav>
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
  <?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>
  <script src="assets/js/feed.js" defer></script>
</body>
</html>
