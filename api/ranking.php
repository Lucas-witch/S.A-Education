<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexao.php';

try {
    $stmt = $pdo->query('SELECT u.id,u.nome_completo,r.pontos FROM ranking r JOIN usuarios u ON r.usuario_id = u.id ORDER BY r.pontos DESC LIMIT 50');
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar ranking']);
    exit;
}
?>