<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Feature flag: para desenvolvimento local, habilite DEV_MODE = true
if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}

// Se estiver em modo de desenvolvimento e não houver usuário autenticado,
// preencha uma sessão de demonstração. Em produção DEV_MODE deve permanecer false.
if (DEV_MODE && !isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nome'] = 'Ana Clara';
    $_SESSION['usuario_email'] = 'ana.clara@email.com';
    $_SESSION['usuario_username'] = 'anaclara';
    $_SESSION['usuario_perfil'] = 'estudante';
}

// Função utilitária para exigir autenticação em páginas privadas
function require_auth(): void {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Não produzir saída aqui — evita problemas com headers
