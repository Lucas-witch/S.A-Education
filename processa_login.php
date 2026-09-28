<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');

$login = trim((string)($_POST['login'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($login === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha todos os campos.';
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome_completo, u.email, u.username, u.senha, u.perfil, u.plano_id, u.premium_ativo,
                p.nome AS plano_nome, p.pode_postar_aulas_ilimitadas, p.pode_criar_salas,
                p.pode_criar_comunidades_privadas, p.limite_video_aula_free
         FROM usuarios u
         LEFT JOIN planos p ON p.id = u.plano_id
         WHERE LOWER(u.email) = LOWER(:login) OR LOWER(u.username) = LOWER(:login)
         LIMIT 1'
    );
    $stmt->execute(['login' => $login]);
    $usuario = $stmt->fetch();

    if (!$usuario || !isset($usuario['senha']) || !password_verify($senha, $usuario['senha'])) {
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome_completo'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_username'] = $usuario['username'];
    $_SESSION['usuario_perfil'] = $usuario['perfil'];
    $_SESSION['usuario_plano_id'] = (int) ($usuario['plano_id'] ?? 1);
    $_SESSION['usuario_plano'] = $usuario['plano_nome'] ?? 'free';
    $_SESSION['usuario_premium_ativo'] = (bool) ($usuario['premium_ativo'] ?? false);
    $_SESSION['usuario_pode_criar_salas'] = (bool) ($usuario['pode_criar_salas'] ?? false);
    $_SESSION['usuario_pode_criar_comunidades_privadas'] = (bool) ($usuario['pode_criar_comunidades_privadas'] ?? false);
    $_SESSION['usuario_pode_postar_aulas_ilimitadas'] = (bool) ($usuario['pode_postar_aulas_ilimitadas'] ?? false);

    header('Location: dashboard.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora.';
    header('Location: login.php');
    exit;
}
