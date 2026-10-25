<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf($_POST['csrf'] ?? null);
$_SESSION['library_flash'] = [
    'type' => 'error',
    'message' => 'O cadastro antigo foi desativado. Use o editor da Biblioteca para enviar o material para revisão.',
];
header('Location: biblioteca-editor.php', true, 303);
exit;
