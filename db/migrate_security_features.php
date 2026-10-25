<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $stmt->execute(['table_name' => $table, 'index_name' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$columnExists('usuarios', 'auth_version')) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0');
    echo "Adicionada coluna usuarios.auth_version.\n";
}
if (!$columnExists('usuarios', 'ranking_publico')) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN ranking_publico TINYINT(1) NOT NULL DEFAULT 0');
    echo "Adicionada coluna usuarios.ranking_publico (opt-in desativado para contas existentes).\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_password_reset_token_hash (token_hash),
        KEY idx_password_reset_user (user_id, expires_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS auth_rate_limits (
        bucket_key CHAR(64) NOT NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME DEFAULT NULL,
        PRIMARY KEY (bucket_key),
        KEY idx_auth_rate_limit_expiry (blocked_until, window_started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

if (!$indexExists('ranking', 'uniq_ranking_usuario')) {
    $duplicates = $pdo->query(
        'SELECT usuario_id FROM ranking GROUP BY usuario_id HAVING COUNT(*) > 1 LIMIT 1'
    )->fetchColumn();
    if ($duplicates === false) {
        $pdo->exec('ALTER TABLE ranking ADD UNIQUE KEY uniq_ranking_usuario (usuario_id)');
        echo "Criado índice único de ranking por usuário.\n";
    } else {
        echo "AVISO: duplicatas em ranking.usuario_id; índice único não foi criado. Revisar os dados sem removê-los automaticamente.\n";
    }
}

if (!$indexExists('salas', 'uniq_salas_codigo')) {
    $duplicates = $pdo->query(
        "SELECT codigo FROM salas WHERE codigo IS NOT NULL
         GROUP BY codigo HAVING COUNT(*) > 1 LIMIT 1"
    )->fetchColumn();
    if ($duplicates === false) {
        $pdo->exec('ALTER TABLE salas ADD UNIQUE KEY uniq_salas_codigo (codigo)');
        echo "Criado índice único para códigos de sala não nulos.\n";
    } else {
        echo "AVISO: códigos de sala duplicados; índice único não foi criado. Revisar os dados sem removê-los automaticamente.\n";
    }
}

echo "Migração de segurança concluída sem alterar/remover registros existentes.\n";
