<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_auth();
require_once SA_PROJECT_ROOT . '/includes/admin_console.php';
require_once SA_PROJECT_ROOT . '/conexao.php';

if (!admin_user_can_manage_official_content($pdo, (int) ($_SESSION['usuario_id'] ?? 0))) {
    http_response_code(403);
    exit('Acesso exclusivo da administração e da equipe autorizada de conteúdo.');
}

$flash = $_SESSION['admin_official_content_flash'] ?? null;
unset($_SESSION['admin_official_content_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? null;
    try {
        if ($action === 'create') {
            $id = admin_create_official_content($pdo, $_POST);
            $_SESSION['admin_official_content_flash'] = [
                'type' => 'success',
                'message' => 'Conteúdo oficial publicado e registrado no histórico (#' . $id . ').',
            ];
        } elseif ($action === 'update' || $action === 'archive') {
            $idInput = $_POST['publication_id'] ?? null;
            $id = is_string($idInput) && ctype_digit($idInput) ? (int) $idInput : 0;
            if ($id <= 0) {
                throw new InvalidArgumentException('Conteúdo inválido.');
            }
            if ($action === 'update') {
                admin_update_official_content($pdo, $id, $_POST);
                $_SESSION['admin_official_content_flash'] = ['type' => 'success', 'message' => 'Conteúdo atualizado; a versão anterior foi preservada no histórico.'];
            } else {
                admin_archive_official_content($pdo, $id);
                $_SESSION['admin_official_content_flash'] = ['type' => 'success', 'message' => 'Conteúdo arquivado; a ação foi registrada no histórico.'];
            }
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (Throwable $error) {
        if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
            $_SESSION['admin_official_content_flash'] = ['type' => 'error', 'message' => $error->getMessage()];
        } else {
            error_log('[S.A Education][admin_official_content] ' . $error->getMessage());
            $_SESSION['admin_official_content_flash'] = ['type' => 'error', 'message' => 'Não foi possível salvar o conteúdo oficial.'];
        }
    }
    header('Location: admin-conteudo-oficial.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$actorId = (int) $_SESSION['usuario_id'];
$canManageAllOfficialContent = admin_user_can($pdo, $actorId, 'admin.content.official');
$contentSql = 'SELECT p.id, p.usuario_id, u.nome_completo AS author_name, p.tipo, p.titulo, p.resumo, p.conteudo, p.arquivo_url, p.video_url,
            categoria, education_stage, knowledge_area, subject, topic, subtopic,
            course_title, complementary_materials, related_exercises, captions_url,
            status, publicado_em
     FROM publicacoes p INNER JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.is_official = 1'
    . ($canManageAllOfficialContent ? '' : ' AND p.usuario_id = :actor_id')
    . ' ORDER BY p.id DESC LIMIT 100';
$contentQuery = $pdo->prepare($contentSql);
$contentQuery->execute($canManageAllOfficialContent ? [] : ['actor_id' => $actorId]);
$contents = $contentQuery->fetchAll(PDO::FETCH_ASSOC);
$historySql = 'SELECT h.publication_id, h.actor_user_id, u.nome_completo AS actor_name, h.action, h.snapshot, h.created_at
     FROM official_content_history h
     INNER JOIN usuarios u ON u.id = h.actor_user_id'
    . ($canManageAllOfficialContent ? '' : ' WHERE h.actor_user_id = :actor_id')
    . ' ORDER BY h.created_at DESC, h.id DESC LIMIT 100';
$historyQuery = $pdo->prepare($historySql);
$historyQuery->execute($canManageAllOfficialContent ? [] : ['actor_id' => $actorId]);
$history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Conteúdo oficial | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/catalog.css">
</head>
<body class="reference-shell">
<div class="app">
  <?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
  <main class="main">
    <section class="library-wrap" aria-labelledby="official-title">
      <header class="topbar">
        <div>
          <h1 id="official-title">Conteúdo oficial S.A Education</h1>
          <p>Publicações feitas pela conta administrativa são identificadas como oficiais; a autoria permanece atribuída à conta responsável, sem atribuí-la a terceiros.</p>
        </div>
        <a class="btn" href="admin-permissoes.php">Voltar à administração</a>
      </header>
      <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="library-state" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= esc((string) $flash['message']) ?></div>
      <?php endif; ?>
      <section class="library-admin" aria-labelledby="new-content-title">
        <h2 id="new-content-title">Publicar novo conteúdo oficial</h2>
        <form method="post" class="library-admin-form">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">
          <label for="kind">Formato</label>
          <select id="kind" name="kind" required>
            <option value="free_lesson">Videoaula</option>
            <option value="material">Material/guia</option>
            <option value="post">Orientação/publicação</option>
          </select>
          <label for="title">Título</label>
          <input id="title" name="title" maxlength="200" required>
          <label for="summary">Resumo</label>
          <textarea id="summary" name="summary" maxlength="1000"></textarea>
          <label for="category">Categoria</label>
          <input id="category" name="category" maxlength="80">
          <label for="content">Descrição ou conteúdo</label>
          <textarea id="content" name="content" maxlength="20000"></textarea>
          <label for="resource-url">URL do material (HTTP/HTTPS ou caminho local)</label>
          <input id="resource-url" name="resource_url" maxlength="255">
          <label for="video-url">URL da videoaula (HTTP/HTTPS)</label>
          <input id="video-url" name="video_url" maxlength="255">
          <label for="education-stage">Etapa de ensino (videoaula)</label>
          <input id="education-stage" name="education_stage" maxlength="100">
          <label for="knowledge-area">Área do conhecimento (videoaula)</label>
          <input id="knowledge-area" name="knowledge_area" maxlength="120">
          <label for="subject">Disciplina (videoaula)</label>
          <input id="subject" name="subject" maxlength="120">
          <label for="topic">Tema (videoaula)</label>
          <input id="topic" name="topic" maxlength="180">
          <label for="course-title">Curso/série (videoaula)</label>
          <input id="course-title" name="course_title" maxlength="180">
          <label for="captions-url">Legendas WebVTT</label>
          <input id="captions-url" name="captions_url" maxlength="1000">
          <button class="btn btn-primary" type="submit">Publicar como conteúdo oficial</button>
        </form>
      </section>
      <section class="library-admin" aria-labelledby="content-list-title">
        <h2 id="content-list-title">Conteúdos oficiais desta conta</h2>
        <?php if ($contents === []): ?><p>Ainda não há publicações oficiais.</p><?php endif; ?>
        <?php foreach ($contents as $content): ?>
          <?php
            $editKind = match ((string) $content['tipo']) {
                'video_aula' => 'free_lesson',
                'material' => 'material',
                default => 'post',
            };
          ?>
          <details class="library-state">
            <summary>#<?= (int) $content['id'] ?> · <?= esc((string) $content['titulo']) ?> · <?= esc((string) $content['status']) ?> · <?= esc((string) $content['author_name']) ?></summary>
            <?php if ($content['status'] !== 'publicado'): ?>
              <p>Conteúdo arquivado; o registro permanece disponível para consulta histórica.</p>
            <?php else: ?>
            <form method="post" class="library-admin-form">
              <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="publication_id" value="<?= (int) $content['id'] ?>">
              <input type="hidden" name="kind" value="<?= esc($editKind) ?>">
              <label>Título <input name="title" maxlength="200" value="<?= esc((string) $content['titulo']) ?>" required></label>
              <label>Resumo <textarea name="summary" maxlength="1000"><?= esc((string) ($content['resumo'] ?? '')) ?></textarea></label>
              <label>Categoria <input name="category" maxlength="80" value="<?= esc((string) ($content['categoria'] ?? '')) ?>"></label>
              <label>Conteúdo <textarea name="content" maxlength="20000"><?= esc((string) ($content['conteudo'] ?? '')) ?></textarea></label>
              <label>URL do material <input name="resource_url" maxlength="255" value="<?= esc((string) ($content['arquivo_url'] ?? '')) ?>"></label>
              <?php if ($editKind === 'free_lesson'): ?>
                <label>URL da videoaula <input name="video_url" maxlength="255" value="<?= esc((string) ($content['video_url'] ?? '')) ?>" required></label>
                <?php foreach ([
                    'education_stage' => 'Etapa de ensino',
                    'knowledge_area' => 'Área do conhecimento',
                    'subject' => 'Disciplina',
                    'topic' => 'Tema',
                    'subtopic' => 'Subtema',
                    'course_title' => 'Curso/série',
                    'complementary_materials' => 'Material complementar',
                    'related_exercises' => 'Exercícios relacionados',
                    'captions_url' => 'Legendas WebVTT',
                ] as $field => $label): ?>
                  <label><?= esc($label) ?> <input name="<?= esc($field) ?>" maxlength="1000" value="<?= esc((string) ($content[$field] ?? '')) ?>"></label>
                <?php endforeach; ?>
              <?php endif; ?>
              <button class="btn btn-primary" type="submit">Salvar edição</button>
            </form>
            <?php if ((int) $content['usuario_id'] === $actorId || $canManageAllOfficialContent): ?>
              <form method="post" class="library-admin-form" onsubmit="return confirm('Arquivar este conteúdo oficial? Ele deixará de aparecer publicamente; o histórico será preservado.')">
                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="publication_id" value="<?= (int) $content['id'] ?>">
                <button class="btn" type="submit">Arquivar conteúdo</button>
              </form>
            <?php endif; ?>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
      </section>
      <section class="library-admin" aria-labelledby="history-title">
        <h2 id="history-title">Histórico recente de alterações</h2>
        <?php if ($history === []): ?><p>Nenhuma alteração registrada.</p><?php endif; ?>
        <?php foreach ($history as $entry): ?>
          <details class="library-state">
            <summary><?= esc((string) $entry['created_at']) ?> · <?= esc((string) $entry['action']) ?> · <?= esc((string) $entry['actor_name']) ?> · conteúdo #<?= (int) ($entry['publication_id'] ?? 0) ?></summary>
            <pre><?= esc((string) $entry['snapshot']) ?></pre>
          </details>
        <?php endforeach; ?>
      </section>
    </section>
  </main>
</div>
<?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>
</body>
</html>
