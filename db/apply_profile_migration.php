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
];

foreach ($sqlStatements as $statement) {
    $pdo->exec($statement);
}

echo "Migration aplicada com sucesso: salas_usuarios e tentativas_atividade cadastrados.
";
