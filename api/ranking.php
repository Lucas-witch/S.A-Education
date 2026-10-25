<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../includes/ranking.php';
require_once __DIR__ . '/../conexao.php';

try {
    $rows = array_map(
        static fn (array $row): array => [
            'nome_publico' => (string) $row['nome'],
            'pontos' => (int) $row['pontos'],
            'posicao' => (int) $row['pos'],
        ],
        obter_ranking_publico($pdo, 50)
    );
    echo json_encode($rows);
    exit;
} catch (PDOException $e) {
    error_log('[S.A Education][api/ranking][PDO] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar ranking']);
    exit;
}
?>