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

header('Location: quiz.php?resultado=indisponivel', true, 303);
exit;
