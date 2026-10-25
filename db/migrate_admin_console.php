<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$action = $argv[1] ?? '';
$confirmDataLoss = ($argv[2] ?? '') === '--confirm-data-loss';
if (!in_array($action, ['up', 'down'], true) || ($action === 'down' && !$confirmDataLoss)) {
    fwrite(STDERR, "Uso: php db/migrate_admin_console.php up|down [--confirm-data-loss]\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $query->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $query->fetchColumn() > 0;
};
$tableExists = static function (string $table) use ($pdo): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $query->execute(['table_name' => $table]);
    return (int) $query->fetchColumn() > 0;
};
$registryName = 'admin_console_migration_objects';
$registryMarker = '__admin_console_migration_registry__';
$registryExists = $tableExists($registryName);
if ($action === 'up' && !$registryExists) {
    $pdo->exec(
        "CREATE TABLE admin_console_migration_objects (
            object_name VARCHAR(64) NOT NULL PRIMARY KEY,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $marker = $pdo->prepare('INSERT INTO admin_console_migration_objects (object_name) VALUES (:object_name)');
    $marker->execute(['object_name' => $registryMarker]);
    $registryExists = true;
}
if ($registryExists) {
    $markerQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM admin_console_migration_objects WHERE object_name = :object_name'
    );
    $markerQuery->execute(['object_name' => $registryMarker]);
    if ((int) $markerQuery->fetchColumn() !== 1) {
        throw new RuntimeException('O registro de propriedade da migração já existe sem o marcador esperado; nenhuma alteração foi aplicada.');
    }
}
if ($registryExists) {
    $ownedQuery = $pdo->query('SELECT object_name FROM admin_console_migration_objects');
    $owned = array_fill_keys($ownedQuery->fetchAll(PDO::FETCH_COLUMN), true);
} else {
    $owned = [];
}

if ($action === 'up') {
    if ($columnExists('publicacoes', 'is_official') && !isset($owned['publicacoes.is_official'])) {
        throw new RuntimeException('A coluna publicacoes.is_official já existe e não pertence a esta migração; revise o esquema manualmente.');
    }
    if (!$columnExists('publicacoes', 'is_official')) {
        $pdo->exec('ALTER TABLE publicacoes ADD COLUMN is_official TINYINT(1) NOT NULL DEFAULT 0 AFTER is_premium_only');
        $record = $pdo->prepare('INSERT INTO admin_console_migration_objects (object_name) VALUES (:object_name)');
        $record->execute(['object_name' => 'publicacoes.is_official']);
    }
    if ($tableExists('official_content_history') && !isset($owned['official_content_history'])) {
        throw new RuntimeException('A tabela official_content_history já existe e não pertence a esta migração; revise o esquema manualmente.');
    }
    if (!$tableExists('official_content_history')) {
        $pdo->exec(
            "CREATE TABLE official_content_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                publication_id INT(11) DEFAULT NULL,
                actor_user_id INT(11) NOT NULL,
                action ENUM('created','updated','archived') NOT NULL,
                snapshot JSON NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_official_content_history_publication (publication_id, created_at),
                KEY idx_official_content_history_actor (actor_user_id, created_at),
                CONSTRAINT fk_official_history_publication FOREIGN KEY (publication_id)
                    REFERENCES publicacoes (id) ON DELETE SET NULL,
                CONSTRAINT fk_official_history_actor FOREIGN KEY (actor_user_id)
                    REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $record = $pdo->prepare('INSERT INTO admin_console_migration_objects (object_name) VALUES (:object_name)');
        $record->execute(['object_name' => 'official_content_history']);
    }
    if ($tableExists('official_content_editors') && !isset($owned['official_content_editors'])) {
        throw new RuntimeException('A tabela official_content_editors já existe e não pertence a esta migração; revise o esquema manualmente.');
    }
    if (!$tableExists('official_content_editors')) {
        $pdo->exec(
            "CREATE TABLE official_content_editors (
                user_id INT(11) NOT NULL,
                granted_by INT(11) NOT NULL,
                status ENUM('active','revoked') NOT NULL DEFAULT 'active',
                revoked_by INT(11) DEFAULT NULL,
                granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME DEFAULT NULL,
                PRIMARY KEY (user_id),
                KEY idx_official_content_editor_status (status, user_id),
                CONSTRAINT fk_official_editor_user FOREIGN KEY (user_id)
                    REFERENCES usuarios (id) ON DELETE CASCADE,
                CONSTRAINT fk_official_editor_granter FOREIGN KEY (granted_by)
                    REFERENCES usuarios (id) ON DELETE RESTRICT,
                CONSTRAINT fk_official_editor_revoker FOREIGN KEY (revoked_by)
                    REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $record = $pdo->prepare('INSERT INTO admin_console_migration_objects (object_name) VALUES (:object_name)');
        $record->execute(['object_name' => 'official_content_editors']);
    }
    echo "Administrative content support applied.\n";
    exit;
}

if (!$registryExists) {
    if (
        $columnExists('publicacoes', 'is_official')
        || $tableExists('official_content_history')
        || $tableExists('official_content_editors')
    ) {
        throw new RuntimeException('Os objetos existem sem registro de propriedade da migração; rollback automático recusado.');
    }
    echo "Administrative content migration is not installed; nothing to roll back.\n";
    exit;
}

if (isset($owned['official_content_history']) && $tableExists('official_content_history')) {
    $pdo->exec('DROP TABLE official_content_history');
}
if (isset($owned['official_content_editors']) && $tableExists('official_content_editors')) {
    $pdo->exec('DROP TABLE official_content_editors');
}
if (isset($owned['publicacoes.is_official']) && $columnExists('publicacoes', 'is_official')) {
    $pdo->exec('ALTER TABLE publicacoes DROP COLUMN is_official');
}
$pdo->exec('DROP TABLE admin_console_migration_objects');
echo "Administrative content support removed; official markers and history were deleted.\n";
