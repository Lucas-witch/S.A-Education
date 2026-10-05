<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}

function current_user_perfil(): string {
    $perfil = $_SESSION['usuario_perfil'] ?? '';
    return is_string($perfil) ? strtolower(trim($perfil)) : '';
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
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_perfil(array $perfis): void {
    require_auth();

    $perfilAtual = current_user_perfil();
    if ($perfilAtual === '' || !in_array($perfilAtual, $perfis, true)) {
        http_response_code(403);
        header('Location: dashboard.php');
        exit;
    }
}
