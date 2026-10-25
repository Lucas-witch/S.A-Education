<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
send_private_no_store_headers();
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    if (!authenticated_session_is_current($pdo)) {
        invalidate_current_session();
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id,nome_completo,email,username,perfil,perfil_imagem FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['usuario_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit;
    }
    echo json_encode($user);
    exit;
} catch (PDOException $e) {
    error_log('[S.A Education][api/usuario][PDO] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar usuário']);
    exit;
}
?>