<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (
    !in_array($argv[1] ?? '', ['up', 'down'], true)
    || (($argv[1] ?? '') === 'up' && count($argv) !== 2)
    || (($argv[1] ?? '') === 'down' && (count($argv) !== 3 || ($argv[2] ?? '') !== '--confirm-data-loss'))
) {
    fwrite(STDERR, "Uso: php db/migrate_permissions.php up|down [--confirm-data-loss]\n");
    fwrite(STDERR, "O argumento --confirm-data-loss é obrigatório para down.\n");
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
$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $statement->fetchColumn() > 0;
};
$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $statement->execute(['table_name' => $table, 'index_name' => $index]);
    return (int) $statement->fetchColumn() > 0;
};
$foreignKeyExists = static function (string $table, string $foreignKey) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = "FOREIGN KEY"'
    );
    $statement->execute(['table_name' => $table, 'constraint_name' => $foreignKey]);
    return (int) $statement->fetchColumn() > 0;
};

if ($argv[1] === 'up') {
    if (!$tableExists('product_plans')) {
        $pdo->exec(
            "CREATE TABLE product_plans (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(80) NOT NULL,
                account_type ENUM('student','teacher','institution') NOT NULL,
                tier ENUM('free','premium','institutional') NOT NULL,
                institution_type ENUM('public_school','public_university','private_school','private_university') DEFAULT NULL,
                monthly_price DECIMAL(10,2) NOT NULL,
                low_income_price DECIMAL(10,2) DEFAULT NULL,
                requires_income_verification TINYINT(1) NOT NULL DEFAULT 0,
                monthly_lesson_limit SMALLINT UNSIGNED DEFAULT NULL,
                monthly_material_limit SMALLINT UNSIGNED DEFAULT NULL,
                monthly_question_bank_limit SMALLINT UNSIGNED DEFAULT NULL,
                questions_per_bank_limit SMALLINT UNSIGNED DEFAULT NULL,
                can_create_courses TINYINT(1) NOT NULL DEFAULT 0,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_product_plan_code (code),
                KEY idx_product_plan_account (account_type, tier, is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    $plans = [
        ['student-free', 'student', 'free', null, 0, null, 0, null, null, null, null, 0],
        ['student-premium', 'student', 'premium', null, 70, null, 0, null, null, null, null, 0],
        ['student-premium-low-income', 'student', 'premium', null, 70, 21, 1, null, null, null, null, 0],
        ['teacher-free', 'teacher', 'free', null, 0, null, 0, 5, 1, 1, 100, 0],
        ['teacher-premium', 'teacher', 'premium', null, 100, null, 0, null, null, null, null, 1],
        ['institution-public-school', 'institution', 'institutional', 'public_school', 5000, null, 0, null, null, null, null, 0],
        ['institution-public-university', 'institution', 'institutional', 'public_university', 7000, null, 0, null, null, null, null, 0],
        ['institution-private-school', 'institution', 'institutional', 'private_school', 10000, null, 0, null, null, null, null, 0],
        ['institution-private-university', 'institution', 'institutional', 'private_university', 15000, null, 0, null, null, null, null, 0],
    ];
    $planStatement = $pdo->prepare(
        'INSERT INTO product_plans
            (code, account_type, tier, institution_type, monthly_price, low_income_price,
             requires_income_verification, monthly_lesson_limit, monthly_material_limit,
             monthly_question_bank_limit, questions_per_bank_limit, can_create_courses)
         VALUES
            (:code, :account_type, :tier, :institution_type, :monthly_price, :low_income_price,
             :requires_income_verification, :monthly_lesson_limit, :monthly_material_limit,
             :monthly_question_bank_limit, :questions_per_bank_limit, :can_create_courses)
         ON DUPLICATE KEY UPDATE
            code = VALUES(code)'
    );
    foreach ($plans as $plan) {
        $planStatement->execute([
            'code' => $plan[0],
            'account_type' => $plan[1],
            'tier' => $plan[2],
            'institution_type' => $plan[3],
            'monthly_price' => $plan[4],
            'low_income_price' => $plan[5],
            'requires_income_verification' => $plan[6],
            'monthly_lesson_limit' => $plan[7],
            'monthly_material_limit' => $plan[8],
            'monthly_question_bank_limit' => $plan[9],
            'questions_per_bank_limit' => $plan[10],
            'can_create_courses' => $plan[11],
        ]);
    }

    if (!$columnExists('usuarios', 'professor_status')) {
        $pdo->exec(
            "ALTER TABLE usuarios ADD COLUMN professor_status
             ENUM('not_applicable','pending','approved','rejected') NOT NULL DEFAULT 'pending'"
        );
        $pdo->exec("UPDATE usuarios SET professor_status = 'pending' WHERE perfil = 'professor'");
    }
    if (!$columnExists('instituicoes', 'validation_status')) {
        $pdo->exec(
            "ALTER TABLE instituicoes ADD COLUMN validation_status
             ENUM('pending','validated','rejected') NOT NULL DEFAULT 'pending'"
        );
    }
    if (!$columnExists('biblioteca', 'owner_user_id')) {
        $pdo->exec('ALTER TABLE biblioteca ADD COLUMN owner_user_id INT(11) DEFAULT NULL AFTER instituicao_id');
    }
    if (!$indexExists('biblioteca', 'idx_biblioteca_owner_created')) {
        $pdo->exec('ALTER TABLE biblioteca ADD KEY idx_biblioteca_owner_created (owner_user_id, criado_em)');
    }
    if (!$foreignKeyExists('biblioteca', 'fk_biblioteca_owner_user')) {
        $pdo->exec(
            'ALTER TABLE biblioteca ADD CONSTRAINT fk_biblioteca_owner_user
             FOREIGN KEY (owner_user_id) REFERENCES usuarios (id) ON DELETE SET NULL'
        );
    }
    if (!$tableExists('subscriptions')) {
        $pdo->exec(
            "CREATE TABLE subscriptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) DEFAULT NULL,
                institution_id INT(11) DEFAULT NULL,
                plan_code VARCHAR(80) NOT NULL,
                status ENUM('pending','active','expired','canceled') NOT NULL DEFAULT 'pending',
                payment_reference VARCHAR(190) DEFAULT NULL,
                payment_confirmed_at DATETIME DEFAULT NULL,
                income_verified_at DATETIME DEFAULT NULL,
                starts_at DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_subscription_user_status (user_id, status, expires_at),
                KEY idx_subscription_institution_status (institution_id, status, expires_at),
                CONSTRAINT fk_subscription_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                CONSTRAINT fk_subscription_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
                CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_code) REFERENCES product_plans (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    if ($tableExists('subscriptions') && !$columnExists('subscriptions', 'income_verified_at')) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN income_verified_at DATETIME DEFAULT NULL AFTER payment_confirmed_at');
    }
    if (!$columnExists('publicacoes', 'question_bank_id')) {
        $pdo->exec('ALTER TABLE publicacoes ADD COLUMN question_bank_id BIGINT UNSIGNED DEFAULT NULL AFTER is_premium_only');
    }
    if (!$tableExists('teacher_question_banks')) {
        $pdo->exec(
            "CREATE TABLE teacher_question_banks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                owner_user_id INT(11) NOT NULL,
                month_start DATE NOT NULL,
                questions_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_teacher_question_bank_month (owner_user_id, month_start),
                CONSTRAINT fk_teacher_question_bank_owner FOREIGN KEY (owner_user_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    if ($tableExists('teacher_question_banks') && !$indexExists('publicacoes', 'idx_publicacoes_question_bank')) {
        $pdo->exec('ALTER TABLE publicacoes ADD KEY idx_publicacoes_question_bank (question_bank_id)');
    }
    if ($tableExists('teacher_question_banks') && !$foreignKeyExists('publicacoes', 'fk_publicacoes_question_bank')) {
        $pdo->exec(
            'ALTER TABLE publicacoes ADD CONSTRAINT fk_publicacoes_question_bank
             FOREIGN KEY (question_bank_id) REFERENCES teacher_question_banks (id) ON DELETE SET NULL'
        );
    }
    if (!$tableExists('admin_audit_log')) {
        $pdo->exec(
            "CREATE TABLE admin_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id INT(11) NOT NULL,
                action VARCHAR(80) NOT NULL,
                target_type VARCHAR(40) NOT NULL,
                target_id BIGINT UNSIGNED DEFAULT NULL,
                details JSON DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_admin_audit_created (created_at),
                KEY idx_admin_audit_actor (actor_user_id, created_at),
                CONSTRAINT fk_admin_audit_actor FOREIGN KEY (actor_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    if (!$tableExists('institution_members')) {
        $pdo->exec(
            "CREATE TABLE institution_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                member_type ENUM('student','teacher') NOT NULL,
                status ENUM('active','revoked') NOT NULL DEFAULT 'active',
                joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_institution_member_user (institution_id, user_id),
                KEY idx_institution_member_user_status (user_id, status),
                CONSTRAINT fk_institution_member_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
                CONSTRAINT fk_institution_member_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    if (!$tableExists('institution_invitations')) {
        $pdo->exec(
            "CREATE TABLE institution_invitations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id INT(11) NOT NULL,
                invited_user_id INT(11) NOT NULL,
                inviter_user_id INT(11) NOT NULL,
                member_type ENUM('student','teacher') NOT NULL,
                token_hash CHAR(64) NOT NULL,
                status ENUM('pending','accepted','canceled','failed','expired') NOT NULL DEFAULT 'pending',
                expires_at DATETIME NOT NULL,
                accepted_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_institution_invitation_token (token_hash),
                KEY idx_institution_invitation_status (institution_id, status, expires_at),
                KEY idx_institution_invitation_user (invited_user_id, status),
                CONSTRAINT fk_institution_invitation_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
                CONSTRAINT fk_institution_invitation_invited_user FOREIGN KEY (invited_user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                CONSTRAINT fk_institution_invitation_inviter_user FOREIGN KEY (inviter_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    if (!$tableExists('institution_audit_log')) {
        $pdo->exec(
            "CREATE TABLE institution_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id INT(11) NOT NULL,
                actor_user_id INT(11) NOT NULL,
                action VARCHAR(80) NOT NULL,
                target_user_id INT(11) DEFAULT NULL,
                details JSON DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_institution_audit_created (institution_id, created_at),
                KEY idx_institution_audit_actor (actor_user_id, created_at),
                CONSTRAINT fk_institution_audit_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE,
                CONSTRAINT fk_institution_audit_actor FOREIGN KEY (actor_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT,
                CONSTRAINT fk_institution_audit_target FOREIGN KEY (target_user_id) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    echo "Permission schema, product catalog, and institutional membership applied.\n";
    exit;
}

try {
    if ($tableExists('institution_audit_log')) {
        $pdo->exec('DROP TABLE institution_audit_log');
    }
    if ($tableExists('institution_invitations')) {
        $pdo->exec('DROP TABLE institution_invitations');
    }
    if ($tableExists('institution_members')) {
        $pdo->exec('DROP TABLE institution_members');
    }
    if ($tableExists('admin_audit_log')) {
        $pdo->exec('DROP TABLE admin_audit_log');
    }
    if ($tableExists('subscriptions')) {
        $pdo->exec('DROP TABLE subscriptions');
    }
    if ($tableExists('teacher_question_banks')) {
        if ($foreignKeyExists('publicacoes', 'fk_publicacoes_question_bank')) {
            $pdo->exec('ALTER TABLE publicacoes DROP FOREIGN KEY fk_publicacoes_question_bank');
        }
        if ($indexExists('publicacoes', 'idx_publicacoes_question_bank')) {
            $pdo->exec('ALTER TABLE publicacoes DROP INDEX idx_publicacoes_question_bank');
        }
        if ($columnExists('publicacoes', 'question_bank_id')) {
            $pdo->exec('ALTER TABLE publicacoes DROP COLUMN question_bank_id');
        }
        $pdo->exec('DROP TABLE teacher_question_banks');
    } elseif ($columnExists('publicacoes', 'question_bank_id')) {
        $pdo->exec('ALTER TABLE publicacoes DROP COLUMN question_bank_id');
    }
    if ($columnExists('biblioteca', 'owner_user_id')) {
        if ($foreignKeyExists('biblioteca', 'fk_biblioteca_owner_user')) {
            $pdo->exec('ALTER TABLE biblioteca DROP FOREIGN KEY fk_biblioteca_owner_user');
        }
        if ($indexExists('biblioteca', 'idx_biblioteca_owner_created')) {
            $pdo->exec('ALTER TABLE biblioteca DROP INDEX idx_biblioteca_owner_created');
        }
        $pdo->exec('ALTER TABLE biblioteca DROP COLUMN owner_user_id');
    }
    if ($columnExists('instituicoes', 'validation_status')) {
        $pdo->exec('ALTER TABLE instituicoes DROP COLUMN validation_status');
    }
    if ($columnExists('usuarios', 'professor_status')) {
        $pdo->exec('ALTER TABLE usuarios DROP COLUMN professor_status');
    }
    if ($tableExists('product_plans')) {
        $pdo->exec('DROP TABLE product_plans');
    }
    echo "Permission schema rolled back. User accounts and publication rows remain; product configuration, subscriptions, audit records, institutional memberships/invitations, and question-bank metadata were removed. MySQL/MariaDB DDL rollback is non-atomic.\n";
} catch (Throwable $error) {
    throw $error;
}
