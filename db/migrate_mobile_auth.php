<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS mobile_auth_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        account_type ENUM('user','institution') NOT NULL,
        auth_version INT UNSIGNED NOT NULL,
        device_name VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        last_used_at DATETIME NOT NULL,
        revoked_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_mobile_sessions_user (user_id, revoked_at),
        CONSTRAINT fk_mobile_session_user
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS mobile_auth_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        token_type ENUM('access','refresh') NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME DEFAULT NULL,
        revoked_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_mobile_token_hash (token_hash),
        KEY idx_mobile_token_session (session_id, token_type, expires_at),
        CONSTRAINT fk_mobile_token_session
            FOREIGN KEY (session_id) REFERENCES mobile_auth_sessions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

echo "Mobile authentication schema is ready.\n";
