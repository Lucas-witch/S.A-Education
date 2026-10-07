<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');

$login = trim((string) ($_POST['login'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

auth_debug_log('login_attempt', [
    'login' => $login,
    'csrf_present' => isset($_POST['csrf']) && $_POST['csrf'] !== '',
    'post_keys' => array_keys($_POST),
]);

if ($login === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha todos os campos.';
    header('Location: login.php');
    exit;
}

try {
    /*
     * A autenticação consulta somente a tabela usuarios e usa dois
     * placeholders diferentes. Isso evita HY093 com prepared statements
     * nativos e também evita que colunas opcionais de planos/progresso
     * impeçam o login em bancos criados por versões anteriores do projeto.
     */
    $stmt = $pdo->prepare(
        'SELECT u.*
         FROM usuarios u
         WHERE LOWER(u.email) = LOWER(:login_email)
            OR LOWER(u.username) = LOWER(:login_username)
         LIMIT 1'
    );

    $stmt->execute([
        'login_email' => $login,
        'login_username' => $login,
    ]);

    $usuario = $stmt->fetch();

    auth_debug_log('login_query_result', [
        'usuario_encontrado' => (bool) $usuario,
        'perfil' => $usuario['perfil'] ?? null,
        'email' => $usuario['email'] ?? null,
        'username' => $usuario['username'] ?? null,
    ]);

    if (!$usuario || !isset($usuario['senha'])) {
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    $senhaValida = password_verify($senha, (string) $usuario['senha']);

    auth_debug_log('password_verify', [
        'senha_valida' => $senhaValida,
        'perfil' => $usuario['perfil'] ?? null,
    ]);

    if (!$senhaValida || !is_login_perfil_permitido($usuario['perfil'] ?? '')) {
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    try {
        set_authenticated_session($usuario);
    } catch (InvalidArgumentException $e) {
        error_log('[S.A Education][login_session] ' . $e->getMessage());
        auth_debug_log('login_session_error', ['message' => $e->getMessage()]);
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    auth_debug_log('login_success', [
        'usuario_id' => $_SESSION['usuario_id'] ?? null,
        'perfil' => $_SESSION['usuario_perfil'] ?? null,
    ]);

    header('Location: perfil-dashboard.php');
    exit;
} catch (PDOException $e) {
    // Mantém os detalhes fora da tela, mas registra a causa real no log do PHP/Apache.
    error_log('[S.A Education][login][PDO] ' . $e->getMessage());
    auth_debug_log('login_exception', ['message' => $e->getMessage()]);
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora.';
    header('Location: login.php');
    exit;
}
