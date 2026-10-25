<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user_preferences.php';
require_once __DIR__ . '/../conexao.php';

$account = require_settings_account();
$statement = $pdo->prepare(
    'SELECT id, nome_completo, email, username, perfil, perfil_imagem, criado_em, ranking_publico
     FROM usuarios
     WHERE id = :id
     LIMIT 1'
);
$statement->execute(['id' => $account['user_id']]);
$user = $statement->fetch(PDO::FETCH_ASSOC);
if (!is_array($user)) {
    http_response_code(404);
    exit('Conta não encontrada.');
}

$export = [
    'exported_at' => gmdate(DATE_ATOM),
    'scope' => 'Dados básicos da conta e preferências disponíveis no sistema.',
    'account' => $user,
    'preferences' => load_user_preferences($pdo, $account['user_id']),
];

if ($account['institution_account']) {
    $institutionQuery = $pdo->prepare(
        'SELECT nome, tipo, natureza, responsavel, telefone, cidade, validation_status, criado_em
         FROM instituicoes
         WHERE usuario_id = :user_id
         LIMIT 1'
    );
    $institutionQuery->execute(['user_id' => $account['user_id']]);
    $export['institution'] = $institutionQuery->fetch(PDO::FETCH_ASSOC) ?: null;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="sa-education-dados-pessoais.json"');
send_private_no_store_headers();
echo json_encode(
    $export,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
);
