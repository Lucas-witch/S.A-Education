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

putenv('APP_ADMIN_USER_ID=71');
set_authenticated_session([
    'id' => 71,
    'nome_completo' => 'João da Silva',
    'email' => 'joao@example.com',
    'username' => 'joao.silva',
    'perfil' => 'estudante',
    'perfil_imagem' => 'uploads/perfil/avatar.jpg',
]);
assert_profile(is_admin_user(), 'Somente o ID explicitamente configurado deve receber o perfil administrativo.');
assert_profile($_SESSION['usuario_is_admin'] === true, 'A conta administradora deve ser marcada na sessão autenticada.');
assert_profile($_SESSION['usuario_premium_ativo'] === true, 'A conta administradora deve receber acesso Premium.');
assert_profile($_SESSION['usuario_pode_criar_salas'] === true, 'A conta administradora deve receber criação de salas.');
assert_profile($_SESSION['usuario_pode_criar_comunidades_privadas'] === true, 'A conta administradora deve receber comunidades privadas.');
assert_profile($_SESSION['usuario_pode_postar_aulas_ilimitadas'] === true, 'A conta administradora deve receber publicação ilimitada de aulas.');
assert_profile($_SESSION['usuario_pode_postar_artigos'] === true, 'A conta administradora deve receber publicação de artigos.');
assert_profile(usuario_tem_acesso_premium(), 'A conta administradora deve resolver como Premium.');
assert_profile(perfil_label('professor') === 'Administrador', 'A conta configurada deve ser identificada como administradora.');
$adminSessionId = $_SESSION['usuario_id'];
$_SESSION['usuario_id'] = 72;
$_SESSION['usuario_is_admin'] = true;
assert_profile(!is_admin_user(), 'Uma marca de sessão não pode conceder administração a outro ID.');
$_SESSION['usuario_id'] = $adminSessionId;
assert_profile($_SESSION['perfil_imagem'] === 'uploads/perfil/avatar.jpg', 'A foto canônica deve ser definida no login.');
assert_profile($_SESSION['usuario_auth_version'] === 0, 'A versão da sessão deve ser definida para invalidar sessões antigas após redefinição de senha.');
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

try {
    set_authenticated_session([
        'id' => 73,
        'nome_completo' => 'Conta institucional',
        'email' => 'instituicao@example.com',
        'username' => 'instituicao',
        'perfil' => 'instituicao',
    ]);
    throw new RuntimeException('O login comum não deve aceitar contas institucionais.');
} catch (InvalidArgumentException) {
}

set_institution_authenticated_session([
    'id' => 73,
    'nome_completo' => 'Conta institucional',
    'nome_instituicao' => 'Escola de Teste',
    'email' => 'instituicao@example.com',
    'perfil' => 'instituicao',
    'auth_version' => 2,
]);
putenv('APP_ADMIN_USER_ID');
assert_profile($_SESSION['instituicao_usuario_id'] === 73, 'A sessão institucional deve usar um identificador separado.');
assert_profile(!isset($_SESSION['usuario_id']), 'A sessão institucional não deve acessar o contexto de estudante/professor.');
assert_profile($_SESSION['instituicao_auth_version'] === 2, 'A sessão institucional deve acompanhar a versão de autenticação.');

echo "OK: validação de campos, aliases de foto e isolamento do ID da sessão.\n";
