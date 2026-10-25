<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$constraintExists = static function (string $table, string $constraint) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name'
    );
    $stmt->execute(['table_name' => $table, 'constraint_name' => $constraint]);
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

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS salas_usuarios (
        id INT(11) NOT NULL AUTO_INCREMENT,
        sala_id INT(11) NOT NULL,
        usuario_id INT(11) NOT NULL,
        papel ENUM('aluno','professor','moderador') NOT NULL DEFAULT 'aluno',
        status ENUM('ativo','removido') NOT NULL DEFAULT 'ativo',
        entrou_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_sala_usuario (sala_id, usuario_id, status),
        KEY idx_salas_usuarios_sala (sala_id),
        KEY idx_salas_usuarios_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS tentativas_atividade (
        id INT(11) NOT NULL AUTO_INCREMENT,
        usuario_id INT(11) NOT NULL,
        tipo ENUM('exercicio','quiz') NOT NULL,
        atividade_id INT(11) DEFAULT NULL,
        categoria VARCHAR(80) DEFAULT 'geral',
        respostas_corretas INT(11) NOT NULL DEFAULT 0,
        respostas_incorretas INT(11) NOT NULL DEFAULT 0,
        total_respostas INT(11) NOT NULL DEFAULT 0,
        percentual_acerto DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        concluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tentativas_usuario (usuario_id),
        KEY idx_tentativas_periodo (usuario_id, concluido, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$duplicateMembership = $pdo->query(
    'SELECT sala_id FROM salas_usuarios
     GROUP BY sala_id, usuario_id, status HAVING COUNT(*) > 1 LIMIT 1'
)->fetchColumn();
if (!$indexExists('salas_usuarios', 'uniq_sala_usuario')) {
    if ($duplicateMembership === false) {
        $pdo->exec('ALTER TABLE salas_usuarios ADD UNIQUE KEY uniq_sala_usuario (sala_id, usuario_id, status)');
    } else {
        echo "AVISO: vínculos de sala duplicados; índice único não foi criado.\n";
    }
}

$foreignKeys = [
    ['salas_usuarios', 'fk_salas_usuarios_sala', 'FOREIGN KEY (sala_id) REFERENCES salas (id) ON DELETE CASCADE'],
    ['salas_usuarios', 'fk_salas_usuarios_usuario', 'FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE'],
    ['tentativas_atividade', 'fk_tentativas_atividade_usuario', 'FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE'],
];

foreach ($foreignKeys as [$table, $constraint, $definition]) {
    if (!$constraintExists($table, $constraint)) {
        $orphanQuery = match ($constraint) {
            'fk_salas_usuarios_sala' => 'SELECT COUNT(*) FROM salas_usuarios child LEFT JOIN salas parent ON parent.id = child.sala_id WHERE parent.id IS NULL',
            'fk_salas_usuarios_usuario' => 'SELECT COUNT(*) FROM salas_usuarios child LEFT JOIN usuarios parent ON parent.id = child.usuario_id WHERE parent.id IS NULL',
            'fk_tentativas_atividade_usuario' => 'SELECT COUNT(*) FROM tentativas_atividade child LEFT JOIN usuarios parent ON parent.id = child.usuario_id WHERE parent.id IS NULL',
            default => throw new LogicException('Constraint não reconhecida.'),
        };
        if ((int) $pdo->query($orphanQuery)->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` {$definition}");
        } else {
            echo "AVISO: referências órfãs impedem a constraint {$constraint}; registros foram preservados.\n";
        }
    }
}

echo "Migração idempotente concluída: salas_usuarios e tentativas_atividade.\n";
