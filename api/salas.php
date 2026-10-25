<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
send_private_no_store_headers();
require_once __DIR__ . '/../includes/room_search.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }

    $usuarioAtualId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioAtualId !== false && $usuarioAtualId > 0 && !authenticated_session_is_current($pdo)) {
        invalidate_current_session();
        $usuarioAtualId = 0;
    }

    if (isset($_GET['id'])) {
        $rawId = $_GET['id'];
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetro inválido.']);
            exit;
        }

        $salaResultados = buscar_salas($pdo, $usuarioAtualId === false || $usuarioAtualId <= 0 ? 0 : $usuarioAtualId, '', '', '', '', (int) $rawId);
        $sala = $salaResultados[0] ?? null;

        if (!$sala) {
            http_response_code(404);
            echo json_encode(['error' => 'Sala não encontrada.']);
            exit;
        }

        echo json_encode($sala);
        exit;
    }

    $readFilter = static function (string $name, int $limit): string {
        $value = $_GET[$name] ?? '';
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Parâmetro inválido.');
        }
        return trim($value);
    };
    $termo = $readFilter('q', 120);
    $assunto = $readFilter('assunto', 80);
    $materia = $readFilter('materia', 80);
    $acesso = strtolower($readFilter('acesso', 12));
    $salas = buscar_salas($pdo, $usuarioAtualId === false || $usuarioAtualId <= 0 ? 0 : $usuarioAtualId, $termo, $assunto, $materia, $acesso);
    echo json_encode($salas);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros de pesquisa inválidos.']);
    exit;
} catch (PDOException $e) {
    error_log('[S.A Education][api/salas] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar salas.']);
    exit;
}
?>