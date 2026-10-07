<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
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
    /*
     * Não reutilizar o mesmo placeholder quando ATTR_EMULATE_PREPARES=false.
     * A busca também não depende de colunas opcionais do restante do sistema.
     */
    $stmt = $pdo->prepare(
        'SELECT u.*
         FROM usuarios u
         WHERE LOWER(u.email) = LOWER(:identificador_email)
            OR LOWER(u.username) = LOWER(:identificador_username)
         LIMIT 1'
    );

    $stmt->execute([
        'identificador_email' => $identificador,
        'identificador_username' => $identificador,
    ]);

    $usuario = $stmt->fetch();

    if (!$usuario || !isset($usuario['id']) || !is_login_perfil_permitido($usuario['perfil'] ?? '')) {
        $voltar('Nenhuma conta de estudante ou professor foi encontrada para esse e-mail ou usuário.');
    }

    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Falha ao gerar o hash da nova senha.');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
    $stmt->execute([
        'senha' => $hash,
        'id' => (int) $usuario['id'],
    ]);

    $_SESSION['sucesso'] = 'Sua senha foi redefinida com sucesso! Faça login novamente.';
    header('Location: login.php');
    exit;
} catch (PDOException $e) {
    error_log('[S.A Education][redefinir_senha][PDO] ' . $e->getMessage());
    $_SESSION['erro_redefinir'] = 'Não foi possível redefinir a senha agora. Tente novamente.';
    header('Location: redefinir-senha.php');
    exit;
} catch (RuntimeException $e) {
    error_log('[S.A Education][redefinir_senha] ' . $e->getMessage());
    $_SESSION['erro_redefinir'] = 'Não foi possível redefinir a senha agora. Tente novamente.';
    header('Location: redefinir-senha.php');
    exit;
}
