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
    fwrite(STDERR, "Uso: php db/migrate_digital_library.php up|down [--confirm-data-loss]\n");
    fwrite(STDERR, "O argumento --confirm-data-loss é obrigatório para down.\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $statement->fetchColumn() > 0;
};
$tableExists = static function (string $table) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int) $statement->fetchColumn() > 0;
};
$registryTable = 'digital_library_migration_objects';
$registryMarker = '__digital_library_migration_registry__';
$recordOwnedObject = static function (string $type, string $name) use ($pdo): void {
    $statement = $pdo->prepare(
        'INSERT INTO digital_library_migration_objects (object_type, object_name)
         VALUES (:object_type, :object_name)'
    );
    $statement->execute(['object_type' => $type, 'object_name' => $name]);
};
$columns = [
    'area_conhecimento' => 'VARCHAR(120) DEFAULT NULL AFTER materia',
    'etapa_ensino' => 'VARCHAR(80) DEFAULT NULL AFTER area_conhecimento',
    'idioma' => 'VARCHAR(40) DEFAULT NULL AFTER etapa_ensino',
    'categoria_material' => 'VARCHAR(80) DEFAULT NULL AFTER idioma',
    'palavras_chave' => 'TEXT DEFAULT NULL AFTER idioma',
    'texto_html' => 'MEDIUMTEXT DEFAULT NULL AFTER palavras_chave',
    'referencia_bibliografica' => 'TEXT DEFAULT NULL AFTER texto_html',
    'fonte_origem' => 'VARCHAR(500) DEFAULT NULL AFTER referencia_bibliografica',
    'direitos_status' => "ENUM('unknown','original','licensed','public_domain','institution_authorized') NOT NULL DEFAULT 'unknown' AFTER fonte_origem",
    'direitos_evidencia' => 'TEXT DEFAULT NULL AFTER direitos_status',
    'direitos_declarados' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER direitos_evidencia',
    'revisao_status' => "ENUM('draft','pending_review','approved','rejected','archived') NOT NULL DEFAULT 'pending_review' AFTER direitos_declarados",
    'revisao_motivo' => 'TEXT DEFAULT NULL AFTER revisao_status',
    'storage_key' => 'VARCHAR(255) DEFAULT NULL AFTER revisao_motivo',
    'file_mime' => 'VARCHAR(100) DEFAULT NULL AFTER storage_key',
    'file_size' => 'BIGINT UNSIGNED DEFAULT NULL AFTER file_mime',
    'file_sha256' => 'CHAR(64) DEFAULT NULL AFTER file_size',
];

if ($argv[1] === 'up') {
    if (!$tableExists($registryTable)) {
        $pdo->exec(
            "CREATE TABLE digital_library_migration_objects (
                object_type ENUM('column','table') NOT NULL,
                object_name VARCHAR(128) NOT NULL,
                PRIMARY KEY (object_type, object_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $recordOwnedObject('table', $registryMarker);
    } else {
        $registryCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM digital_library_migration_objects
             WHERE object_type = "table" AND object_name = :marker'
        );
        $registryCheck->execute(['marker' => $registryMarker]);
        if ((int) $registryCheck->fetchColumn() !== 1) {
            throw new RuntimeException('O registro de propriedade da Biblioteca já existe sem o marcador esperado; nenhuma alteração foi aplicada.');
        }
    }

    $revisionStatusAdded = false;
    foreach ($columns as $column => $definition) {
        if (!$columnExists('biblioteca', $column)) {
            $pdo->exec('ALTER TABLE biblioteca ADD COLUMN `' . $column . '` ' . $definition);
            $recordOwnedObject('column', 'biblioteca.' . $column);
            if ($column === 'revisao_status') {
                $revisionStatusAdded = true;
            }
        }
    }
    if ($revisionStatusAdded) {
        $pdo->exec(
            'UPDATE biblioteca
             SET revisao_status = CASE
                 WHEN status = "ativo" THEN "approved"
                 WHEN status = "indisponivel" THEN "archived"
                 WHEN status = "em_revisao" THEN "pending_review"
                 ELSE revisao_status
             END
             WHERE revisao_status = "pending_review"'
        );
    }
    $tables = [
        'library_reading_progress' => "CREATE TABLE library_reading_progress (
            user_id INT(11) NOT NULL,
            material_id INT(11) NOT NULL,
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            position_anchor VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, material_id),
            CONSTRAINT fk_library_progress_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_library_progress_material FOREIGN KEY (material_id) REFERENCES biblioteca (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'library_annotations' => "CREATE TABLE library_annotations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            material_id INT(11) NOT NULL,
            annotation_type ENUM('note','highlight') NOT NULL DEFAULT 'note',
            anchor_text VARCHAR(1000) DEFAULT NULL,
            anchor_start INT UNSIGNED DEFAULT NULL,
            anchor_end INT UNSIGNED DEFAULT NULL,
            note_text TEXT NOT NULL,
            color VARCHAR(16) NOT NULL DEFAULT 'yellow',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_library_annotation_user_material (user_id, material_id, updated_at),
            CONSTRAINT fk_library_annotation_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_library_annotation_material FOREIGN KEY (material_id) REFERENCES biblioteca (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'library_access_events' => "CREATE TABLE library_access_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            material_id INT(11) NOT NULL,
            event_type ENUM('open','exercise_export') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_library_access_user_material (user_id, material_id, created_at),
            CONSTRAINT fk_library_access_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_library_access_material FOREIGN KEY (material_id) REFERENCES biblioteca (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'library_copies' => "CREATE TABLE library_copies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            material_id INT(11) NOT NULL,
            institution_id INT(11) NOT NULL,
            copy_code VARCHAR(100) NOT NULL,
            format ENUM('physical','digital') NOT NULL,
            status ENUM('available','on_loan','maintenance','unavailable') NOT NULL DEFAULT 'available',
            distribution_authorized TINYINT(1) NOT NULL DEFAULT 0,
            access_rules TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_library_copy_institution_code (institution_id, copy_code),
            KEY idx_library_copy_material_status (material_id, status),
            CONSTRAINT fk_library_copy_material FOREIGN KEY (material_id) REFERENCES biblioteca (id) ON DELETE CASCADE,
            CONSTRAINT fk_library_copy_institution FOREIGN KEY (institution_id) REFERENCES instituicoes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'library_loans' => "CREATE TABLE library_loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            copy_id BIGINT UNSIGNED NOT NULL,
            user_id INT(11) NOT NULL,
            responsible_user_id INT(11) NOT NULL,
            status ENUM('active','returned','overdue','canceled') NOT NULL DEFAULT 'active',
            borrowed_at DATETIME NOT NULL,
            due_at DATETIME NOT NULL,
            returned_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_library_loan_user_status (user_id, status, due_at),
            KEY idx_library_loan_copy_status (copy_id, status),
            CONSTRAINT fk_library_loan_copy FOREIGN KEY (copy_id) REFERENCES library_copies (id) ON DELETE RESTRICT,
            CONSTRAINT fk_library_loan_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_library_loan_responsible FOREIGN KEY (responsible_user_id) REFERENCES usuarios (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];
    foreach ($tables as $table => $sql) {
        if (!$tableExists($table)) {
            $pdo->exec($sql);
            $recordOwnedObject('table', $table);
        }
    }
    $additionalColumns = [
        'library_reading_progress' => [
            'font_size' => "ENUM('small','standard','large','extra-large') NOT NULL DEFAULT 'standard'",
            'line_spacing' => "ENUM('normal','wide','compact') NOT NULL DEFAULT 'normal'",
            'reading_mode' => "ENUM('light','warm','dark') NOT NULL DEFAULT 'light'",
        ],
        'library_copies' => [
            'authorization_evidence' => 'TEXT DEFAULT NULL',
        ],
    ];
    foreach ($additionalColumns as $table => $tableColumns) {
        foreach ($tableColumns as $column => $definition) {
            if (!$columnExists($table, $column)) {
                $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
                $recordOwnedObject('column', $table . '.' . $column);
            }
        }
    }
    $copyStatus = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "library_copies" AND COLUMN_NAME = "status"'
    );
    $copyStatus->execute();
    $copyStatusType = (string) $copyStatus->fetchColumn();
    if ($copyStatusType !== '' && !str_contains($copyStatusType, "'requested'")) {
        $pdo->exec(
            "ALTER TABLE library_copies
             MODIFY status ENUM('available','requested','on_loan','maintenance','unavailable') NOT NULL DEFAULT 'available'"
        );
    }
    $loanStatus = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "library_loans" AND COLUMN_NAME = "status"'
    );
    $loanStatus->execute();
    $loanStatusType = (string) $loanStatus->fetchColumn();
    if ($loanStatusType !== '' && !str_contains($loanStatusType, "'requested'")) {
        $pdo->exec(
            "ALTER TABLE library_loans
             MODIFY status ENUM('requested','active','returned','overdue','canceled') NOT NULL DEFAULT 'active'"
        );
    }
    foreach (['borrowed_at', 'due_at'] as $dateColumn) {
        $dateColumnInfo = $pdo->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "library_loans" AND COLUMN_NAME = :column_name'
        );
        $dateColumnInfo->execute(['column_name' => $dateColumn]);
        if ($dateColumnInfo->fetchColumn() === 'NO') {
            $pdo->exec('ALTER TABLE library_loans MODIFY `' . $dateColumn . '` DATETIME NULL');
        }
    }
    echo "Digital library metadata, reader, annotations, access log, and lending schema applied.\n";
    exit;
}

if (!$tableExists($registryTable)) {
    fwrite(STDERR, "Rollback recusado: esta instalação não possui registro de propriedade. Nenhuma tabela ou coluna foi removida.\n");
    exit(2);
}
$registryCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM digital_library_migration_objects
     WHERE object_type = "table" AND object_name = :marker'
);
$registryCheck->execute(['marker' => $registryMarker]);
if ((int) $registryCheck->fetchColumn() !== 1) {
    fwrite(STDERR, "Rollback recusado: o registro de propriedade não possui o marcador esperado. Nenhuma tabela ou coluna foi removida.\n");
    exit(2);
}

$ownedQuery = $pdo->query('SELECT object_type, object_name FROM digital_library_migration_objects');
$ownedObjects = [];
foreach ($ownedQuery->fetchAll(PDO::FETCH_ASSOC) as $object) {
    $ownedObjects[(string) $object['object_type']][(string) $object['object_name']] = true;
}
$knownTables = [
    'library_loans',
    'library_copies',
    'library_access_events',
    'library_annotations',
    'library_reading_progress',
];
foreach ($knownTables as $table) {
    if (isset($ownedObjects['table'][$table]) && $tableExists($table)) {
        $pdo->exec('DROP TABLE `' . $table . '`');
    }
}
$knownColumns = [];
foreach (array_keys($columns) as $column) {
    $knownColumns['biblioteca.' . $column] = ['biblioteca', $column];
}
foreach ([
    'library_reading_progress' => ['font_size', 'line_spacing', 'reading_mode'],
    'library_copies' => ['authorization_evidence'],
] as $table => $tableColumns) {
    foreach ($tableColumns as $column) {
        $knownColumns[$table . '.' . $column] = [$table, $column];
    }
}
foreach ($ownedObjects as $type => $objects) {
    foreach (array_keys($objects) as $name) {
        if (
            ($type === 'table' && ($name === $registryMarker || in_array($name, $knownTables, true)))
            || ($type === 'column' && isset($knownColumns[$name]))
        ) {
            continue;
        }
        throw new RuntimeException('Objeto desconocido no registro da migração; rollback interrompido antes de remover objetos.');
    }
}
foreach ($knownTables as $table) {
    if (isset($ownedObjects['table'][$table]) && $tableExists($table)) {
        $pdo->exec('DROP TABLE `' . $table . '`');
    }
}
foreach (array_reverse($knownColumns, true) as $name => [$table, $column]) {
    if (
        isset($ownedObjects['column'][$name])
        && $tableExists($table)
        && $columnExists($table, $column)
    ) {
        $pdo->exec('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
    }
}
$pdo->exec('DROP TABLE digital_library_migration_objects');
echo "Digital library rollback complete. Only objects created by this migration were removed; pre-existing objects were retained. MySQL/MariaDB DDL rollback is non-atomic.\n";
