<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (
    !in_array($argv[1] ?? '', ['up', 'down'], true)
    || (($argv[1] ?? '') === 'down' && (count($argv) !== 3 || ($argv[2] ?? '') !== '--confirm-data-loss'))
    || (($argv[1] ?? '') === 'up' && count($argv) !== 2)
) {
    fwrite(STDERR, "Uso: php db/migrate_video_learning.php up|down [--confirm-data-loss]\n");
    fwrite(STDERR, "O argumento --confirm-data-loss é obrigatório para down.\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$migrationKey = 'video_learning_v1';
$ownershipTable = 'video_learning_migration_objects';
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
$recordOwnedObject = static function (string $type, string $name) use ($pdo, $migrationKey): void {
    $statement = $pdo->prepare(
        'INSERT INTO video_learning_migration_objects (migration_key, object_type, object_name)
         VALUES (:migration_key, :object_type, :object_name)'
    );
    $statement->execute([
        'migration_key' => $migrationKey,
        'object_type' => $type,
        'object_name' => $name,
    ]);
};

$columns = [
    'education_stage' => 'VARCHAR(100) DEFAULT NULL',
    'knowledge_area' => 'VARCHAR(120) DEFAULT NULL',
    'subject' => 'VARCHAR(120) DEFAULT NULL',
    'topic' => 'VARCHAR(180) DEFAULT NULL',
    'subtopic' => 'VARCHAR(180) DEFAULT NULL',
    'course_title' => 'VARCHAR(180) DEFAULT NULL',
    'complementary_materials' => 'VARCHAR(1000) DEFAULT NULL',
    'related_exercises' => 'VARCHAR(1000) DEFAULT NULL',
    'captions_url' => 'VARCHAR(1000) DEFAULT NULL',
];

if ($argv[1] === 'up') {
    if (!$tableExists($ownershipTable)) {
        $pdo->exec(
            'CREATE TABLE video_learning_migration_objects (
                migration_key VARCHAR(64) NOT NULL,
                object_type ENUM("column", "table") NOT NULL,
                object_name VARCHAR(64) NOT NULL,
                PRIMARY KEY (migration_key, object_type, object_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        $recordOwnedObject('table', $ownershipTable);
    }
    $pdo->query(
        'SELECT migration_key, object_type, object_name
         FROM video_learning_migration_objects LIMIT 0'
    );

    foreach ($columns as $column => $definition) {
        if (!$columnExists('publicacoes', $column)) {
            $pdo->exec('ALTER TABLE publicacoes ADD COLUMN `' . $column . '` ' . $definition);
            $recordOwnedObject('column', $column);
        }
    }
    $tables = [
        'video_learning_progress' => "CREATE TABLE video_learning_progress (
            user_id INT(11) NOT NULL,
            publication_id INT(11) NOT NULL,
            played_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
            last_position DECIMAL(12,3) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, publication_id),
            CONSTRAINT fk_video_progress_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_video_progress_publication FOREIGN KEY (publication_id) REFERENCES publicacoes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'video_playback_sessions' => "CREATE TABLE video_playback_sessions (
            user_id INT(11) NOT NULL,
            publication_id INT(11) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            last_position DECIMAL(12,3) NOT NULL DEFAULT 0,
            heartbeat_at BIGINT UNSIGNED NOT NULL,
            is_playing TINYINT(1) NOT NULL DEFAULT 0,
            client_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, publication_id),
            UNIQUE KEY uniq_video_session_token (token_hash),
            CONSTRAINT fk_video_session_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_video_session_publication FOREIGN KEY (publication_id) REFERENCES publicacoes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'video_ad_intervals' => "CREATE TABLE video_ad_intervals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            publication_id INT(11) NOT NULL,
            interval_number INT UNSIGNED NOT NULL,
            status ENUM('not_configured','provider_pending','served','failed_open') NOT NULL DEFAULT 'not_configured',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_video_ad_interval (user_id, publication_id, interval_number),
            CONSTRAINT fk_video_ad_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            CONSTRAINT fk_video_ad_publication FOREIGN KEY (publication_id) REFERENCES publicacoes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];
    foreach ($tables as $table => $sql) {
        if (!$tableExists($table)) {
            $pdo->exec($sql);
            $recordOwnedObject('table', $table);
        }
    }
    echo "Video lesson schema applied. Only objects created by this migration are registered for rollback.\n";
    exit;
}

if (!$tableExists($ownershipTable)) {
    fwrite(STDERR, "Rollback recusado: esta instalação não possui registro de propriedade dos objetos. Faça inventário e rollback manual após backup.\n");
    exit(2);
}

$ownedObjectsQuery = $pdo->prepare(
    'SELECT object_type, object_name
     FROM video_learning_migration_objects
     WHERE migration_key = :migration_key
     ORDER BY CASE object_type WHEN "table" THEN 0 ELSE 1 END, object_name'
);
$ownedObjectsQuery->execute(['migration_key' => $migrationKey]);
$ownedObjects = $ownedObjectsQuery->fetchAll(PDO::FETCH_ASSOC);

$knownTables = ['video_ad_intervals', 'video_playback_sessions', 'video_learning_progress', $ownershipTable];
$knownColumns = array_keys($columns);
$ownsOwnershipTable = false;
foreach ($ownedObjects as $object) {
    $name = (string) $object['object_name'];
    if ($object['object_type'] === 'table' && in_array($name, $knownTables, true)) {
        if ($name === $ownershipTable) {
            $ownsOwnershipTable = true;
        } elseif ($tableExists($name)) {
            $pdo->exec('DROP TABLE `' . $name . '`');
        }
    } elseif ($object['object_type'] === 'column' && in_array($name, $knownColumns, true)) {
        if ($columnExists('publicacoes', $name)) {
            $pdo->exec('ALTER TABLE publicacoes DROP COLUMN `' . $name . '`');
        }
    } else {
        throw new RuntimeException('Objeto desconhecido no registro da migração; rollback interrompido.');
    }

    $deleteOwnership = $pdo->prepare(
        'DELETE FROM video_learning_migration_objects
         WHERE migration_key = :migration_key AND object_type = :object_type AND object_name = :object_name'
    );
    $deleteOwnership->execute([
        'migration_key' => $migrationKey,
        'object_type' => $object['object_type'],
        'object_name' => $name,
    ]);
}
if ($ownsOwnershipTable) {
    $pdo->exec('DROP TABLE video_learning_migration_objects');
}
echo "Rollback complete. Only tracked objects were removed; objects predating this migration were retained. MariaDB DDL is non-atomic.\n";
