<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quiz.php');
    exit;
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
$resposta = strtoupper(trim((string) ($_POST['resposta'] ?? '')));
$correta = $resposta === 'B';
$acertos = $correta ? 1 : 0;
$erros = $correta ? 0 : 1;

if ($usuarioId > 0) {
    registrar_tentativa_atividade($pdo, $usuarioId, 'quiz', 1, 'Geografia', $acertos, $erros, 1);
}

header('Location: quiz.php?resultado=' . ($correta ? 'correta' : 'incorreta'));
exit;
