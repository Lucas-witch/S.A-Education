<?php
require_once __DIR__ . '/helpers.php';
session_configure_security();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
send_private_no_store_headers();

if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}

const LOGIN_PERFIS_PERMITIDOS = ['estudante', 'professor'];

function configured_admin_user_id(): ?int {
    $configuredId = trim((string) (getenv('APP_ADMIN_USER_ID') ?: ''));
    if ($configuredId === '') {
        return null;
    }
    if (!ctype_digit($configuredId) || (int) $configuredId <= 0) {
        throw new RuntimeException('APP_ADMIN_USER_ID deve conter o ID positivo de uma conta existente.');
    }

    return (int) $configuredId;
}

function is_admin_user(): bool {
    $userId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    return $userId !== false && $userId > 0 && $userId === configured_admin_user_id();
}

function usuario_tem_acesso_premium(): bool {
    return is_admin_user() || ($_SESSION['usuario_premium_ativo'] ?? false) === true;
}

function current_user_perfil(): string {
    $perfil = $_SESSION['usuario_perfil'] ?? '';
    return is_string($perfil) ? strtolower(trim($perfil)) : '';
}

function normalize_perfil(?string $perfil): string {
    return is_string($perfil) ? strtolower(trim($perfil)) : '';
}

function is_login_perfil_permitido(?string $perfil): bool {
    $perfilNormalizado = normalize_perfil($perfil);
    return in_array($perfilNormalizado, LOGIN_PERFIS_PERMITIDOS, true);
}

function sync_authenticated_profile_session(array $usuario): void {
    $sessionId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $usuarioId = filter_var($usuario['id'] ?? null, FILTER_VALIDATE_INT);
    if ($sessionId === false || $usuarioId === false || $sessionId <= 0 || $sessionId !== $usuarioId) {
        throw new InvalidArgumentException('O usuário do perfil não corresponde à sessão autenticada.');
    }

    $imagem = trim((string) ($usuario['perfil_imagem'] ?? ''));
    if ($imagem === '') {
        $imagem = 'assets/images/icons/user-icon.png';
    }

    $_SESSION['usuario_nome'] = (string) ($usuario['nome_completo'] ?? '');
    $_SESSION['usuario_email'] = (string) ($usuario['email'] ?? '');
    $_SESSION['usuario_username'] = (string) ($usuario['username'] ?? '');
    $_SESSION['perfil_imagem'] = $imagem;
    $_SESSION['usuario_foto'] = $imagem;
    $_SESSION['usuario_imagem'] = $imagem;
}

function set_authenticated_session(array $usuario): void {
    $perfil = normalize_perfil($usuario['perfil'] ?? '');

    if (!is_login_perfil_permitido($perfil)) {
        throw new InvalidArgumentException('Perfil de usuário não permitido para login local.');
    }

    if (!isset($usuario['id'])) {
        throw new InvalidArgumentException('Usuário sem identificador válido.');
    }

    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_auth_version'] = (int) ($usuario['auth_version'] ?? 0);
    $_SESSION['usuario_nome'] = (string) ($usuario['nome_completo'] ?? $usuario['nome'] ?? '');
    $_SESSION['usuario_email'] = (string) ($usuario['email'] ?? '');
    $_SESSION['usuario_username'] = (string) ($usuario['username'] ?? '');
    $_SESSION['usuario_perfil'] = $perfil;
    $_SESSION['usuario_is_admin'] = (int) $usuario['id'] === configured_admin_user_id();
    $_SESSION['usuario_plano_id'] = isset($usuario['plano_id']) ? (int) $usuario['plano_id'] : 1;
    $_SESSION['usuario_plano'] = $_SESSION['usuario_is_admin']
        ? 'administrador'
        : (string) ($usuario['plano_nome'] ?? 'free');
    $_SESSION['usuario_premium_ativo'] = $_SESSION['usuario_is_admin'] || (bool) ($usuario['premium_ativo'] ?? false);
    $_SESSION['usuario_pode_criar_salas'] = $_SESSION['usuario_is_admin'] || (bool) ($usuario['pode_criar_salas'] ?? false);
    $_SESSION['usuario_pode_criar_comunidades_privadas'] = $_SESSION['usuario_is_admin'] || (bool) ($usuario['pode_criar_comunidades_privadas'] ?? false);
    $_SESSION['usuario_pode_postar_aulas_ilimitadas'] = $_SESSION['usuario_is_admin'] || (bool) ($usuario['pode_postar_aulas_ilimitadas'] ?? false);
    $_SESSION['usuario_pode_postar_artigos'] = $_SESSION['usuario_is_admin'] || (bool) ($usuario['pode_postar_artigos'] ?? false);
    sync_authenticated_profile_session([
        'id' => (int) $usuario['id'],
        'nome_completo' => (string) ($usuario['nome_completo'] ?? $usuario['nome'] ?? ''),
        'email' => (string) ($usuario['email'] ?? ''),
        'username' => (string) ($usuario['username'] ?? ''),
        'perfil_imagem' => (string) ($usuario['perfil_imagem'] ?? ''),
    ]);
}

function set_institution_authenticated_session(array $usuario): void {
    if (normalize_perfil($usuario['perfil'] ?? '') !== 'instituicao' || !isset($usuario['id'])) {
        throw new InvalidArgumentException('A conta não é uma instituição válida.');
    }

    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['instituicao_usuario_id'] = (int) $usuario['id'];
    $_SESSION['instituicao_auth_version'] = (int) ($usuario['auth_version'] ?? 0);
    $_SESSION['instituicao_nome'] = (string) ($usuario['nome_instituicao'] ?? $usuario['nome_completo'] ?? '');
    $_SESSION['instituicao_email'] = (string) ($usuario['email'] ?? '');
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function is_estudante(): bool {
    return current_user_perfil() === 'estudante';
}

function is_professor(): bool {
    return current_user_perfil() === 'professor';
}

function is_instituicao(): bool {
    return current_user_perfil() === 'instituicao';
}

function authenticated_session_is_current(PDO $pdo): bool {
    $usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $sessionVersion = filter_var($_SESSION['usuario_auth_version'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioId === false || $usuarioId <= 0 || $sessionVersion === false || $sessionVersion < 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT perfil, auth_version FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $usuarioId]);
    $usuario = $stmt->fetch();
    return $usuario !== false
        && is_login_perfil_permitido((string) $usuario['perfil'])
        && (int) $usuario['auth_version'] === $sessionVersion;
}

function institution_session_is_current(PDO $pdo): bool {
    $usuarioId = filter_var($_SESSION['instituicao_usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $sessionVersion = filter_var($_SESSION['instituicao_auth_version'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioId === false || $usuarioId <= 0 || $sessionVersion === false || $sessionVersion < 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT perfil, auth_version FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $usuarioId]);
    $usuario = $stmt->fetch();
    return $usuario !== false
        && normalize_perfil((string) $usuario['perfil']) === 'instituicao'
        && (int) $usuario['auth_version'] === $sessionVersion;
}

function invalidate_current_session(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function perfil_label(string $perfil): string {
    if (is_admin_user()) {
        return 'Administrador';
    }

    $mapa = [
        'estudante' => 'Estudante',
        'professor' => 'Professor',
        'instituicao' => 'Instituição',
    ];

    return $mapa[$perfil] ?? 'Usuário';
}

function require_auth(): void {
    global $pdo;

    $usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioId === false || $usuarioId <= 0) {
        $institutionId = filter_var($_SESSION['instituicao_usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if ($institutionId !== false && $institutionId > 0) {
            header('Location: instituicao-dashboard.php');
            exit;
        }
        $_SESSION = [];
        header('Location: login.php');
        exit;
    }

    require_once __DIR__ . '/../conexao.php';
    try {
        $sessionIsCurrent = authenticated_session_is_current($pdo);
    } catch (PDOException $error) {
        error_log('[S.A Education][session_validation][PDO] ' . $error->getMessage());
        http_response_code(503);
        exit('Não foi possível validar sua sessão agora. Tente novamente.');
    }
    if (!$sessionIsCurrent) {
        invalidate_current_session();
        header('Location: login.php');
        exit;
    }

    require_once __DIR__ . '/permissions.php';
    try {
        refresh_user_permission_session($pdo, (int) $_SESSION['usuario_id']);
    } catch (PDOException $error) {
        error_log('[S.A Education][permission_session][PDO] ' . $error->getMessage());
        http_response_code(503);
        exit('Não foi possível validar suas permissões agora. Tente novamente.');
    }
}

function require_institution_auth(): void {
    global $pdo;

    $usuarioId = filter_var($_SESSION['instituicao_usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioId === false || $usuarioId <= 0) {
        $studentId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if ($studentId !== false && $studentId > 0) {
            header('Location: perfil-dashboard.php');
            exit;
        }
        $_SESSION = [];
        header('Location: login-instituicao.php');
        exit;
    }

    require_once __DIR__ . '/../conexao.php';
    try {
        $sessionIsCurrent = institution_session_is_current($pdo);
    } catch (PDOException $error) {
        error_log('[S.A Education][institution_session_validation][PDO] ' . $error->getMessage());
        http_response_code(503);
        exit('Não foi possível validar sua sessão agora. Tente novamente.');
    }
    if (!$sessionIsCurrent) {
        invalidate_current_session();
        header('Location: login-instituicao.php');
        exit;
    }

    require_once __DIR__ . '/permissions.php';
    try {
        $permissions = resolve_account_permissions($pdo, (int) $_SESSION['instituicao_usuario_id']);
        $_SESSION['instituicao_validada'] = $permissions['institution_validated'];
        $_SESSION['instituicao_assinatura_ativa'] = $permissions['premium'];
    } catch (PDOException $error) {
        error_log('[S.A Education][institution_permissions][PDO] ' . $error->getMessage());
        http_response_code(503);
        exit('Não foi possível validar as permissões institucionais agora. Tente novamente.');
    }
}

function require_perfil(array $perfis): void {
    require_auth();

    $perfilAtual = current_user_perfil();
    if ($perfilAtual === '' || !in_array($perfilAtual, $perfis, true)) {
        http_response_code(403);
        header('Location: perfil-dashboard.php');
        exit;
    }
    if ($perfilAtual === 'professor' && ($_SESSION['usuario_aprovado'] ?? false) !== true) {
        http_response_code(403);
        exit('O perfil de professor precisa ser aprovado antes de acessar esta funcionalidade.');
    }
}
