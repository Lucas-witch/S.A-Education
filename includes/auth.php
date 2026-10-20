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
    $_SESSION['usuario_nome'] = (string) ($usuario['nome_completo'] ?? $usuario['nome'] ?? '');
    $_SESSION['usuario_email'] = (string) ($usuario['email'] ?? '');
    $_SESSION['usuario_username'] = (string) ($usuario['username'] ?? '');
    $_SESSION['usuario_perfil'] = $perfil;
    $_SESSION['usuario_plano_id'] = isset($usuario['plano_id']) ? (int) $usuario['plano_id'] : 1;
    $_SESSION['usuario_plano'] = (string) ($usuario['plano_nome'] ?? 'free');
    $_SESSION['usuario_premium_ativo'] = (bool) ($usuario['premium_ativo'] ?? false);
    $_SESSION['usuario_pode_criar_salas'] = (bool) ($usuario['pode_criar_salas'] ?? false);
    $_SESSION['usuario_pode_criar_comunidades_privadas'] = (bool) ($usuario['pode_criar_comunidades_privadas'] ?? false);
    $_SESSION['usuario_pode_postar_aulas_ilimitadas'] = (bool) ($usuario['pode_postar_aulas_ilimitadas'] ?? false);
    sync_authenticated_profile_session([
        'id' => (int) $usuario['id'],
        'nome_completo' => (string) ($usuario['nome_completo'] ?? $usuario['nome'] ?? ''),
        'email' => (string) ($usuario['email'] ?? ''),
        'username' => (string) ($usuario['username'] ?? ''),
        'perfil_imagem' => (string) ($usuario['perfil_imagem'] ?? ''),
    ]);
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

function perfil_label(string $perfil): string {
    $mapa = [
        'estudante' => 'Estudante',
        'professor' => 'Professor',
        'instituicao' => 'Instituição',
    ];

    return $mapa[$perfil] ?? 'Usuário';
}

function require_auth(): void {
    $usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($usuarioId === false || $usuarioId <= 0) {
        $_SESSION = [];
        header('Location: login.php');
        exit;
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
}
