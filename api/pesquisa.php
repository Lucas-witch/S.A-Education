<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
send_private_no_store_headers();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/search.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }

    $readText = static function (string $name, int $maxLength): string {
        $value = $_GET[$name] ?? '';
        if (!is_string($value) || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException('Parâmetro inválido.');
        }
        return trim($value);
    };

    $q = $readText('q', 120);
    $assunto = $readText('assunto', 80);
    $materia = $readText('materia', 80);
    $tipo = strtolower($readText('tipo', 30));
    $acesso = strtolower($readText('acesso', 12));

    $pageInput = $_GET['page'] ?? '1';
    $limitInput = $_GET['limit'] ?? '8';
    if (!is_string($pageInput) || !ctype_digit($pageInput) || !is_string($limitInput) || !ctype_digit($limitInput)) {
        throw new InvalidArgumentException('Parâmetro de paginação inválido.');
    }
    $page = max(1, min(100000, (int) $pageInput));
    $limit = max(1, min(20, (int) $limitInput));

    $usuarioAtualId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $usuarioAtualId = $usuarioAtualId === false || $usuarioAtualId <= 0 ? 0 : $usuarioAtualId;
    $usuarioPremium = $usuarioAtualId > 0 && !empty($_SESSION['usuario_premium_ativo']);

    $resultado = pesquisar_catalogo($pdo, $usuarioAtualId, $usuarioPremium, $q, $assunto, $materia, $tipo, $acesso, $page, $limit);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros de pesquisa inválidos.']);
} catch (PDOException $exception) {
    error_log('[S.A Education][api/pesquisa] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível pesquisar agora.']);
}