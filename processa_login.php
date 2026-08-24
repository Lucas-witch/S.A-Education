<?php
declare(strict_types=1);

session_start();
require_once 'includes/helpers.php';
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Verifica CSRF token
verify_csrf($_POST['csrf'] ?? '');

$login = trim($_POST['login'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($login === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha todos os campos.';
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, nome_completo, email, username, senha, perfil
         FROM usuarios
         WHERE email = :login OR username = :login
         LIMIT 1'
    );
    $stmt->execute(['login' => $login]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome_completo'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_username'] = $usuario['username'];
    $_SESSION['usuario_perfil'] = $usuario['perfil'];

    header('Location: dashboard.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora.';
    header('Location: login.php');
    exit;
}
