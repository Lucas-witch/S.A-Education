<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$demoAttempts = [
    ['quiz', 101, 'Matemática', 7, 3, 10, '70.00', '2026-04-12 18:05:00'],
    ['exercicio', 201, 'Português', 6, 4, 10, '60.00', '2026-05-08 20:15:00'],
    ['quiz', 102, 'Biologia', 8, 2, 10, '80.00', '2026-06-16 19:35:00'],
    ['exercicio', 202, 'História', 9, 1, 10, '90.00', '2026-07-09 18:50:00'],
    ['quiz', 103, 'Matemática', 10, 0, 10, '100.00', '2026-08-13 17:20:00'],
    ['quiz', 104, 'Geografia', 7, 3, 10, '70.00', '2026-09-21 16:40:00'],
    ['exercicio', 203, 'Química', 11, 4, 15, '73.33', '2026-10-02 18:00:00'],
];

$demoMemberships = [
    [1, '2026-09-18 08:30:00'],
    [3, '2026-09-24 15:15:00'],
    [2, '2026-10-01 09:45:00'],
];

try {
    $pdo->beginTransaction();
    $removedAttempts = 0;
    $attemptStatement = $pdo->prepare(
        'DELETE FROM tentativas_atividade
         WHERE usuario_id = 1 AND tipo = :tipo AND atividade_id = :atividade_id
           AND categoria = :categoria AND respostas_corretas = :acertos
                     AND respostas_incorretas = :erros AND total_respostas = :total
                     AND percentual_acerto = :percentual AND concluido = 1
                     AND criado_em BETWEEN :inicio AND :fim'
    );

    foreach ($demoAttempts as [$type, $activityId, $category, $correct, $incorrect, $total, $percentage, $createdAt]) {
                $fixtureDate = new DateTimeImmutable($createdAt);
        $attemptStatement->execute([
            ':tipo' => $type,
            ':atividade_id' => $activityId,
            ':categoria' => $category,
            ':acertos' => $correct,
            ':erros' => $incorrect,
            ':total' => $total,
            ':percentual' => $percentage,
            ':inicio' => $fixtureDate->modify('-1 day')->format('Y-m-d H:i:s'),
            ':fim' => $fixtureDate->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $removedAttempts += $attemptStatement->rowCount();
    }

    $membershipStatement = $pdo->prepare(
        'DELETE FROM salas_usuarios
         WHERE usuario_id = 1 AND papel = "aluno" AND status = "ativo"
           AND sala_id = :sala_id AND entrou_em = :entrou_em'
    );
    $removedMemberships = 0;
    foreach ($demoMemberships as [$roomId, $joinedAt]) {
        $membershipStatement->execute([
            ':sala_id' => $roomId,
            ':entrou_em' => $joinedAt,
        ]);
        $removedMemberships += $membershipStatement->rowCount();
    }

    $pdo->commit();
    echo "Fixtures exatos removidos: {$removedAttempts} tentativas e {$removedMemberships} vínculos.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][remove_profile_demo_data] ' . $exception->getMessage());
    http_response_code(500);
    exit("Falha ao limpar fixtures conhecidos; nenhuma alteração foi confirmada.\n");
}