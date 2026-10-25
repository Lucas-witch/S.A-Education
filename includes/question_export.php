<?php
declare(strict_types=1);

function load_printable_question_set(PDO $pdo, int $setId): ?array {
    if ($setId <= 0) {
        return null;
    }
    $setQuery = $pdo->prepare(
        'SELECT qs.id, qs.subject_id, qs.author_user_id, qs.activity_type, qs.assembly_mode,
                qs.title, qs.topic, qs.subtopic, qs.description, qs.difficulty,
                qs.requested_question_count, qs.estimated_minutes,
                s.name AS subject_name, u.username AS author_username
         FROM question_sets qs
         INNER JOIN question_subjects s ON s.id = qs.subject_id AND s.is_enabled = 1
         INNER JOIN usuarios u ON u.id = qs.author_user_id
         WHERE qs.id = :set_id AND qs.status = "published" AND qs.visibility = "public"
         LIMIT 1'
    );
    $setQuery->execute(['set_id' => $setId]);
    $set = $setQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($set)) {
        return null;
    }

    if ((string) $set['assembly_mode'] === 'fixed') {
        $questionQuery = $pdo->prepare(
            'SELECT q.id, q.prompt
             FROM question_set_questions set_question
             INNER JOIN quiz_questions q ON q.id = set_question.question_id
             WHERE set_question.question_set_id = :set_id AND q.status = "published"
             ORDER BY set_question.position, q.id
             LIMIT 100'
        );
        $questionQuery->execute(['set_id' => $setId]);
    } elseif ((string) $set['assembly_mode'] === 'personalized') {
        $count = filter_var($set['requested_question_count'], FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 100) {
            return null;
        }
        $randomOrder = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'RANDOM()' : 'RAND()';
        $questionQuery = $pdo->prepare(
            'SELECT q.id, q.prompt
             FROM quiz_questions q
             WHERE q.status = "published"
               AND (
                   q.subject_id = :primary_subject
                   OR EXISTS (
                       SELECT 1 FROM question_set_subjects set_subject
                       WHERE set_subject.question_set_id = :set_id
                         AND set_subject.subject_id = q.subject_id
                   )
               )
             ORDER BY ' . $randomOrder . '
             LIMIT ' . (int) $count
        );
        $questionQuery->execute([
            'primary_subject' => (int) $set['subject_id'],
            'set_id' => $setId,
        ]);
    } else {
        return null;
    }

    $questions = $questionQuery->fetchAll(PDO::FETCH_ASSOC);
    if ($questions === []) {
        return null;
    }
    $questionIds = array_map(static fn (array $question): int => (int) $question['id'], $questions);
    $placeholders = [];
    $parameters = [];
    foreach ($questionIds as $index => $questionId) {
        $placeholder = ':question_' . $index;
        $placeholders[] = $placeholder;
        $parameters['question_' . $index] = $questionId;
    }
    $optionsQuery = $pdo->prepare(
        'SELECT question_id, option_text, position
         FROM quiz_question_options
         WHERE question_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY question_id, position'
    );
    $optionsQuery->execute($parameters);
    $optionsByQuestion = [];
    foreach ($optionsQuery->fetchAll(PDO::FETCH_ASSOC) as $option) {
        $optionsByQuestion[(int) $option['question_id']][] = (string) $option['option_text'];
    }
    foreach ($questions as &$question) {
        $question['id'] = (int) $question['id'];
        $question['options'] = $optionsByQuestion[$question['id']] ?? [];
    }
    unset($question);
    $set['id'] = (int) $set['id'];
    $set['questions'] = $questions;

    return $set;
}
