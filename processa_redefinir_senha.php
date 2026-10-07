<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: redefinir-senha.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');

$identificador = trim((string) ($_POST['identificador'] ?? ''));
$novaSenha = (string) ($_POST['nova_senha'] ?? '');
$confirmarNovaSenha = (string) ($_POST['confirmar_nova_senha'] ?? '');

$voltar = function (string $mensagem): never {
    $_SESSION['erro_redefinir'] = $mensagem;
    header('Location: redefinir-senha.php');
    exit;
};

if ($identificador === '') {
    $voltar('Informe seu e-mail ou nome de usuário.');
}

if (strlen($novaSenha) < 8 || !preg_match('/[A-Z]/', $novaSenha) || !preg_match('/[\d\W]/', $novaSenha)) {
    $voltar('A nova senha precisa ter 8 caracteres, uma letra maiúscula e um número ou símbolo.');
}

if ($novaSenha !== $confirmarNovaSenha) {
    $voltar('As novas senhas não coincidem.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios
         WHERE (LOWER(email) = LOWER(:identificador) OR LOWER(username) = LOWER(:identificador))
           AND perfil IN (\'estudante\', \'professor\')
         LIMIT 1'
    );
    $stmt->execute(['identificador' => $identificador]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $voltar('Nenhuma conta ativa foi encontrada para esse e-mail ou usuário.');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
    $stmt->execute([
        'senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
        'id' => (int) $usuario['id'],
    ]);

    $_SESSION['sucesso'] = 'Sua senha foi redefinida com sucesso! Faça login novamente.';
    header('Location: login.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['erro_redefinir'] = 'Não foi possível redefinir a senha agora. Tente novamente.';
    header('Location: redefinir-senha.php');
    exit;
}
