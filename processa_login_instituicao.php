<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
send_private_no_store_headers();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf($_POST['csrf'] ?? null);
$login = trim((string) ($_POST['login'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$fail = static function (): never {
    $_SESSION['erro_login_instituicao'] = 'E-mail/usuário ou senha inválidos.';
    header('Location: login-instituicao.php');
    exit;
};

if ($login === '' || $senha === '' || mb_strlen($login, 'UTF-8') > 255) {
    $fail();
}

try {
    if (
        auth_rate_limit_remaining($pdo, 'institution-login-ip', $clientIp, 20, 900) > 0
        || auth_rate_limit_remaining($pdo, 'institution-login-identifier', strtolower($login), 10, 900) > 0
    ) {
        $fail();
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome_completo, email, username, senha, perfil, auth_version
         FROM usuarios
         WHERE (LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:username))
           AND perfil = :perfil
         LIMIT 1'
    );
    $stmt->execute([
        'email' => $login,
        'username' => $login,
        'perfil' => 'instituicao',
    ]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, (string) $usuario['senha'])) {
        auth_rate_limit_record_failure($pdo, 'institution-login-ip', $clientIp, 20, 900);
        auth_rate_limit_record_failure($pdo, 'institution-login-identifier', strtolower($login), 10, 900);
        $fail();
    }

    $organization = $pdo->prepare('SELECT nome FROM instituicoes WHERE usuario_id = :usuario_id LIMIT 1');
    $organization->execute(['usuario_id' => (int) $usuario['id']]);
    $institution = $organization->fetch();
    if (!$institution) {
        auth_rate_limit_record_failure($pdo, 'institution-login-ip', $clientIp, 20, 900);
        $fail();
    }

    set_institution_authenticated_session([
        'id' => (int) $usuario['id'],
        'nome_completo' => (string) $usuario['nome_completo'],
        'nome_instituicao' => (string) $institution['nome'],
        'email' => (string) $usuario['email'],
        'auth_version' => (int) $usuario['auth_version'],
        'perfil' => (string) $usuario['perfil'],
    ]);
    auth_rate_limit_clear($pdo, 'institution-login-identifier', strtolower($login));
    header('Location: instituicao-dashboard.php');
    exit;
} catch (PDOException $error) {
    error_log('[S.A Education][institution_login][PDO] ' . $error->getMessage());
    $_SESSION['erro_login_instituicao'] = 'Não foi possível realizar o login agora. Tente novamente.';
    header('Location: login-instituicao.php');
    exit;
} catch (RuntimeException $error) {
    error_log('[S.A Education][institution_login] ' . $error->getMessage());
    $_SESSION['erro_login_instituicao'] = 'Não foi possível realizar o login agora. Tente novamente.';
    header('Location: login-instituicao.php');
    exit;
}
