<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=sa_education;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlStatements = [
    "CREATE TABLE IF NOT EXISTS `salas_usuarios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sala_id` int(11) NOT NULL,
        `usuario_id` int(11) NOT NULL,
        `papel` enum('aluno','professor','moderador') NOT NULL DEFAULT 'aluno',
        `status` enum('ativo','removido') NOT NULL DEFAULT 'ativo',
        `entrou_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sala_usuario` (`sala_id`, `usuario_id`, `status`),
        KEY `fk_salas_usuarios_sala` (`sala_id`),
        KEY `fk_salas_usuarios_usuario` (`usuario_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    "CREATE TABLE IF NOT EXISTS `tentativas_atividade` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usuario_id` int(11) NOT NULL,
        `tipo` enum('exercicio','quiz') NOT NULL,
        `atividade_id` int(11) DEFAULT NULL,
        `categoria` varchar(80) DEFAULT 'geral',
        `respostas_corretas` int(11) NOT NULL DEFAULT 0,
        `respostas_incorretas` int(11) NOT NULL DEFAULT 0,
        `total_respostas` int(11) NOT NULL DEFAULT 0,
        `percentual_acerto` decimal(5,2) NOT NULL DEFAULT 0.00,
        `concluido` tinyint(1) NOT NULL DEFAULT 0,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `fk_tentativas_usuario` (`usuario_id`),
        KEY `idx_tentativas_periodo` (`usuario_id`, `concluido`, `criado_em`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    "ALTER TABLE `salas_usuarios` ADD CONSTRAINT `fk_salas_usuarios_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`) ON DELETE CASCADE;",
    "ALTER TABLE `salas_usuarios` ADD CONSTRAINT `fk_salas_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;",
    "ALTER TABLE `tentativas_atividade` ADD CONSTRAINT `fk_tentativas_atividade_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;",
    "INSERT INTO `salas_usuarios` (`sala_id`, `usuario_id`, `papel`, `status`, `entrou_em`) VALUES (1, 1, 'aluno', 'ativo', '2026-09-18 08:30:00') ON DUPLICATE KEY UPDATE `sala_id` = `sala_id`;",
    "INSERT INTO `salas_usuarios` (`sala_id`, `usuario_id`, `papel`, `status`, `entrou_em`) VALUES (3, 1, 'aluno', 'ativo', '2026-09-24 15:15:00') ON DUPLICATE KEY UPDATE `sala_id` = `sala_id`;",
    "INSERT INTO `salas_usuarios` (`sala_id`, `usuario_id`, `papel`, `status`, `entrou_em`) VALUES (2, 1, 'aluno', 'ativo', '2026-10-01 09:45:00') ON DUPLICATE KEY UPDATE `sala_id` = `sala_id`;",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'quiz', 101, 'Matemática', 7, 3, 10, 70.00, 1, '2026-04-12 18:05:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'exercicio', 201, 'Português', 6, 4, 10, 60.00, 1, '2026-05-08 20:15:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'quiz', 102, 'Biologia', 8, 2, 10, 80.00, 1, '2026-06-16 19:35:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'exercicio', 202, 'História', 9, 1, 10, 90.00, 1, '2026-07-09 18:50:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'quiz', 103, 'Matemática', 10, 0, 10, 100.00, 1, '2026-08-13 17:20:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'quiz', 104, 'Geografia', 7, 3, 10, 70.00, 1, '2026-09-21 16:40:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
    "INSERT INTO `tentativas_atividade` (`usuario_id`, `tipo`, `atividade_id`, `categoria`, `respostas_corretas`, `respostas_incorretas`, `total_respostas`, `percentual_acerto`, `concluido`, `criado_em`) VALUES (1, 'exercicio', 203, 'Química', 11, 4, 15, 73.33, 1, '2026-10-02 18:00:00') ON DUPLICATE KEY UPDATE `percentual_acerto` = VALUES(`percentual_acerto`);",
];

foreach ($sqlStatements as $statement) {
    $pdo->exec($statement);
}

echo "Migration aplicada com sucesso: salas_usuarios e tentativas_atividade cadastrados.
";
