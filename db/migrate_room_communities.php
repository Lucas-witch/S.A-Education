<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
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
$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $statement->execute(['table_name' => $table, 'index_name' => $index]);
    return (int) $statement->fetchColumn() > 0;
};

$columns = [
    'criador_id' => 'INT(11) DEFAULT NULL AFTER `nome`',
    'institution_id' => 'INT(11) DEFAULT NULL AFTER `criador_id`',
    'categoria' => 'VARCHAR(80) DEFAULT NULL AFTER `materia`',
    'area_conhecimento' => 'VARCHAR(120) DEFAULT NULL AFTER `categoria`',
];
foreach ($columns as $column => $definition) {
    if (!$columnExists('salas', $column)) {
        $pdo->exec("ALTER TABLE `salas` ADD COLUMN `{$column}` {$definition}");
    }
}

$pdo->exec(
    "ALTER TABLE salas
     MODIFY privacidade ENUM('publica','privada','instituicao') DEFAULT 'publica'"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS room_join_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        status ENUM('pending','approved','rejected','canceled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL DEFAULT NULL,
        reviewed_by INT(11) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_room_join_requests_review (room_id, status, created_at),
        KEY idx_room_join_requests_user_status (room_id, user_id, status),
        CONSTRAINT fk_room_join_requests_room FOREIGN KEY (room_id) REFERENCES salas (id) ON DELETE CASCADE,
        CONSTRAINT fk_room_join_requests_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
        CONSTRAINT fk_room_join_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
if ($indexExists('room_join_requests', 'uq_room_join_requests_status')) {
    $pdo->exec('ALTER TABLE room_join_requests DROP INDEX uq_room_join_requests_status');
}
if (!$indexExists('room_join_requests', 'idx_room_join_requests_user_status')) {
    $pdo->exec(
        'ALTER TABLE room_join_requests ADD KEY idx_room_join_requests_user_status
         (room_id, user_id, status)'
    );
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS room_reports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT(11) NOT NULL,
        reporter_user_id INT(11) NOT NULL,
        target_type ENUM('room','publication','comment') NOT NULL DEFAULT 'room',
        target_id INT(11) NOT NULL DEFAULT 0,
        reason VARCHAR(80) NOT NULL,
        details VARCHAR(1000) DEFAULT NULL,
        status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT(11) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_room_report_target_status (room_id, reporter_user_id, target_type, target_id, status),
        KEY idx_room_reports_review (status, created_at),
        KEY idx_room_reports_room (room_id, created_at),
        CONSTRAINT fk_room_reports_room FOREIGN KEY (room_id) REFERENCES salas (id) ON DELETE CASCADE,
        CONSTRAINT fk_room_reports_user FOREIGN KEY (reporter_user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
        CONSTRAINT fk_room_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
if (!$columnExists('room_reports', 'target_type')) {
    $pdo->exec("ALTER TABLE room_reports ADD COLUMN target_type ENUM('room','publication','comment') NOT NULL DEFAULT 'room' AFTER reporter_user_id");
}
if (!$columnExists('room_reports', 'target_id')) {
    $pdo->exec('ALTER TABLE room_reports ADD COLUMN target_id INT(11) NOT NULL DEFAULT 0 AFTER target_type');
}
if (!$columnExists('room_reports', 'reviewed_by')) {
    $pdo->exec('ALTER TABLE room_reports ADD COLUMN reviewed_by INT(11) DEFAULT NULL AFTER created_at');
}
if ($indexExists('room_reports', 'uq_room_reporter_status')) {
    $pdo->exec('ALTER TABLE room_reports DROP INDEX uq_room_reporter_status');
}
if (!$indexExists('room_reports', 'uq_room_report_target_status')) {
    $pdo->exec(
        'ALTER TABLE room_reports ADD UNIQUE KEY uq_room_report_target_status
         (room_id, reporter_user_id, target_type, target_id, status)'
    );
}

echo "Migração de salas e comunidades concluída; dados existentes foram preservados.\n";
