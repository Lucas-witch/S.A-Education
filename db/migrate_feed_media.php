<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$action = $argv[1] ?? '';
$confirmDataLoss = ($argv[2] ?? '') === '--confirm-data-loss';
if (!in_array($action, ['up', 'down'], true) || ($action === 'down' && (!$confirmDataLoss || count($argv) !== 3)) || ($action === 'up' && count($argv) !== 2)) {
    fwrite(STDERR, "Uso: php db/migrate_feed_media.php up|down [--confirm-data-loss]\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$migrationKey = 'feed_media_v1';
$registry = 'feed_media_migration_objects';
$columns = [
    'media_storage_key' => 'VARCHAR(255) DEFAULT NULL',
    'media_mime' => 'VARCHAR(100) DEFAULT NULL',
    'media_size' => 'BIGINT UNSIGNED DEFAULT NULL',
    'media_sha256' => 'CHAR(64) DEFAULT NULL',
];
$tableExists = static function (string $table) use ($pdo): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $query->execute(['table_name' => $table]);
    return (int) $query->fetchColumn() > 0;
};
$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $query->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $query->fetchColumn() > 0;
};

if ($action === 'up') {
    if (!$tableExists($registry)) {
        $pdo->exec(
            'CREATE TABLE feed_media_migration_objects (
                migration_key VARCHAR(64) NOT NULL,
                object_name VARCHAR(64) NOT NULL,
                PRIMARY KEY (migration_key, object_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }
    foreach ($columns as $column => $definition) {
        if (!$columnExists('publicacoes', $column)) {
            $pdo->exec('ALTER TABLE publicacoes ADD COLUMN `' . $column . '` ' . $definition);
            $record = $pdo->prepare(
                'INSERT INTO feed_media_migration_objects (migration_key, object_name)
                 VALUES (:migration_key, :object_name)'
            );
            $record->execute(['migration_key' => $migrationKey, 'object_name' => $column]);
        }
    }
    echo "Feed media schema applied. Existing posts and video links are preserved.\n";
    exit;
}

if (!$tableExists($registry)) {
    fwrite(STDERR, "Rollback recusado: esta instalação não possui registro de propriedade da migração.\n");
    exit(2);
}
if ($columnExists('publicacoes', 'media_storage_key')) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM publicacoes WHERE media_storage_key IS NOT NULL')->fetchColumn();
    if ($count > 0) {
        fwrite(STDERR, "Rollback recusado: existem anexos no Feed. Remova-os e faça backup antes de tentar novamente.\n");
        exit(2);
    }
}

$owned = $pdo->prepare(
    'SELECT object_name FROM feed_media_migration_objects WHERE migration_key = :migration_key'
);
$owned->execute(['migration_key' => $migrationKey]);
foreach ($owned->fetchAll(PDO::FETCH_COLUMN) as $column) {
    if (isset($columns[$column]) && $columnExists('publicacoes', $column)) {
        $pdo->exec('ALTER TABLE publicacoes DROP COLUMN `' . $column . '`');
    }
}
$delete = $pdo->prepare('DELETE FROM feed_media_migration_objects WHERE migration_key = :migration_key');
$delete->execute(['migration_key' => $migrationKey]);
$count = (int) $pdo->query('SELECT COUNT(*) FROM feed_media_migration_objects')->fetchColumn();
if ($count === 0) {
    $pdo->exec('DROP TABLE feed_media_migration_objects');
}
echo "Feed media schema rolled back. No uploaded files were deleted.\n";
