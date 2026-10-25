<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$tables = [
    "CREATE TABLE IF NOT EXISTS question_subjects (
        id INT(11) NOT NULL AUTO_INCREMENT,
        code VARCHAR(40) NOT NULL,
        name VARCHAR(100) NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        knowledge_area VARCHAR(80) NOT NULL,
        display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_question_subjects_code (code),
        KEY idx_question_subjects_catalog (is_enabled, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS question_subject_stages (
        subject_id INT(11) NOT NULL,
        stage_code ENUM('fundamental','medio','superior') NOT NULL,
        PRIMARY KEY (subject_id, stage_code),
        CONSTRAINT fk_question_subject_stages_subject
            FOREIGN KEY (subject_id) REFERENCES question_subjects (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS question_sets (
        id INT(11) NOT NULL AUTO_INCREMENT,
        subject_id INT(11) NOT NULL,
        author_user_id INT(11) NOT NULL,
        activity_type ENUM('quiz','simulado') NOT NULL DEFAULT 'quiz',
        assembly_mode ENUM('fixed','personalized') NOT NULL DEFAULT 'fixed',
        title VARCHAR(180) NOT NULL,
        topic VARCHAR(180) NOT NULL,
        subtopic VARCHAR(180) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        difficulty ENUM('iniciante','intermediario','avancado') DEFAULT NULL,
        requested_question_count SMALLINT UNSIGNED DEFAULT NULL,
        estimated_minutes SMALLINT UNSIGNED DEFAULT NULL,
        visibility ENUM('public','private') NOT NULL DEFAULT 'private',
        status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_question_sets_catalog (subject_id, status, visibility, created_at),
        KEY idx_question_sets_author (author_user_id, created_at),
        CONSTRAINT fk_question_sets_subject
            FOREIGN KEY (subject_id) REFERENCES question_subjects (id) ON DELETE RESTRICT,
        CONSTRAINT fk_question_sets_author
            FOREIGN KEY (author_user_id) REFERENCES usuarios (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS question_set_subjects (
        question_set_id INT(11) NOT NULL,
        subject_id INT(11) NOT NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (question_set_id, subject_id),
        KEY idx_question_set_subjects_subject (subject_id, question_set_id),
        CONSTRAINT fk_question_set_subjects_set
            FOREIGN KEY (question_set_id) REFERENCES question_sets (id) ON DELETE CASCADE,
        CONSTRAINT fk_question_set_subjects_subject
            FOREIGN KEY (subject_id) REFERENCES question_subjects (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS question_set_topics (
        id INT(11) NOT NULL AUTO_INCREMENT,
        question_set_id INT(11) NOT NULL,
        topic VARCHAR(180) NOT NULL,
        subtopic VARCHAR(180) DEFAULT NULL,
        display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_question_set_topics_lookup (question_set_id, topic, subtopic),
        CONSTRAINT fk_question_set_topics_set
            FOREIGN KEY (question_set_id) REFERENCES question_sets (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT(11) NOT NULL AUTO_INCREMENT,
        subject_id INT(11) NOT NULL,
        author_user_id INT(11) NOT NULL,
        topic VARCHAR(180) NOT NULL,
        subtopic VARCHAR(180) DEFAULT NULL,
        prompt TEXT NOT NULL,
        explanation TEXT DEFAULT NULL,
        difficulty ENUM('iniciante','intermediario','avancado') DEFAULT NULL,
        status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_quiz_questions_pool (subject_id, status, difficulty),
        KEY idx_quiz_questions_author (author_user_id, created_at),
        CONSTRAINT fk_quiz_questions_subject
            FOREIGN KEY (subject_id) REFERENCES question_subjects (id) ON DELETE RESTRICT,
        CONSTRAINT fk_quiz_questions_author
            FOREIGN KEY (author_user_id) REFERENCES usuarios (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS quiz_question_options (
        id INT(11) NOT NULL AUTO_INCREMENT,
        question_id INT(11) NOT NULL,
        position SMALLINT UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_quiz_question_options_position (question_id, position),
        CONSTRAINT fk_quiz_question_options_question
            FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    "CREATE TABLE IF NOT EXISTS question_set_questions (
        question_set_id INT(11) NOT NULL,
        question_id INT(11) NOT NULL,
        position SMALLINT UNSIGNED NOT NULL,
        PRIMARY KEY (question_set_id, question_id),
        UNIQUE KEY uq_question_set_questions_position (question_set_id, position),
        CONSTRAINT fk_question_set_questions_set
            FOREIGN KEY (question_set_id) REFERENCES question_sets (id) ON DELETE CASCADE,
        CONSTRAINT fk_question_set_questions_question
            FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
];

foreach ($tables as $sql) {
    $pdo->exec($sql);
}

$imageColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = "question_subjects"
       AND COLUMN_NAME = "image_path"'
);
$imageColumn->execute();
if ((int) $imageColumn->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE question_subjects ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER name');
}

$subjects = [
    ['portugues', 'Português', 'assets/images/icons/materias/linguistica.png', 'Linguagens', 10, ['fundamental', 'medio', 'superior']],
    ['matematica', 'Matemática', 'assets/images/icons/materias/simbolo-de-constante-matematica-pi.png', 'Matemática e suas tecnologias', 20, ['fundamental', 'medio', 'superior']],
    ['ciencias', 'Ciências', 'assets/images/icons/materias/dna.png', 'Ciências da Natureza', 30, ['fundamental']],
    ['historia', 'História', 'assets/images/icons/templo-grego.png', 'Ciências Humanas', 40, ['fundamental', 'medio', 'superior']],
    ['geografia', 'Geografia', 'assets/images/icons/materias/geografia.png', 'Ciências Humanas', 50, ['fundamental', 'medio', 'superior']],
    ['ingles', 'Inglês', 'assets/images/icons/materias/eng.png', 'Linguagens', 60, ['fundamental', 'medio', 'superior']],
    ['literatura', 'Literatura', 'assets/images/icons/materias/abra-o-livro.png', 'Linguagens', 70, ['medio', 'superior']],
    ['redacao', 'Redação', 'assets/images/icons/redacao.png', 'Linguagens', 80, ['medio', 'superior']],
    ['fisica', 'Física', 'assets/images/icons/materias/newton.png', 'Ciências da Natureza', 90, ['medio', 'superior']],
    ['quimica', 'Química', 'assets/images/icons/materias/produtos-quimicos.png', 'Ciências da Natureza', 100, ['medio', 'superior']],
    ['biologia', 'Biologia', 'assets/images/icons/materias/dna.png', 'Ciências da Natureza', 110, ['medio', 'superior']],
    ['filosofia', 'Filosofia', 'assets/images/icons/materias/filosofia.png', 'Ciências Humanas', 120, ['medio', 'superior']],
    ['sociologia', 'Sociologia', 'assets/images/icons/materias/abra-o-livro.png', 'Ciências Humanas', 130, ['medio', 'superior']],
];
$insertSubject = $pdo->prepare(
    'INSERT INTO question_subjects (code, name, image_path, knowledge_area, display_order)
     VALUES (:code, :name, :image_path, :knowledge_area, :display_order)
     ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)'
);
$findSubject = $pdo->prepare('SELECT id FROM question_subjects WHERE code = :code LIMIT 1');
$insertStage = $pdo->prepare(
    'INSERT IGNORE INTO question_subject_stages (subject_id, stage_code)
     VALUES (:subject_id, :stage_code)'
);

foreach ($subjects as [$code, $name, $imagePath, $area, $order, $stages]) {
    $insertSubject->execute([
        'code' => $code,
        'name' => $name,
        'image_path' => $imagePath,
        'knowledge_area' => $area,
        'display_order' => $order,
    ]);
    $findSubject->execute(['code' => $code]);
    $subjectId = (int) $findSubject->fetchColumn();
    foreach ($stages as $stage) {
        $insertStage->execute(['subject_id' => $subjectId, 'stage_code' => $stage]);
    }
}

echo "Catálogo de matérias, conjuntos, questões e alternativas preparado; nenhum conteúdo preexistente foi removido.\n";
