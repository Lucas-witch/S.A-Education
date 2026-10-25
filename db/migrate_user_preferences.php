<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_preferences (
        user_id INT(11) NOT NULL,
        theme ENUM('light','dark','auto') NOT NULL DEFAULT 'light',
        font_size ENUM('small','standard','large','extra-large') NOT NULL DEFAULT 'standard',
        high_contrast TINYINT(1) NOT NULL DEFAULT 0,
        reduce_motion TINYINT(1) NOT NULL DEFAULT 0,
        underline_links TINYINT(1) NOT NULL DEFAULT 0,
        line_spacing ENUM('normal','relaxed') NOT NULL DEFAULT 'normal',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        CONSTRAINT fk_user_preferences_user
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

echo "Tabela user_preferences pronta; nenhum dado de conta foi alterado.\n";
