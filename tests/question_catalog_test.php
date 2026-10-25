<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/question_catalog.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'CREATE TABLE question_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, name TEXT, image_path TEXT,
        knowledge_area TEXT, display_order INTEGER, is_enabled INTEGER DEFAULT 1
    )',
    'CREATE TABLE question_subject_stages (
        subject_id INTEGER, stage_code TEXT, PRIMARY KEY (subject_id, stage_code)
    )',
    'CREATE TABLE question_sets (
        id INTEGER PRIMARY KEY AUTOINCREMENT, subject_id INTEGER, author_user_id INTEGER,
        activity_type TEXT, assembly_mode TEXT, title TEXT, topic TEXT, subtopic TEXT,
        description TEXT, difficulty TEXT, requested_question_count INTEGER,
        estimated_minutes INTEGER, visibility TEXT, status TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE question_set_subjects (
        question_set_id INTEGER, subject_id INTEGER, is_primary INTEGER,
        PRIMARY KEY (question_set_id, subject_id)
    )',
    'CREATE TABLE question_set_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT, question_set_id INTEGER,
        topic TEXT, subtopic TEXT, display_order INTEGER
    )',
    'CREATE TABLE question_set_questions (
        question_set_id INTEGER, question_id INTEGER, position INTEGER
    )',
    'CREATE TABLE quiz_questions (
        id INTEGER PRIMARY KEY, subject_id INTEGER, author_user_id INTEGER,
        topic TEXT, subtopic TEXT, prompt TEXT, difficulty TEXT, status TEXT
    )',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, username TEXT)',
] as $sql) {
    $pdo->exec($sql);
}

$pdo->exec(
    "INSERT INTO question_subjects (id, code, name, image_path, knowledge_area, display_order) VALUES
     (1, 'matematica', 'Matemática', 'assets/images/icons/materias/simbolo-de-constante-matematica-pi.png', 'Matemática e suas tecnologias', 20),
     (2, 'historia', 'História', 'assets/images/icons/templo-grego.png', 'Ciências Humanas', 40),
     (3, 'literatura', 'Literatura', 'assets/images/icons/materias/abra-o-livro.png', 'Linguagens', 70)"
);
$pdo->exec(
    "INSERT INTO question_subject_stages (subject_id, stage_code) VALUES
     (1, 'fundamental'), (1, 'medio'), (1, 'superior'),
     (2, 'medio'), (2, 'superior'), (3, 'medio'), (3, 'superior')"
);
$pdo->exec("INSERT INTO usuarios (id, username) VALUES (9, 'autora')");
$pdo->exec(
    "INSERT INTO question_sets
        (id, subject_id, author_user_id, activity_type, assembly_mode, title, topic, subtopic,
         description, difficulty, requested_question_count, estimated_minutes, visibility, status)
     VALUES
        (1, 1, 9, 'quiz', 'fixed', 'Frações', 'Números racionais', 'Operações',
         'Prática de matemática', 'iniciante', NULL, 12, 'public', 'published'),
        (2, 1, 9, 'simulado', 'personalized', 'Simulado de álgebra', 'Álgebra', 'Equações',
         'Avaliação de matemática', 'intermediario', 20, NULL, 'public', 'published'),
        (3, 1, 9, 'quiz', 'fixed', 'Rascunho privado', 'Geometria', NULL,
         NULL, NULL, NULL, NULL, 'private', 'draft'),
        (4, 2, 9, 'quiz', 'fixed', 'Conteúdo oculto', 'História', NULL,
         NULL, NULL, NULL, NULL, 'private', 'published')"
);
$pdo->exec(
    'INSERT INTO question_set_questions (question_set_id, question_id, position)
     VALUES (1, 101, 1), (1, 102, 2)'
);
$pdo->exec(
    'INSERT INTO question_set_subjects (question_set_id, subject_id, is_primary)
     VALUES (2, 1, 1), (2, 3, 0)'
);
$pdo->exec(
    "INSERT INTO question_set_topics (question_set_id, topic, subtopic, display_order)
     VALUES (2, 'Álgebra', 'Equações', 1)"
);
$pdo->exec(
    "INSERT INTO quiz_questions (id, subject_id, author_user_id, topic, prompt, difficulty, status) VALUES
     (101, 1, 9, 'Números racionais', 'Questão 1', 'iniciante', 'published'),
     (102, 1, 9, 'Números racionais', 'Questão 2', 'iniciante', 'published')"
);

function assert_question_catalog(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$allSubjects = load_question_subjects($pdo);
assert_question_catalog(count($allSubjects) === 3, 'Cada matéria deve aparecer uma única vez, mesmo com várias etapas.');
assert_question_catalog($allSubjects[0]['code'] === 'matematica', 'O catálogo deve respeitar a ordem definida.');
assert_question_catalog($allSubjects[0]['content_count'] === 2, 'A contagem deve incluir apenas conjuntos publicados e públicos.');
assert_question_catalog(
    $allSubjects[0]['stages'] === ['fundamental', 'medio', 'superior'],
    'As etapas devem permanecer relacionadas à mesma matéria.'
);

$fundamentalSubjects = load_question_subjects($pdo, 'fundamental');
assert_question_catalog(
    count($fundamentalSubjects) === 1 && $fundamentalSubjects[0]['code'] === 'matematica',
    'O filtro de etapa deve filtrar sem duplicar matérias.'
);
assert_question_catalog(
    count(load_question_subjects($pdo, '', 'Linguagens', 'Literatura')) === 1,
    'Os filtros por área e texto devem ser aplicados ao catálogo.'
);

$mathematics = load_question_subject($pdo, 'matematica');
assert_question_catalog(is_array($mathematics) && $mathematics['content_count'] === 2, 'A consulta da matéria deve retornar contagem real.');
$catalog = load_public_question_sets($pdo, ['subject_id' => 1]);
assert_question_catalog($catalog['total'] === 2, 'Somente conjuntos públicos publicados com questões disponíveis podem aparecer.');
assert_question_catalog($catalog['items'][0]['question_count'] === 20, 'Conjuntos personalizados devem exibir a quantidade solicitada.');
assert_question_catalog($catalog['items'][0]['estimated_minutes'] === null, 'Tempo estimado ausente não deve ser inventado.');
assert_question_catalog($catalog['items'][1]['question_count'] === 2, 'Conjuntos fixos devem contar apenas questões publicadas.');
assert_question_catalog($catalog['items'][1]['author_username'] === 'autora', 'O autor deve vir da conta associada ao conjunto.');
assert_question_catalog(
    count(load_question_catalog_filter_values($pdo, 1)['topics']) === 2
    && count(load_question_catalog_filter_values($pdo, 1)['subtopics']) === 2,
    'Filtros de tema e assunto devem vir somente de conjuntos públicos publicados.'
);

$filtered = load_public_question_sets($pdo, [
    'subject_id' => 1,
    'stage' => 'medio',
    'topic' => 'Álgebra',
    'subtopic' => 'Equações',
    'difficulty' => 'intermediario',
    'activity_type' => 'simulado',
]);
assert_question_catalog(
    $filtered['total'] === 1 && $filtered['items'][0]['title'] === 'Simulado de álgebra',
    'Matéria, etapa, tema, assunto, dificuldade e tipo devem combinar no servidor.'
);
$searched = load_public_question_sets($pdo, ['search' => 'autora']);
assert_question_catalog($searched['total'] === 2, 'A busca deve localizar pelo username público do autor.');
assert_question_catalog(count($searched['items'][0]['subjects']) === 2, 'Conjuntos personalizados podem estar associados a mais de uma matéria.');
$topicSearch = load_public_question_sets($pdo, ['search' => 'Equações']);
assert_question_catalog($topicSearch['total'] === 1, 'A busca deve incluir temas e assuntos relacionados do conjunto.');
$firstPage = load_public_question_sets($pdo, [], 1, 1);
$lastPage = load_public_question_sets($pdo, [], 2, 1);
assert_question_catalog($firstPage['pages'] === 2 && count($firstPage['items']) === 1, 'A listagem deve ser paginada.');
assert_question_catalog($lastPage['page'] === 2 && count($lastPage['items']) === 1, 'A página seguinte deve retornar o conjunto restante.');

$invalidStageRejected = false;
try {
    load_question_subjects($pdo, 'inexistente');
} catch (InvalidArgumentException) {
    $invalidStageRejected = true;
}
assert_question_catalog($invalidStageRejected, 'Etapas fora da lista devem ser rejeitadas.');

echo "OK: matérias, selos associados, filtros combináveis, busca pública, paginação, visibilidade e contagens.\n";
