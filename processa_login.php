<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');

$login = trim((string) ($_POST['login'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

auth_debug_log('login_attempt', [
    'login_identifier_present' => $login !== '',
    'csrf_present' => isset($_POST['csrf']) && $_POST['csrf'] !== '',
]);

if ($login === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha todos os campos.';
    header('Location: login.php');
    exit;
}

try {
    if (
        auth_rate_limit_remaining($pdo, 'login-ip', $clientIp, 20, 900) > 0
        || auth_rate_limit_remaining($pdo, 'login-identifier', strtolower($login), 10, 900) > 0
    ) {
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos. Aguarde alguns minutos antes de tentar novamente.';
        header('Location: login.php');
        exit;
    }

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
    ]);

    if (!$usuario || !isset($usuario['senha'])) {
        auth_rate_limit_record_failure($pdo, 'login-ip', $clientIp, 20, 900);
        auth_rate_limit_record_failure($pdo, 'login-identifier', strtolower($login), 10, 900);
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    $senhaValida = password_verify($senha, (string) $usuario['senha']);

    auth_debug_log('password_verify', [
        'senha_valida' => $senhaValida,
    ]);

    if (!$senhaValida || !is_login_perfil_permitido($usuario['perfil'] ?? '')) {
        auth_rate_limit_record_failure($pdo, 'login-ip', $clientIp, 20, 900);
        auth_rate_limit_record_failure($pdo, 'login-identifier', strtolower($login), 10, 900);
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

    auth_rate_limit_clear($pdo, 'login-identifier', strtolower($login));
    auth_debug_log('login_success');

    header('Location: perfil-dashboard.php');
    exit;
} catch (PDOException $e) {
    // Mantém os detalhes fora da tela, mas registra a causa real no log do PHP/Apache.
    error_log('[S.A Education][login][PDO] ' . $e->getMessage());
    auth_debug_log('login_exception', ['message' => $e->getMessage()]);
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora.';
    header('Location: login.php');
    exit;
} catch (RuntimeException $e) {
    error_log('[S.A Education][login_rate_limit] ' . $e->getMessage());
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora. Tente novamente.';
    header('Location: login.php');
    exit;
}
