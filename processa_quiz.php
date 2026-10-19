<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf($_POST['csrf'] ?? null);

$respostaInput = $_POST['resposta'] ?? null;
if (!is_string($respostaInput) || !in_array(strtoupper(trim($respostaInput)), ['A', 'B', 'C', 'D'], true)) {
    http_response_code(400);
    exit('Resposta inválida.');
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    header('Location: login.php');
    exit;
}

$resposta = strtoupper(trim($respostaInput));
$correta = $resposta === 'B';
$acertos = $correta ? 1 : 0;
$erros = $correta ? 0 : 1;

try {
    registrar_tentativa_atividade($pdo, $usuarioId, 'quiz', 1, 'Geografia', $acertos, $erros, 1);
} catch (Throwable $exception) {
    error_log('[S.A Education][quiz_attempt] ' . $exception->getMessage());
    http_response_code(500);
    exit('Não foi possível registrar sua resposta agora.');
}

header('Location: quiz.php?resultado=' . ($correta ? 'correta' : 'incorreta'));
exit;
