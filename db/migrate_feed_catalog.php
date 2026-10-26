<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'up' || count($argv) !== 2) {
    fwrite(STDERR, "Uso: php db/migrate_feed_catalog.php up\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$tableCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
);
$tableCheck->execute(['table_name' => 'publicacoes']);
if ((int) $tableCheck->fetchColumn() !== 1) {
    throw new RuntimeException('A tabela publicacoes não existe; aplique primeiro o schema-base.');
}

$indexCheck = $pdo->prepare(
    'SELECT COLUMN_NAME, SEQ_IN_INDEX
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table_name
       AND INDEX_NAME = :index_name
     ORDER BY SEQ_IN_INDEX'
);
$indexCheck->execute([
    'table_name' => 'publicacoes',
    'index_name' => 'idx_publicacoes_feed_page',
]);
$existingColumns = $indexCheck->fetchAll(PDO::FETCH_COLUMN);
$expectedColumns = ['status', 'publicado_em', 'id'];

if ($existingColumns === []) {
    $pdo->exec(
        'ALTER TABLE publicacoes
         ADD KEY idx_publicacoes_feed_page (status, publicado_em, id)'
    );
} elseif ($existingColumns !== $expectedColumns) {
    throw new RuntimeException('O índice idx_publicacoes_feed_page existe com uma definição inesperada.');
}

echo "Feed pagination index applied; existing publications were preserved.\n";
