<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/question_export.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach ([
    'CREATE TABLE question_subjects (id INTEGER PRIMARY KEY, name TEXT, is_enabled INTEGER)',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, username TEXT)',
    'CREATE TABLE question_sets (
        id INTEGER PRIMARY KEY, subject_id INTEGER, author_user_id INTEGER, activity_type TEXT,
        assembly_mode TEXT, title TEXT, topic TEXT, subtopic TEXT, description TEXT, difficulty TEXT,
        requested_question_count INTEGER, estimated_minutes INTEGER, visibility TEXT, status TEXT
    )',
    'CREATE TABLE question_set_subjects (question_set_id INTEGER, subject_id INTEGER)',
    'CREATE TABLE question_set_questions (question_set_id INTEGER, question_id INTEGER, position INTEGER)',
    'CREATE TABLE quiz_questions (id INTEGER PRIMARY KEY, subject_id INTEGER, status TEXT, prompt TEXT)',
    'CREATE TABLE quiz_question_options (question_id INTEGER, option_text TEXT, position INTEGER, is_correct INTEGER)',
] as $sql) {
    $pdo->exec($sql);
}
$pdo->exec("INSERT INTO question_subjects VALUES (1, 'Matemática', 1), (2, 'História', 0)");
$pdo->exec("INSERT INTO usuarios VALUES (9, 'autora')");
$pdo->exec(
    "INSERT INTO question_sets VALUES
     (1, 1, 9, 'quiz', 'fixed', 'Frações', 'Números', NULL, 'Prática', 'iniciante', NULL, 10, 'public', 'published'),
     (2, 1, 9, 'quiz', 'fixed', 'Privado', 'Números', NULL, NULL, NULL, NULL, NULL, 'private', 'published'),
     (3, 2, 9, 'quiz', 'fixed', 'Matéria desativada', 'História', NULL, NULL, NULL, NULL, NULL, 'public', 'published'),
     (4, 1, 9, 'simulado', 'personalized', 'Personalizado', 'Álgebra', NULL, NULL, NULL, 2, NULL, 'public', 'published')"
);
$pdo->exec("INSERT INTO quiz_questions VALUES
    (101, 1, 'published', 'Questão publicada um'),
    (102, 1, 'published', 'Questão publicada dois'),
    (103, 1, 'draft', 'Questão de rascunho')");
$pdo->exec("INSERT INTO question_set_questions VALUES (1, 101, 1), (1, 102, 2), (1, 103, 3)");
$pdo->exec("INSERT INTO quiz_question_options VALUES (101, 'Alternativa A', 1, 1), (101, 'Alternativa B', 2, 0)");

function assert_question_export(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixed = load_printable_question_set($pdo, 1);
assert_question_export(is_array($fixed) && count($fixed['questions']) === 2, 'Somente questões publicadas do conjunto fixo devem ser exportadas.');
assert_question_export($fixed['questions'][0]['prompt'] === 'Questão publicada um', 'As questões devem respeitar a ordem do conjunto.');
assert_question_export($fixed['questions'][0]['options'] === ['Alternativa A', 'Alternativa B'], 'Alternativas devem ser incluídas sem expor o gabarito.');
assert_question_export(!array_key_exists('is_correct', $fixed['questions'][0]), 'O exportador não deve retornar a resposta correta.');
assert_question_export(load_printable_question_set($pdo, 2) === null, 'Conjuntos privados não podem ser exportados.');
assert_question_export(load_printable_question_set($pdo, 3) === null, 'Conjuntos de matérias desativadas não podem ser exportados.');
$personalized = load_printable_question_set($pdo, 4);
assert_question_export(is_array($personalized) && count($personalized['questions']) === 2, 'Conjuntos personalizados devem respeitar o limite de questões solicitado.');
assert_question_export(load_printable_question_set($pdo, 999) === null, 'Conjuntos inexistentes não podem ser exportados.');

echo "OK: exportação restrita a conjuntos públicos, questões publicadas, alternativas sem gabarito e limites definidos.\n";
