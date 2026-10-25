<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$check = $pdo->prepare(
    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$check->execute([':table_name' => 'biblioteca', ':column_name' => 'materia']);

if ((int) $check->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE biblioteca ADD COLUMN materia VARCHAR(80) NULL AFTER tipo');
    echo "Coluna biblioteca.materia adicionada. Os registros existentes permaneceram sem disciplina.\n";
} else {
    echo "Coluna biblioteca.materia já existe; nenhuma alteração foi necessária.\n";
}