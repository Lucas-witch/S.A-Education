<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function set_authenticated_session(array $usuario): void {
    $perfil = normalize_perfil($usuario['perfil'] ?? '');

    if (!is_login_perfil_permitido($perfil)) {
        throw new InvalidArgumentException('Perfil de usuário não permitido para login local.');
    }

    if (!isset($usuario['id'])) {
        throw new InvalidArgumentException('Usuário sem identificador válido.');
    }

    $acertosTimeline = $usuario['acertos_timeline'] ?? null;
    if (is_string($acertosTimeline) && $acertosTimeline !== '') {
        $decoded = json_decode($acertosTimeline, true);
        $acertosTimeline = is_array($decoded) ? array_map('intval', $decoded) : null;
    }

    $errosTimeline = $usuario['erros_timeline'] ?? null;
    if (is_string($errosTimeline) && $errosTimeline !== '') {
        $decoded = json_decode($errosTimeline, true);
        $errosTimeline = is_array($decoded) ? array_map('intval', $decoded) : null;
    }

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
    $_SESSION['perfil_imagem'] = (string) ($usuario['perfil_imagem'] ?? 'assets/images/icons/user-icon.png');
    $_SESSION['usuario_foto'] = $_SESSION['perfil_imagem'];
    $_SESSION['usuario_pontos'] = (int) ($usuario['pontos'] ?? 0);
    $_SESSION['usuario_moedas'] = (int) ($usuario['moedas'] ?? 0);
    $_SESSION['usuario_seguidores'] = (int) ($usuario['seguidores'] ?? 0);
    $_SESSION['usuario_grupos'] = (int) ($usuario['grupos'] ?? 0);
    $_SESSION['usuario_horas_aulas'] = (int) ($usuario['horas_aulas'] ?? 0);
    $_SESSION['usuario_livros_lidos'] = (int) ($usuario['livros_lidos'] ?? 0);
    $_SESSION['usuario_quizzes_pontos'] = (int) ($usuario['quizzes_pontos'] ?? 0);
    $_SESSION['usuario_atividades_pontos'] = (int) ($usuario['atividades_pontos'] ?? 0);
    $_SESSION['usuario_artigos_pontuacao'] = $usuario['artigos_pontuacao'] ?? null;
    $_SESSION['usuario_escritos_pontuacao'] = $usuario['escritos_pontuacao'] ?? null;
    $_SESSION['usuario_ensaios_pontuacao'] = $usuario['ensaios_pontuacao'] ?? null;
    $_SESSION['usuario_redacoes_pontuacao'] = $usuario['redacoes_pontuacao'] ?? null;
    $_SESSION['usuario_acertos_timeline'] = is_array($acertosTimeline) ? $acertosTimeline : [35, 42, 48, 58, 66, 74, 82];
    $_SESSION['usuario_erros_timeline'] = is_array($errosTimeline) ? $errosTimeline : [52, 48, 42, 36, 29, 23, 18];
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
