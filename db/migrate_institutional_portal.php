<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$usage = "Uso: php db/migrate_institutional_portal.php up|down [--confirm-data-loss]\n";
$action = $argv[1] ?? '';
if (!in_array($action, ['up', 'down'], true) || ($action === 'down' && ($argv[2] ?? '') !== '--confirm-data-loss')) {
    fwrite(STDERR, $usage);
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$tableExists = static function (string $table) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int) $statement->fetchColumn() > 0;
};

$registryExists = $tableExists('institution_portal_migration_objects');
if ($action === 'up' && !$registryExists) {
    $pdo->exec(
        "CREATE TABLE institution_portal_migration_objects (
            object_name VARCHAR(64) NOT NULL PRIMARY KEY,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}
if ($action === 'down' && !$registryExists) {
    echo "Institutional academic portal migration is not installed; nothing to roll back.\n";
    exit;
}

$objects = [
    'institution_academic_units' => "CREATE TABLE institution_academic_units (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        parent_unit_id BIGINT UNSIGNED DEFAULT NULL,
        unit_type ENUM('campus','faculty','department','area') NOT NULL,
        name VARCHAR(180) NOT NULL,
        code VARCHAR(60) DEFAULT NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_academic_unit_scope (id, institution_id),
        UNIQUE KEY uq_academic_unit_code (institution_id, code),
        KEY idx_academic_unit_parent (institution_id, parent_unit_id, status),
        CONSTRAINT fk_academic_unit_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
        CONSTRAINT fk_academic_unit_parent FOREIGN KEY (parent_unit_id, institution_id)
            REFERENCES institution_academic_units (id, institution_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_programs' => "CREATE TABLE institution_programs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        academic_unit_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(180) NOT NULL,
        code VARCHAR(60) DEFAULT NULL,
        degree_level ENUM('fundamental','medio','undergraduate','graduate','continuing') NOT NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_program_scope (id, institution_id),
        UNIQUE KEY uq_institution_program_code (institution_id, code),
        KEY idx_institution_program_status (institution_id, status, name),
        CONSTRAINT fk_institution_program_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_program_unit FOREIGN KEY (academic_unit_id, institution_id)
            REFERENCES institution_academic_units (id, institution_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_terms' => "CREATE TABLE institution_terms (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        name VARCHAR(100) NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        period_code VARCHAR(40) DEFAULT NULL,
        starts_on DATE DEFAULT NULL,
        ends_on DATE DEFAULT NULL,
        status ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_term_scope (id, institution_id),
        UNIQUE KEY uq_institution_term_name (institution_id, year, name),
        KEY idx_institution_term_status (institution_id, status, year),
        CONSTRAINT fk_institution_term_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_classes' => "CREATE TABLE institution_classes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        program_id BIGINT UNSIGNED NOT NULL,
        term_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        stage_label VARCHAR(100) DEFAULT NULL,
        grade_label VARCHAR(100) DEFAULT NULL,
        section VARCHAR(60) DEFAULT NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_class_scope (id, institution_id),
        KEY idx_institution_class_term (institution_id, term_id, status),
        KEY idx_institution_class_program (institution_id, program_id, status),
        CONSTRAINT fk_institution_class_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_class_program FOREIGN KEY (program_id, institution_id)
            REFERENCES institution_programs (id, institution_id) ON DELETE RESTRICT,
        CONSTRAINT fk_institution_class_term FOREIGN KEY (term_id, institution_id)
            REFERENCES institution_terms (id, institution_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_subjects' => "CREATE TABLE institution_subjects (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        name VARCHAR(150) NOT NULL,
        knowledge_area VARCHAR(120) DEFAULT NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_subject_scope (id, institution_id),
        UNIQUE KEY uq_institution_subject_name (institution_id, name),
        CONSTRAINT fk_institution_subject_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_enrollments' => "CREATE TABLE institution_enrollments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        class_id BIGINT UNSIGNED NOT NULL,
        student_user_id INT(11) NOT NULL,
        status ENUM('active','withdrawn','completed') NOT NULL DEFAULT 'active',
        enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_enrollment_scope (id, institution_id),
        UNIQUE KEY uq_institution_enrollment_student (class_id, student_user_id),
        KEY idx_institution_enrollment_student (institution_id, student_user_id, status),
        CONSTRAINT fk_institution_enrollment_class FOREIGN KEY (class_id, institution_id)
            REFERENCES institution_classes (id, institution_id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_enrollment_student FOREIGN KEY (student_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_class_subjects' => "CREATE TABLE institution_class_subjects (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        class_id BIGINT UNSIGNED NOT NULL,
        subject_id BIGINT UNSIGNED NOT NULL,
        teacher_user_id INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_class_subject_scope (id, institution_id),
        UNIQUE KEY uq_institution_class_subject (class_id, subject_id),
        KEY idx_institution_class_subject_teacher (institution_id, teacher_user_id),
        CONSTRAINT fk_institution_class_subject_class FOREIGN KEY (class_id, institution_id)
            REFERENCES institution_classes (id, institution_id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_class_subject_subject FOREIGN KEY (subject_id, institution_id)
            REFERENCES institution_subjects (id, institution_id) ON DELETE RESTRICT,
        CONSTRAINT fk_institution_class_subject_teacher FOREIGN KEY (teacher_user_id) REFERENCES usuarios (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_learning_resources' => "CREATE TABLE institution_learning_resources (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        class_subject_id BIGINT UNSIGNED NOT NULL,
        resource_type ENUM('publication','library_material') NOT NULL,
        resource_id BIGINT UNSIGNED NOT NULL,
        assigned_by INT(11) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_learning_resource (class_subject_id, resource_type, resource_id),
        KEY idx_institution_learning_resource_scope (institution_id, resource_type, resource_id),
        CONSTRAINT fk_learning_resource_class_subject FOREIGN KEY (class_subject_id, institution_id)
            REFERENCES institution_class_subjects (id, institution_id) ON DELETE CASCADE,
        CONSTRAINT fk_learning_resource_assigner FOREIGN KEY (assigned_by) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_assessments' => "CREATE TABLE institution_assessments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        class_subject_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        assessment_type ENUM('activity','assignment','exam','quiz') NOT NULL DEFAULT 'activity',
        description TEXT DEFAULT NULL,
        max_score DECIMAL(7,2) NOT NULL,
        opens_at DATETIME DEFAULT NULL,
        due_at DATETIME DEFAULT NULL,
        status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
        created_by INT(11) NOT NULL,
        published_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_assessment_scope (id, institution_id),
        KEY idx_institution_assessment_subject (institution_id, class_subject_id, status),
        CONSTRAINT fk_institution_assessment_subject FOREIGN KEY (class_subject_id, institution_id)
            REFERENCES institution_class_subjects (id, institution_id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_assessment_creator FOREIGN KEY (created_by) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_assessment_results' => "CREATE TABLE institution_assessment_results (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        assessment_id BIGINT UNSIGNED NOT NULL,
        student_user_id INT(11) NOT NULL,
        score DECIMAL(7,2) NOT NULL,
        feedback TEXT DEFAULT NULL,
        published_at DATETIME DEFAULT NULL,
        recorded_by INT(11) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_institution_assessment_result (assessment_id, student_user_id),
        KEY idx_institution_result_student (institution_id, student_user_id, published_at),
        CONSTRAINT fk_institution_result_assessment FOREIGN KEY (assessment_id, institution_id)
            REFERENCES institution_assessments (id, institution_id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_result_student FOREIGN KEY (student_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT,
        CONSTRAINT fk_institution_result_recorder FOREIGN KEY (recorded_by) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_academic_audit' => "CREATE TABLE institution_academic_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_id INT(11) NOT NULL,
        actor_user_id INT(11) NOT NULL,
        action VARCHAR(80) NOT NULL,
        target_type VARCHAR(60) NOT NULL,
        target_id BIGINT UNSIGNED DEFAULT NULL,
        details JSON DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_institution_academic_audit_time (institution_id, created_at),
        KEY idx_institution_academic_audit_actor (institution_id, actor_user_id, created_at),
        CONSTRAINT fk_institution_academic_audit_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_academic_audit_actor FOREIGN KEY (actor_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    'institution_portal_suspensions' => "CREATE TABLE institution_portal_suspensions (
        institution_id INT(11) NOT NULL,
        suspended_by INT(11) NOT NULL,
        reason VARCHAR(1000) NOT NULL,
        suspended_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (institution_id),
        CONSTRAINT fk_institution_portal_suspension_institution
            FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
        CONSTRAINT fk_institution_portal_suspension_actor
            FOREIGN KEY (suspended_by) REFERENCES usuarios (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
];

if ($action === 'up') {
    $createdTables = $pdo->prepare(
        'SELECT object_name FROM institution_portal_migration_objects'
    );
    $createdTables->execute();
    $owned = array_fill_keys($createdTables->fetchAll(PDO::FETCH_COLUMN), true);
    foreach ($objects as $table => $sql) {
        if ($tableExists($table)) {
            if (!isset($owned[$table])) {
                throw new RuntimeException(
                    'A tabela ' . $table . ' já existe e não pertence a esta migração; revise o esquema antes de continuar.'
                );
            }
            continue;
        }
        $pdo->exec($sql);
        $record = $pdo->prepare(
            'INSERT INTO institution_portal_migration_objects (object_name) VALUES (:object_name)'
        );
        $record->execute(['object_name' => $table]);
    }
    echo "Institutional academic portal schema applied.\n";
    exit;
}

$ownedQuery = $pdo->query(
    'SELECT object_name FROM institution_portal_migration_objects ORDER BY object_name'
);
$ownedTables = array_flip($ownedQuery->fetchAll(PDO::FETCH_COLUMN));
$dropOrder = array_reverse(array_keys($objects));
foreach ($dropOrder as $table) {
    if (isset($ownedTables[$table]) && $tableExists($table)) {
        $pdo->exec('DROP TABLE `' . $table . '`');
    }
}
$pdo->exec('DROP TABLE institution_portal_migration_objects');
echo "Institutional academic portal schema rolled back. Academic records created by this migration were removed.\n";
