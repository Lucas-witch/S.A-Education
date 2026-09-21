<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro-instituicao.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');

$nome = trim($_POST['nome'] ?? '');
$tipo = $_POST['tipo'] ?? '';
$natureza = $_POST['natureza'] ?? '';
$responsavel = trim($_POST['responsavel'] ?? '');
$email = trim(strtolower($_POST['email'] ?? ''));
$username = trim($_POST['username'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';

$_SESSION['dados_instituicao'] = compact(
    'nome',
    'tipo',
    'natureza',
    'responsavel',
    'email',
    'username',
    'telefone',
    'cidade'
);

$voltar = function (string $mensagem): never {
    $_SESSION['erro_instituicao'] = $mensagem;
    header('Location: cadastro-instituicao.php');
    exit;
};

if ($nome === '' || mb_strlen($nome) < 3) {
    $voltar('Digite o nome da instituição.');
}

if (!in_array($tipo, ['fundamental', 'medio', 'faculdade', 'universidade', 'outro'], true)) {
    $voltar('Selecione um tipo de ensino válido.');
}

if (!in_array($natureza, ['publica', 'particular'], true)) {
    $voltar('Selecione se a instituição é pública ou particular.');
}

if ($responsavel === '' || mb_strlen($responsavel) < 3) {
    $voltar('Digite o nome do responsável.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $voltar('Digite um e-mail institucional válido.');
}

if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
    $voltar('O nome de usuário possui caracteres inválidos.');
}

if ($cidade === '') {
    $voltar('Digite a cidade da instituição.');
}

if (strlen($senha) < 8 || !preg_match('/[A-Z]/', $senha) || !preg_match('/[\d\W]/', $senha)) {
    $voltar('A senha precisa ter 8 caracteres, uma letra maiúscula e um número ou símbolo.');
}

if ($senha !== $confirmar) {
    $voltar('As senhas não coincidem.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = :email OR username = :username LIMIT 1'
    );
    $stmt->execute(['email' => $email, 'username' => $username]);

    if ($stmt->fetch()) {
        $pdo->rollBack();
        $voltar('O e-mail ou nome de usuário já está cadastrado.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nome_completo, email, username, senha, perfil)
         VALUES (:nome, :email, :username, :senha, :perfil)'
    );
    $stmt->execute([
        'nome' => $responsavel,
        'email' => $email,
        'username' => $username,
        'senha' => password_hash($senha, PASSWORD_DEFAULT),
        'perfil' => 'instituicao'
    ]);

    $usuarioId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'INSERT INTO instituicoes (usuario_id, nome, tipo, natureza, responsavel, telefone, cidade)
         VALUES (:usuario_id, :nome, :tipo, :natureza, :responsavel, :telefone, :cidade)'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'nome' => $nome,
        'tipo' => $tipo,
        'natureza' => $natureza,
        'responsavel' => $responsavel,
        'telefone' => $telefone !== '' ? $telefone : null,
        'cidade' => $cidade
    ]);

    $pdo->commit();
    unset($_SESSION['dados_instituicao']);
    $_SESSION['sucesso'] = 'Acesso institucional criado com sucesso! Agora faça login.';
    header('Location: login.php');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['erro_instituicao'] = 'Não foi possível criar o cadastro agora. Tente novamente.';
    header('Location: cadastro-instituicao.php');
    exit;
}
