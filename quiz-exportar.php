<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/question_export.php';

require_auth();
$setId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$set = $setId !== false && $setId !== null
    ? load_printable_question_set($pdo, (int) $setId)
    : null;
if ($set === null) {
    http_response_code(404);
    exit('Conjunto de exercícios não encontrado ou indisponível para exportação.');
}
$difficultyLabels = [
    'iniciante' => 'Iniciante',
    'intermediario' => 'Intermediário',
    'avancado' => 'Avançado',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc((string) $set['title']) ?> | Exercícios S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/question-export.css">
</head>
<body class="exercise-export">
<main class="exercise-paper">
    <nav class="exercise-actions" aria-label="Ações do documento">
        <a href="quiz.php">Voltar às questões</a>
        <button type="button" onclick="window.print()">Imprimir ou salvar como PDF</button>
    </nav>
    <header class="exercise-header">
        <p class="exercise-brand">S.A Education · Caderno de exercícios</p>
        <h1><?= esc((string) $set['title']) ?></h1>
        <p><?= esc((string) $set['subject_name']) ?> · <?= esc((string) $set['topic']) ?><?= !empty($set['subtopic']) ? ' · ' . esc((string) $set['subtopic']) : '' ?></p>
        <?php if (!empty($set['description'])): ?><p><?= nl2br(esc((string) $set['description'])) ?></p><?php endif; ?>
        <div class="exercise-identification">
            <span>Nome: __________________________________________</span>
            <span>Data: ____ / ____ / ______</span>
        </div>
        <p class="exercise-details">
            <?= count($set['questions']) ?> questões
            <?php if (isset($difficultyLabels[(string) $set['difficulty']])): ?> · <?= esc($difficultyLabels[(string) $set['difficulty']]) ?><?php endif; ?>
            · Material público por @<?= esc((string) $set['author_username']) ?>
        </p>
    </header>
    <ol class="exercise-list">
        <?php foreach ($set['questions'] as $index => $question): ?>
            <li class="exercise-question">
                <p class="exercise-prompt"><?= nl2br(esc((string) $question['prompt'])) ?></p>
                <?php if ($question['options'] !== []): ?>
                    <ol class="exercise-options" type="A">
                        <?php foreach ($question['options'] as $option): ?>
                            <li><?= nl2br(esc($option)) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <div class="exercise-answer-lines" aria-label="Espaço para resolução">
                    <span></span><span></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
    <footer class="exercise-footer">
        As alternativas corretas e as explicações não são incluídas neste caderno. Use a opção de impressão do navegador e escolha “Salvar como PDF”.
    </footer>
</main>
</body>
</html>
