<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

function assert_profile(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assert_profile(
    validate_profile_fields('João da Silva', 'joao@example.com', 'joao.silva') === [],
    'Dados de perfil válidos devem ser aceitos.'
);

$invalid = validate_profile_fields('', 'email-invalido', 'x');
assert_profile(isset($invalid['nome_completo']), 'Nome vazio deve ser rejeitado.');
assert_profile(isset($invalid['email']), 'E-mail inválido deve ser rejeitado.');
assert_profile(isset($invalid['username']), 'Nome de usuário curto deve ser rejeitado.');

$tooLongName = str_repeat('a', 151);
assert_profile(
    isset(validate_profile_fields($tooLongName, 'joao@example.com', 'joao')['nome_completo']),
    'Nome acima do limite do banco deve ser rejeitado.'
);

set_authenticated_session([
    'id' => 71,
    'nome_completo' => 'João da Silva',
    'email' => 'joao@example.com',
    'username' => 'joao.silva',
    'perfil' => 'estudante',
    'perfil_imagem' => 'uploads/perfil/avatar.jpg',
]);
assert_profile($_SESSION['perfil_imagem'] === 'uploads/perfil/avatar.jpg', 'A foto canônica deve ser definida no login.');
assert_profile($_SESSION['usuario_foto'] === $_SESSION['perfil_imagem'], 'O alias usuario_foto deve acompanhar a fonte canônica.');
assert_profile($_SESSION['usuario_imagem'] === $_SESSION['perfil_imagem'], 'O alias usuario_imagem deve acompanhar a fonte canônica.');

sync_authenticated_profile_session([
    'id' => 71,
    'nome_completo' => 'Nome atualizado',
    'email' => 'novo@example.com',
    'username' => 'novo.nome',
    'perfil_imagem' => 'assets/images/icons/user-icon.png',
]);
assert_profile($_SESSION['usuario_nome'] === 'Nome atualizado', 'O nome atualizado deve refletir na sessão.');
assert_profile($_SESSION['usuario_email'] === 'novo@example.com', 'O e-mail atualizado deve refletir na sessão.');
assert_profile($_SESSION['usuario_foto'] === 'assets/images/icons/user-icon.png', 'A remoção deve sincronizar o fallback em sessão.');

try {
    sync_authenticated_profile_session([
        'id' => 72,
        'nome_completo' => 'Outro usuário',
        'email' => 'outro@example.com',
        'username' => 'outro.nome',
        'perfil_imagem' => '',
    ]);
    throw new RuntimeException('A atualização de uma sessão para outro ID deveria falhar.');
} catch (InvalidArgumentException) {
}

echo "OK: validação de campos, aliases de foto e isolamento do ID da sessão.\n";
