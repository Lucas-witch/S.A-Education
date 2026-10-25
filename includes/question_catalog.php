<?php
declare(strict_types=1);

const QUESTION_CATALOG_STAGES = [
    'fundamental' => 'Ensino Fundamental',
    'medio' => 'Ensino Médio',
    'superior' => 'Ensino Superior',
];

function load_question_subjects(
    PDO $pdo,
    string $stage = '',
    string $area = '',
    string $search = ''
): array {
    if ($stage !== '' && !array_key_exists($stage, QUESTION_CATALOG_STAGES)) {
        throw new InvalidArgumentException('Etapa de ensino inválida.');
    }
    if (mb_strlen($area, 'UTF-8') > 80 || mb_strlen($search, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Filtro do catálogo excede o limite permitido.');
    }

    $conditions = ['s.is_enabled = 1'];
    $parameters = [];
    if ($stage !== '') {
        $conditions[] = 'EXISTS (
            SELECT 1 FROM question_subject_stages stage_filter
            WHERE stage_filter.subject_id = s.id AND stage_filter.stage_code = :stage
        )';
        $parameters['stage'] = $stage;
    }
    if ($area !== '') {
        $conditions[] = 's.knowledge_area = :area';
        $parameters['area'] = $area;
    }
    if ($search !== '') {
        $conditions[] = 's.name LIKE :search';
        $parameters['search'] = '%' . $search . '%';
    }

    $statement = $pdo->prepare(
        'SELECT s.id, s.code, s.name, s.image_path, s.knowledge_area,
                (SELECT COUNT(*) FROM question_sets qs
                 WHERE (qs.subject_id = s.id OR EXISTS (
                     SELECT 1 FROM question_set_subjects set_subject
                     WHERE set_subject.question_set_id = qs.id AND set_subject.subject_id = s.id
                 ))
                   AND qs.status = "published" AND qs.visibility = "public"
                   AND ((qs.assembly_mode = "personalized" AND qs.requested_question_count > 0)
                     OR (qs.assembly_mode = "fixed" AND EXISTS (
                         SELECT 1 FROM question_set_questions visible_questions
                         INNER JOIN quiz_questions visible_question
                           ON visible_question.id = visible_questions.question_id
                         WHERE visible_questions.question_set_id = qs.id
                           AND visible_question.status = "published"
                     )))) AS content_count
         FROM question_subjects s
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY s.display_order, s.name'
    );
    $statement->execute($parameters);
    $subjects = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($subjects === []) {
        return [];
    }

    $subjectIds = array_map(static fn (array $subject): int => (int) $subject['id'], $subjects);
    $placeholders = [];
    $stageParameters = [];
    foreach ($subjectIds as $index => $subjectId) {
        $placeholder = ':subject_' . $index;
        $placeholders[] = $placeholder;
        $stageParameters[$placeholder] = $subjectId;
    }
    $stageStatement = $pdo->prepare(
        'SELECT subject_id, stage_code FROM question_subject_stages
         WHERE subject_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY stage_code'
    );
    $stageStatement->execute($stageParameters);
    $stagesBySubject = [];
    foreach ($stageStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stageCode = (string) $row['stage_code'];
        if (isset(QUESTION_CATALOG_STAGES[$stageCode])) {
            $stagesBySubject[(int) $row['subject_id']][] = $stageCode;
        }
    }

    foreach ($subjects as &$subject) {
        $subject['id'] = (int) $subject['id'];
        $subject['content_count'] = (int) $subject['content_count'];
        $subject['stages'] = $stagesBySubject[$subject['id']] ?? [];
    }
    unset($subject);

    return $subjects;
}

function load_question_subject(PDO $pdo, string $code): ?array {
    if (preg_match('/^[a-z0-9-]{1,40}$/', $code) !== 1) {
        throw new InvalidArgumentException('Matéria inválida.');
    }

    $statement = $pdo->prepare(
        'SELECT id, code, name, image_path, knowledge_area,
                (SELECT COUNT(*) FROM question_sets qs
                 WHERE (qs.subject_id = s.id OR EXISTS (
                     SELECT 1 FROM question_set_subjects set_subject
                     WHERE set_subject.question_set_id = qs.id AND set_subject.subject_id = s.id
                 ))
                   AND qs.status = "published" AND qs.visibility = "public"
                   AND ((qs.assembly_mode = "personalized" AND qs.requested_question_count > 0)
                     OR (qs.assembly_mode = "fixed" AND EXISTS (
                         SELECT 1 FROM question_set_questions visible_questions
                         INNER JOIN quiz_questions visible_question
                           ON visible_question.id = visible_questions.question_id
                         WHERE visible_questions.question_set_id = qs.id
                           AND visible_question.status = "published"
                     )))) AS content_count
         FROM question_subjects s
         WHERE s.code = :code AND s.is_enabled = 1
         LIMIT 1'
    );
    $statement->execute(['code' => $code]);
    $subject = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subject)) {
        return null;
    }

    $stageStatement = $pdo->prepare(
        'SELECT stage_code FROM question_subject_stages
         WHERE subject_id = :subject_id ORDER BY stage_code'
    );
    $stageStatement->execute(['subject_id' => (int) $subject['id']]);
    $subject['id'] = (int) $subject['id'];
    $subject['content_count'] = (int) $subject['content_count'];
    $subject['stages'] = array_values(array_filter(
        array_map('strval', $stageStatement->fetchAll(PDO::FETCH_COLUMN)),
        static fn (string $stageCode): bool => isset(QUESTION_CATALOG_STAGES[$stageCode])
    ));

    return $subject;
}

function load_question_catalog_filter_values(
    PDO $pdo,
    int $subjectId = 0,
    string $stage = '',
    string $area = ''
): array {
    if (
        $subjectId < 0
        || ($stage !== '' && !array_key_exists($stage, QUESTION_CATALOG_STAGES))
        || ($area !== '' && mb_strlen($area, 'UTF-8') > 80)
    ) {
        throw new InvalidArgumentException('Filtros do catálogo inválidos.');
    }

    $conditions = [
        'qs.status = "published"',
        'qs.visibility = "public"',
        '((qs.assembly_mode = "personalized" AND qs.requested_question_count > 0)
          OR (qs.assembly_mode = "fixed" AND EXISTS (
              SELECT 1 FROM question_set_questions visible_questions
              INNER JOIN quiz_questions visible_question
                ON visible_question.id = visible_questions.question_id
              WHERE visible_questions.question_set_id = qs.id
                AND visible_question.status = "published"
          )))',
    ];
    $parameters = [];
    if ($subjectId > 0) {
        $conditions[] = '(qs.subject_id = :primary_subject_id OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            WHERE set_subject.question_set_id = qs.id AND set_subject.subject_id = :linked_subject_id
        ))';
        $parameters['primary_subject_id'] = $subjectId;
        $parameters['linked_subject_id'] = $subjectId;
    }
    if ($stage !== '') {
        $conditions[] = '(EXISTS (
            SELECT 1 FROM question_subject_stages stage_filter
            WHERE stage_filter.subject_id = qs.subject_id
              AND stage_filter.stage_code = :stage
        ) OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            INNER JOIN question_subject_stages linked_stage
              ON linked_stage.subject_id = set_subject.subject_id
            WHERE set_subject.question_set_id = qs.id
              AND linked_stage.stage_code = :linked_stage
        ))';
        $parameters['stage'] = $stage;
        $parameters['linked_stage'] = $stage;
    }
    if ($area !== '') {
        $conditions[] = '(EXISTS (
            SELECT 1 FROM question_subjects area_filter
            WHERE area_filter.id = qs.subject_id AND area_filter.knowledge_area = :primary_area
        ) OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            INNER JOIN question_subjects linked_area ON linked_area.id = set_subject.subject_id
            WHERE set_subject.question_set_id = qs.id
              AND linked_area.knowledge_area = :linked_area
        ))';
        $parameters['primary_area'] = $area;
        $parameters['linked_area'] = $area;
    }
    $where = implode(' AND ', $conditions);

    $topicsStatement = $pdo->prepare(
        'SELECT DISTINCT qs.topic
         FROM question_sets qs
         WHERE ' . $where . '
         ORDER BY qs.topic'
    );
    $topicsStatement->execute($parameters);
    $setTopicsStatement = $pdo->prepare(
        'SELECT DISTINCT set_topic.topic
         FROM question_set_topics set_topic
         INNER JOIN question_sets qs ON qs.id = set_topic.question_set_id
         WHERE ' . $where . '
         ORDER BY set_topic.topic'
    );
    $setTopicsStatement->execute($parameters);
    $subtopicsStatement = $pdo->prepare(
        'SELECT DISTINCT qs.subtopic
         FROM question_sets qs
         WHERE ' . $where . ' AND qs.subtopic IS NOT NULL AND qs.subtopic <> ""
         ORDER BY qs.subtopic'
    );
    $subtopicsStatement->execute($parameters);
    $setSubtopicsStatement = $pdo->prepare(
        'SELECT DISTINCT set_topic.subtopic
         FROM question_set_topics set_topic
         INNER JOIN question_sets qs ON qs.id = set_topic.question_set_id
         WHERE ' . $where . ' AND set_topic.subtopic IS NOT NULL AND set_topic.subtopic <> ""
         ORDER BY set_topic.subtopic'
    );
    $setSubtopicsStatement->execute($parameters);

    $topics = array_merge(
        array_map('strval', $topicsStatement->fetchAll(PDO::FETCH_COLUMN)),
        array_map('strval', $setTopicsStatement->fetchAll(PDO::FETCH_COLUMN))
    );
    $subtopics = array_merge(
        array_map('strval', $subtopicsStatement->fetchAll(PDO::FETCH_COLUMN)),
        array_map('strval', $setSubtopicsStatement->fetchAll(PDO::FETCH_COLUMN))
    );
    $topics = array_values(array_unique(array_filter($topics, static fn (string $value): bool => $value !== '')));
    $subtopics = array_values(array_unique(array_filter($subtopics, static fn (string $value): bool => $value !== '')));
    sort($topics, SORT_NATURAL | SORT_FLAG_CASE);
    sort($subtopics, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'topics' => $topics,
        'subtopics' => $subtopics,
    ];
}

function load_public_question_sets(
    PDO $pdo,
    array $filters = [],
    int $page = 1,
    int $pageSize = 18
): array {
    $subjectId = (int) ($filters['subject_id'] ?? 0);
    $stage = (string) ($filters['stage'] ?? '');
    $topic = (string) ($filters['topic'] ?? '');
    $subtopic = (string) ($filters['subtopic'] ?? '');
    $difficulty = (string) ($filters['difficulty'] ?? '');
    $activityType = (string) ($filters['activity_type'] ?? '');
    $search = (string) ($filters['search'] ?? '');
    $area = (string) ($filters['area'] ?? '');

    if (
        $subjectId < 0
        || ($stage !== '' && !array_key_exists($stage, QUESTION_CATALOG_STAGES))
        || ($difficulty !== '' && !in_array($difficulty, ['iniciante', 'intermediario', 'avancado'], true))
        || ($activityType !== '' && !in_array($activityType, ['quiz', 'simulado'], true))
        || mb_strlen($topic, 'UTF-8') > 180
        || mb_strlen($subtopic, 'UTF-8') > 180
        || mb_strlen($search, 'UTF-8') > 100
        || mb_strlen($area, 'UTF-8') > 80
        || $page < 1
        || $pageSize < 1
        || $pageSize > 50
    ) {
        throw new InvalidArgumentException('Filtros do catálogo inválidos.');
    }

    $conditions = [
        'qs.status = "published"',
        'qs.visibility = "public"',
        's.is_enabled = 1',
        '((qs.assembly_mode = "personalized" AND qs.requested_question_count > 0)
          OR (qs.assembly_mode = "fixed" AND EXISTS (
              SELECT 1 FROM question_set_questions visible_questions
              INNER JOIN quiz_questions visible_question
                ON visible_question.id = visible_questions.question_id
              WHERE visible_questions.question_set_id = qs.id
                AND visible_question.status = "published"
          )))',
    ];
    $parameters = [];
    if ($subjectId > 0) {
        $conditions[] = '(qs.subject_id = :primary_subject_id OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            WHERE set_subject.question_set_id = qs.id AND set_subject.subject_id = :linked_subject_id
        ))';
        $parameters['primary_subject_id'] = $subjectId;
        $parameters['linked_subject_id'] = $subjectId;
    }
    if ($stage !== '') {
        $conditions[] = '(EXISTS (
            SELECT 1 FROM question_subject_stages stage_filter
            WHERE stage_filter.subject_id = qs.subject_id
              AND stage_filter.stage_code = :stage
        ) OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            INNER JOIN question_subject_stages linked_stage
              ON linked_stage.subject_id = set_subject.subject_id
            WHERE set_subject.question_set_id = qs.id
              AND linked_stage.stage_code = :linked_stage
        ))';
        $parameters['stage'] = $stage;
        $parameters['linked_stage'] = $stage;
    }
    if ($area !== '') {
        $conditions[] = '(s.knowledge_area = :primary_area OR EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            INNER JOIN question_subjects linked_area ON linked_area.id = set_subject.subject_id
            WHERE set_subject.question_set_id = qs.id
              AND linked_area.knowledge_area = :linked_area
        ))';
        $parameters['primary_area'] = $area;
        $parameters['linked_area'] = $area;
    }
    if ($topic !== '') {
        $conditions[] = '(qs.topic = :topic OR EXISTS (
            SELECT 1 FROM question_set_topics set_topic
            WHERE set_topic.question_set_id = qs.id AND set_topic.topic = :criteria_topic
        ))';
        $parameters['topic'] = $topic;
        $parameters['criteria_topic'] = $topic;
    }
    if ($subtopic !== '') {
        $conditions[] = '(qs.subtopic = :subtopic OR EXISTS (
            SELECT 1 FROM question_set_topics set_topic
            WHERE set_topic.question_set_id = qs.id AND set_topic.subtopic = :criteria_subtopic
        ))';
        $parameters['subtopic'] = $subtopic;
        $parameters['criteria_subtopic'] = $subtopic;
    }
    if ($difficulty !== '') {
        $conditions[] = 'qs.difficulty = :difficulty';
        $parameters['difficulty'] = $difficulty;
    }
    if ($activityType !== '') {
        $conditions[] = 'qs.activity_type = :activity_type';
        $parameters['activity_type'] = $activityType;
    }
    if ($search !== '') {
        $searchConditions = [];
        foreach (['title', 'topic', 'subtopic', 'description'] as $index => $column) {
            $placeholder = ':search_' . $index;
            $searchConditions[] = 'qs.' . $column . ' LIKE ' . $placeholder;
            $parameters[$placeholder] = '%' . $search . '%';
        }
        foreach (['s.name' => 'subject_name', 'u.username' => 'author_username'] as $column => $placeholderName) {
            $placeholder = ':search_' . $placeholderName;
            $searchConditions[] = $column . ' LIKE ' . $placeholder;
            $parameters[$placeholder] = '%' . $search . '%';
        }
        $searchConditions[] = 'EXISTS (
            SELECT 1 FROM question_set_subjects set_subject
            INNER JOIN question_subjects linked_subject ON linked_subject.id = set_subject.subject_id
            WHERE set_subject.question_set_id = qs.id
              AND linked_subject.name LIKE :search_linked_subject
        )';
        $parameters[':search_linked_subject'] = '%' . $search . '%';
        $searchConditions[] = 'EXISTS (
            SELECT 1 FROM question_set_topics search_topic
            WHERE search_topic.question_set_id = qs.id
              AND (search_topic.topic LIKE :search_topic OR search_topic.subtopic LIKE :search_subtopic)
        )';
        $parameters[':search_topic'] = '%' . $search . '%';
        $parameters[':search_subtopic'] = '%' . $search . '%';
        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }
    $where = implode(' AND ', $conditions);

    $countStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM question_sets qs
         INNER JOIN question_subjects s ON s.id = qs.subject_id
         INNER JOIN usuarios u ON u.id = qs.author_user_id
         WHERE ' . $where
    );
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);

    $statement = $pdo->prepare(
        'SELECT qs.id, qs.subject_id, qs.title, qs.topic, qs.subtopic, qs.description,
                qs.activity_type, qs.difficulty, qs.assembly_mode,
                qs.requested_question_count, qs.estimated_minutes,
                s.code AS subject_code, s.name AS subject_name,
                s.image_path AS subject_image_path,
                u.username AS author_username,
                CASE WHEN qs.assembly_mode = "personalized"
                     THEN qs.requested_question_count
                     ELSE (SELECT COUNT(*)
                           FROM question_set_questions qsq
                           INNER JOIN quiz_questions q ON q.id = qsq.question_id
                           WHERE qsq.question_set_id = qs.id AND q.status = "published")
                END AS question_count
         FROM question_sets qs
         INNER JOIN question_subjects s ON s.id = qs.subject_id AND s.is_enabled = 1
         INNER JOIN usuarios u ON u.id = qs.author_user_id
         WHERE ' . $where . '
         ORDER BY qs.created_at DESC, qs.id DESC
         LIMIT :page_size OFFSET :page_offset'
    );
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . ltrim((string) $name, ':'), $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $statement->bindValue(':page_offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
    $statement->execute();
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['subject_id'] = (int) $item['subject_id'];
        $item['question_count'] = (int) $item['question_count'];
        $item['estimated_minutes'] = $item['estimated_minutes'] !== null
            ? (int) $item['estimated_minutes']
            : null;
    }
    unset($item);

    if ($items !== []) {
        $setIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $setPlaceholders = [];
        $linkedSetPlaceholders = [];
        $setParameters = [];
        foreach ($setIds as $index => $setId) {
            $placeholder = ':set_' . $index;
            $linkedPlaceholder = ':linked_set_' . $index;
            $setPlaceholders[] = $placeholder;
            $linkedSetPlaceholders[] = $linkedPlaceholder;
            $setParameters[$placeholder] = $setId;
            $setParameters[$linkedPlaceholder] = $setId;
        }
        $subjectsStatement = $pdo->prepare(
            'SELECT qs.id AS question_set_id, s.id, s.name, s.image_path
             FROM question_sets qs
             INNER JOIN question_subjects s ON s.id = qs.subject_id AND s.is_enabled = 1
             WHERE qs.id IN (' . implode(', ', $setPlaceholders) . ')
             UNION
             SELECT set_subject.question_set_id, s.id, s.name, s.image_path
             FROM question_set_subjects set_subject
             INNER JOIN question_subjects s ON s.id = set_subject.subject_id AND s.is_enabled = 1
             WHERE set_subject.question_set_id IN (' . implode(', ', $linkedSetPlaceholders) . ')
             ORDER BY question_set_id, name'
        );
        $subjectsStatement->execute($setParameters);
        $subjectsBySet = [];
        foreach ($subjectsStatement->fetchAll(PDO::FETCH_ASSOC) as $subject) {
            $subjectsBySet[(int) $subject['question_set_id']][] = [
                'id' => (int) $subject['id'],
                'name' => (string) $subject['name'],
                'image_path' => (string) $subject['image_path'],
            ];
        }
        foreach ($items as &$item) {
            $item['subjects'] = $subjectsBySet[$item['id']] ?? [];
            if ($item['subjects'] === []) {
                error_log('[S.A Education][question_catalog_subject] Conjunto público sem matéria associada: ' . $item['id']);
            }
        }
        unset($item);
    }

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'page_size' => $pageSize,
    ];
}
