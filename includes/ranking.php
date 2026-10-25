<?php
declare(strict_types=1);

function obter_ranking_publico(PDO $pdo, int $limite = 50): array {
    if ($limite < 1 || $limite > 100) {
        throw new InvalidArgumentException('O limite do ranking deve ficar entre 1 e 100.');
    }

    $stmt = $pdo->query(
        'SELECT classificados.nome, classificados.pontos,
                ROW_NUMBER() OVER (ORDER BY classificados.pontos DESC, classificados.nome ASC) AS pos
         FROM (
             SELECT u.id, u.username AS nome, COALESCE(MAX(r.pontos), 0) AS pontos
             FROM usuarios u
             INNER JOIN ranking r ON r.usuario_id = u.id
             WHERE u.ranking_publico = 1
             GROUP BY u.id, u.username
         ) AS classificados
         ORDER BY classificados.pontos DESC, classificados.nome ASC
         LIMIT ' . $limite
    );

    return $stmt->fetchAll();
}
