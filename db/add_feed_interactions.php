<?php
declare(strict_types=1);

require_once __DIR__ . '/../conexao.php';

$statements = [
    "CREATE TABLE IF NOT EXISTS `publicacao_curtidas` (
        `publicacao_id` int(11) NOT NULL,
        `usuario_id` int(11) NOT NULL,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`publicacao_id`, `usuario_id`),
        KEY `idx_publicacao_curtidas_usuario` (`usuario_id`),
        CONSTRAINT `fk_publicacao_curtidas_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_publicacao_curtidas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    "CREATE TABLE IF NOT EXISTS `publicacao_salvamentos` (
        `publicacao_id` int(11) NOT NULL,
        `usuario_id` int(11) NOT NULL,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`publicacao_id`, `usuario_id`),
        KEY `idx_publicacao_salvamentos_usuario` (`usuario_id`),
        CONSTRAINT `fk_publicacao_salvamentos_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_publicacao_salvamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    "CREATE TABLE IF NOT EXISTS `publicacao_comentarios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `publicacao_id` int(11) NOT NULL,
        `usuario_id` int(11) NOT NULL,
        `conteudo` varchar(2000) NOT NULL,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_publicacao_comentarios_feed` (`publicacao_id`, `criado_em`, `id`),
        KEY `idx_publicacao_comentarios_usuario` (`usuario_id`),
        CONSTRAINT `fk_publicacao_comentarios_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_publicacao_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

echo "Tabelas de curtidas, salvamentos e comentários verificadas.\n";