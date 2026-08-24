<?php
declare(strict_types=1);

session_start();
require_once 'includes/helpers.php';
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.php');
    exit;
}

// Verifica CSRF token
verify_csrf($_POST['csrf'] ?? '');

$nome = trim($_POST['nome_completo'] ?? '');
$email = trim(strtolower($_POST['email'] ?? ''));
$username = trim($_POST['username'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';
$perfil = $_POST['perfil'] ?? 'estudante';
$interesses = trim($_POST['interesses'] ?? '');

$_SESSION['dados_cadastro'] = [
    'nome_completo' => $nome,
    'email' => $email,
    'username' => $username,
    'perfil' => $perfil,
    'interesses' => $interesses
];

$voltar = function(string $mensagem): never {
    $_SESSION['erro_cadastro'] = $mensagem;
    header('Location: cadastro.php');
    exit;
};

if ($nome === '' || mb_strlen($nome) < 3) {
    $voltar('Digite seu nome completo.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $voltar('Digite um e-mail válido.');
}

if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
    $voltar('O nome de usuário possui caracteres inválidos.');
}

if (!in_array($perfil, ['estudante', 'professor'], true)) {
    $voltar('Selecione um perfil válido.');
}

if (strlen($senha) < 8 || !preg_match('/[A-Z]/', $senha) || !preg_match('/[\d\W]/', $senha)) {
    $voltar('A senha precisa ter 8 caracteres, uma letra maiúscula e um número ou símbolo.');
}

if ($senha !== $confirmar) {
    $voltar('As senhas não coincidem.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = :email OR username = :username LIMIT 1'
    );
    $stmt->execute(['email' => $email, 'username' => $username]);

    if ($stmt->fetch()) {
        $voltar('O e-mail ou nome de usuário já está cadastrado.');
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nome_completo, email, username, senha, perfil, interesses)
         VALUES (:nome, :email, :username, :senha, :perfil, :interesses)'
    );

    $stmt->execute([
        'nome' => $nome,
        'email' => $email,
        'username' => $username,
        'senha' => $senhaHash,
        'perfil' => $perfil,
        'interesses' => $interesses !== '' ? $interesses : null
    ]);

    unset($_SESSION['dados_cadastro']);
    $_SESSION['sucesso'] = 'Conta criada com sucesso! Agora faça login.';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['erro_cadastro'] = 'Não foi possível criar a conta agora. Tente novamente.';
    header('Location: cadastro.php');
    exit;
}
