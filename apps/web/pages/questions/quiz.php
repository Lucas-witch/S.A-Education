<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/includes/helpers.php';
require_once SA_PROJECT_ROOT . '/includes/question_catalog.php';
require_once SA_PROJECT_ROOT . '/includes/question_subject_badge.php';
require_once SA_PROJECT_ROOT . '/conexao.php';

require_auth();
$referenceShell = true;

$stage = $_GET['etapa'] ?? '';
$area = $_GET['area'] ?? '';
$search = $_GET['q'] ?? '';
$subjectCode = $_GET['materia'] ?? '';
$topic = $_GET['tema'] ?? '';
$subtopic = $_GET['assunto'] ?? '';
$difficulty = $_GET['dificuldade'] ?? '';
$activityType = $_GET['tipo'] ?? '';
$page = $_GET['pagina'] ?? '1';
$catalogError = '';
$subjects = [];
$selectedSubject = null;
$questionCatalog = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'page_size' => 18];
$filterValues = ['topics' => [], 'subtopics' => []];
$catalogHasAnyContent = false;
$areaLabels = [
    'Linguagens' => 'Linguagens',
    'Matemática e suas tecnologias' => 'Matemática e suas tecnologias',
    'Ciências da Natureza' => 'Ciências da Natureza',
    'Ciências Humanas' => 'Ciências Humanas',
];

try {
    $readFilter = static function ($value, int $limit): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Filtro inválido.');
        }
        if (mb_strlen($value, 'UTF-8') > $limit) {
            throw new InvalidArgumentException('Filtro excede o limite permitido.');
        }
        return trim($value);
    };
    $stage = $readFilter($stage, 20);
    $area = $readFilter($area, 80);
    $search = $readFilter($search, 100);
    $subjectCode = $readFilter($subjectCode, 40);
    $topic = $readFilter($topic, 180);
    $subtopic = $readFilter($subtopic, 180);
    $difficulty = $readFilter($difficulty, 20);
    $activityType = $readFilter($activityType, 20);
    $page = $readFilter($page, 6);
    if (preg_match('/^[1-9][0-9]{0,5}$/', $page) !== 1) {
        throw new InvalidArgumentException('Página inválida.');
    }
    if (!array_key_exists($area, $areaLabels) && $area !== '') {
        throw new InvalidArgumentException('Área do conhecimento inválida.');
    }
    if ($difficulty !== '' && !in_array($difficulty, ['iniciante', 'intermediario', 'avancado'], true)) {
        throw new InvalidArgumentException('Dificuldade inválida.');
    }
    if ($activityType !== '' && !in_array($activityType, ['quiz', 'simulado'], true)) {
        throw new InvalidArgumentException('Tipo de atividade inválido.');
    }

    $subjects = load_question_subjects($pdo, $stage, $area, $search);
    if ($subjectCode !== '') {
        $selectedSubject = load_question_subject($pdo, $subjectCode);
        if ($selectedSubject === null) {
            http_response_code(404);
            $catalogError = 'Matéria não encontrada.';
        }
    }
    $filterValues = load_question_catalog_filter_values(
        $pdo,
        (int) ($selectedSubject['id'] ?? 0),
        $stage,
        $area
    );
    if ($catalogError === '') {
        $questionCatalog = load_public_question_sets($pdo, [
            'subject_id' => (int) ($selectedSubject['id'] ?? 0),
            'stage' => $stage,
            'area' => $area,
            'topic' => $topic,
            'subtopic' => $subtopic,
            'difficulty' => $difficulty,
            'activity_type' => $activityType,
            'search' => $search,
        ], (int) $page);
        $catalogHasAnyContent = $questionCatalog['total'] > 0;
        if (
            !$catalogHasAnyContent
            && ($search !== '' || $subjectCode !== '' || $topic !== '' || $subtopic !== ''
                || $stage !== '' || $area !== '' || $difficulty !== '' || $activityType !== '')
        ) {
            $catalogHasAnyContent = load_public_question_sets($pdo, [], 1, 1)['total'] > 0;
        }
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $catalogError = 'Os filtros enviados são inválidos.';
} catch (PDOException $exception) {
    $failure = database_request_failure($exception, 'question_catalog');
    http_response_code($failure['status']);
    $catalogError = $failure['message'];
}

$subjectsByArea = [];
foreach ($subjects as $subject) {
    $subjectsByArea[(string) $subject['knowledge_area']][] = $subject;
}
$stageLabels = QUESTION_CATALOG_STAGES;
$resultNotice = ($_GET['resultado'] ?? '') === 'indisponivel'
    ? 'A resposta de demonstração foi desativada; nenhum resultado foi salvo.'
    : '';
$difficultyLabels = [
    'iniciante' => 'Iniciante',
    'intermediario' => 'Intermediário',
    'avancado' => 'Avançado',
];
$activityLabels = [
    'quiz' => 'Quiz',
    'simulado' => 'Simulado',
];
$activeFilters = $search !== '' || $subjectCode !== '' || $topic !== '' || $subtopic !== ''
    || $stage !== '' || $area !== '' || $difficulty !== '' || $activityType !== '';
$paginationQuery = [
    'q' => $search,
    'materia' => $subjectCode,
    'tema' => $topic,
    'assunto' => $subtopic,
    'etapa' => $stage,
    'area' => $area,
    'dificuldade' => $difficulty,
    'tipo' => $activityType,
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Questões e simulados | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/question-catalog.css">
</head>
<body class="reference-shell">
  <?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
  <main class="main">
    <div class="question-catalog">
      <header class="question-hero">
        <div class="question-hero-copy">
          <p class="question-eyebrow">Aprender também é praticar</p>
          <h1>Questões</h1>
          <p>Explore conteúdos por matéria e encontre quizzes e simulados organizados para sua jornada de estudos.</p>
        </div>
        <div class="question-hero-mark">
          <img src="assets/images/logo-app-512.png" alt="S.A Education" width="142" height="142">
        </div>
      </header>

      <?php if ($resultNotice !== ''): ?>
        <p class="question-state" role="status"><?= esc($resultNotice) ?></p>
      <?php endif; ?>

      <section class="question-discovery" aria-labelledby="subjects-title">
        <div class="question-section-heading">
          <div>
            <p class="question-eyebrow">Catálogo de conteúdos</p>
            <h2 id="subjects-title">Explore por matéria</h2>
            <p>As matérias são compartilhadas entre etapas quando aplicável. Você pode explorar sem selecionar uma etapa.</p>
          </div>
        </div>

        <form class="question-filters" method="get" action="quiz.php" role="search">
          <label class="question-search">
            <span aria-hidden="true">⌕</span>
            <span class="visually-hidden">Buscar quizzes, temas e autores</span>
            <input type="search" name="q" maxlength="100" value="<?= esc($search) ?>" placeholder="Buscar quiz, tema, matéria ou autor">
          </label>
          <?php if ($selectedSubject !== null): ?>
            <input type="hidden" name="materia" value="<?= esc($subjectCode) ?>">
          <?php else: ?>
            <label>
              <span class="visually-hidden">Filtrar por matéria</span>
              <select name="materia">
                <option value="">Todas as matérias</option>
                <?php foreach ($subjects as $subject): ?>
                  <option value="<?= esc((string) $subject['code']) ?>"><?= esc((string) $subject['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php endif; ?>
          <label>
            <span class="visually-hidden">Filtrar por etapa</span>
            <select name="etapa">
              <option value="">Todas as etapas</option>
              <?php foreach ($stageLabels as $code => $label): ?>
                <option value="<?= esc($code) ?>" <?= $stage === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span class="visually-hidden">Filtrar por área de conhecimento</span>
            <select name="area">
              <option value="">Todas as áreas</option>
              <?php foreach ($areaLabels as $value => $label): ?>
                <option value="<?= esc($value) ?>" <?= $area === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span class="visually-hidden">Filtrar por tema</span>
            <select name="tema">
              <option value="">Todos os temas</option>
              <?php foreach ($filterValues['topics'] as $filterTopic): ?>
                <option value="<?= esc($filterTopic) ?>" <?= $topic === $filterTopic ? 'selected' : '' ?>><?= esc($filterTopic) ?></option>
              <?php endforeach; ?>
              <?php if ($topic !== '' && !in_array($topic, $filterValues['topics'], true)): ?>
                <option value="<?= esc($topic) ?>" selected><?= esc($topic) ?></option>
              <?php endif; ?>
            </select>
          </label>
          <label>
            <span class="visually-hidden">Filtrar por assunto</span>
            <select name="assunto">
              <option value="">Todos os assuntos</option>
              <?php foreach ($filterValues['subtopics'] as $filterSubtopic): ?>
                <option value="<?= esc($filterSubtopic) ?>" <?= $subtopic === $filterSubtopic ? 'selected' : '' ?>><?= esc($filterSubtopic) ?></option>
              <?php endforeach; ?>
              <?php if ($subtopic !== '' && !in_array($subtopic, $filterValues['subtopics'], true)): ?>
                <option value="<?= esc($subtopic) ?>" selected><?= esc($subtopic) ?></option>
              <?php endif; ?>
            </select>
          </label>
          <label>
            <span class="visually-hidden">Filtrar por dificuldade</span>
            <select name="dificuldade">
              <option value="">Todas as dificuldades</option>
              <?php foreach ($difficultyLabels as $difficultyCode => $difficultyLabel): ?>
                <option value="<?= esc($difficultyCode) ?>" <?= $difficulty === $difficultyCode ? 'selected' : '' ?>><?= esc($difficultyLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span class="visually-hidden">Filtrar por tipo de atividade</span>
            <select name="tipo">
              <option value="">Todos os tipos</option>
              <?php foreach ($activityLabels as $activityCode => $activityLabel): ?>
                <option value="<?= esc($activityCode) ?>" <?= $activityType === $activityCode ? 'selected' : '' ?>><?= esc($activityLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="question-filter-button" type="submit">Filtrar</button>
          <?php if ($activeFilters): ?>
            <a class="question-clear-filters" href="quiz.php">Limpar filtros</a>
          <?php endif; ?>
        </form>

        <?php if ($catalogError !== ''): ?>
          <p class="question-state question-state-error" role="alert"><?= esc($catalogError) ?></p>
        <?php elseif ($subjects === []): ?>
          <p class="question-state">Nenhuma matéria corresponde a esses filtros. Tente outra etapa, área ou busca.</p>
        <?php else: ?>
          <?php foreach ($subjectsByArea as $areaName => $areaSubjects): ?>
            <section class="question-area" aria-labelledby="area-<?= esc((string) array_key_first($areaSubjects)) ?>">
              <div class="question-area-heading">
                <h3 id="area-<?= esc((string) array_key_first($areaSubjects)) ?>"><?= esc($areaName) ?></h3>
                <span><?= count($areaSubjects) ?> <?= count($areaSubjects) === 1 ? 'matéria' : 'matérias' ?></span>
              </div>
              <div class="question-subject-grid">
                <?php foreach ($areaSubjects as $subject): ?>
                  <?php
                    $subjectQuery = array_replace($paginationQuery, [
                        'materia' => $subject['code'],
                    ]);
                    unset($subjectQuery['pagina']);
                    $subjectUrl = 'quiz.php?' . http_build_query($subjectQuery) . '#conteudos';
                  ?>
                  <a class="question-subject-card <?= (int) $subject['content_count'] === 0 ? 'is-empty' : '' ?>"
                     href="<?= esc($subjectUrl) ?>"
                     aria-label="<?= esc((string) $subject['name']) ?>, <?= (int) $subject['content_count'] ?> conjuntos disponíveis">
                    <?php render_question_subject_badge($subject, 'tile'); ?>
                    <span class="question-subject-info">
                      <span class="question-subject-name"><?= esc((string) $subject['name']) ?></span>
                      <span class="question-subject-stages">
                        <?= esc(implode(' · ', array_map(
                            static fn (string $subjectStage): string => QUESTION_CATALOG_STAGES[$subjectStage],
                            $subject['stages']
                        ))) ?>
                      </span>
                      <span class="question-subject-count">
                        <?= (int) $subject['content_count'] > 0
                            ? (int) $subject['content_count'] . ((int) $subject['content_count'] === 1 ? ' conjunto' : ' conjuntos')
                            : 'Conteúdo em breve' ?>
                      </span>
                    </span>
                    <span class="question-subject-arrow" aria-hidden="true">→</span>
                  </a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <section class="question-content-section" id="conteudos" aria-labelledby="content-title">
          <div class="question-section-heading">
            <div>
              <p class="question-eyebrow"><?= $selectedSubject !== null ? esc((string) $selectedSubject['knowledge_area']) : 'Descubra conteúdos' ?></p>
              <h2 id="content-title"><?= $selectedSubject !== null ? esc((string) $selectedSubject['name']) : 'Quizzes e conjuntos publicados' ?></h2>
              <p><?= $selectedSubject !== null ? 'Conjuntos publicados e disponíveis para descoberta nesta matéria.' : 'Pesquise por título, matéria, tema, assunto ou nome de usuário público.' ?></p>
            </div>
            <?php if ($selectedSubject !== null): ?>
              <a class="question-back-link" href="quiz.php">Ver todas as matérias</a>
            <?php endif; ?>
          </div>

          <?php if ($questionCatalog['items'] === []): ?>
            <div class="question-state question-empty-state" role="status">
              <strong><?= $activeFilters && $catalogHasAnyContent ? 'Nenhum conjunto corresponde aos filtros.' : 'Ainda não há quizzes publicados no catálogo.' ?></strong>
              <span><?= $activeFilters && $catalogHasAnyContent ? 'Ajuste ou limpe os filtros para ampliar os resultados.' : 'Quando houver conjuntos públicos cadastrados, eles aparecerão aqui com autor, tema e informações disponíveis.' ?></span>
            </div>
          <?php else: ?>
            <div class="question-set-grid">
              <?php foreach ($questionCatalog['items'] as $set): ?>
                <article class="question-set-card">
                  <?php render_question_subject_badge($set); ?>
                  <div class="question-set-badges">
                    <span class="question-badge"><?= esc($activityLabels[(string) $set['activity_type']] ?? 'Atividade') ?></span>
                    <?php if (isset($difficultyLabels[(string) $set['difficulty']])): ?>
                      <span class="question-badge question-badge-muted"><?= esc($difficultyLabels[(string) $set['difficulty']]) ?></span>
                    <?php endif; ?>
                  </div>
                  <h3><?= esc((string) $set['title']) ?></h3>
                  <p class="question-set-topic"><?= esc((string) $set['topic']) ?><?= !empty($set['subtopic']) ? ' · ' . esc((string) $set['subtopic']) : '' ?></p>
                  <p class="question-set-author">Por <strong>@<?= esc((string) $set['author_username']) ?></strong></p>
                  <div class="question-set-meta">
                    <span><?= (int) $set['question_count'] ?> <?= (int) $set['question_count'] === 1 ? 'questão' : 'questões' ?></span>
                    <?php if ($set['estimated_minutes'] !== null && $set['estimated_minutes'] > 0): ?>
                      <span><?= (int) $set['estimated_minutes'] ?> min estimados</span>
                    <?php endif; ?>
                  </div>
                  <p class="question-set-unavailable">A resolução interativa ainda não está disponível.</p>
                  <a class="question-set-export" href="quiz-exportar.php?id=<?= (int) $set['id'] ?>" target="_blank" rel="noopener noreferrer">Imprimir ou salvar como PDF</a>
                </article>
              <?php endforeach; ?>
            </div>
            <?php if ($questionCatalog['pages'] > 1): ?>
              <nav class="question-pagination" aria-label="Paginação dos resultados">
                <span>Página <?= (int) $questionCatalog['page'] ?> de <?= (int) $questionCatalog['pages'] ?> · <?= (int) $questionCatalog['total'] ?> conjuntos</span>
                <div>
                  <?php if ($questionCatalog['page'] > 1): ?>
                    <a href="quiz.php?<?= esc(http_build_query($paginationQuery + ['pagina' => $questionCatalog['page'] - 1])) ?>#conteudos">Anterior</a>
                  <?php endif; ?>
                  <?php if ($questionCatalog['page'] < $questionCatalog['pages']): ?>
                    <a href="quiz.php?<?= esc(http_build_query($paginationQuery + ['pagina' => $questionCatalog['page'] + 1])) ?>#conteudos">Próxima</a>
                  <?php endif; ?>
                </div>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </section>

      <p class="question-catalog-note">A área de criação e resolução de quizzes será integrada em uma próxima etapa; as informações mostradas aqui dependem de conjuntos publicados no catálogo.</p>
    </div>
  </main>
  <?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>
</body>
</html>
