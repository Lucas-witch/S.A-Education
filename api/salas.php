<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexao.php';

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM salas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$_GET['id']]);
        $sala = $stmt->fetch();
        echo json_encode($sala ?: null);
        exit;
    }

    // Listagem simples
    $stmt = $pdo->query('SELECT id,nome,prof,alunos,materia,icone,codigo,privacidade FROM salas ORDER BY criado_em DESC');
    $salas = $stmt->fetchAll();
    echo json_encode($salas);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar salas.']);
    exit;
}
?>