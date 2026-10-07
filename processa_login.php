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
    'csrf_session' => $_SESSION['csrf_token'] ?? null,
    'post_keys' => array_keys($_POST),
]);

if ($login === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha todos os campos.';
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome_completo, u.email, u.username, u.senha, u.perfil, u.plano_id, u.premium_ativo,
                u.perfil_imagem, u.pontos, u.moedas, u.seguidores, u.grupos, u.horas_aulas, u.livros_lidos,
                u.quizzes_pontos, u.atividades_pontos, u.artigos_pontuacao, u.escritos_pontuacao,
                u.ensaios_pontuacao, u.redacoes_pontuacao, u.acertos_timeline, u.erros_timeline,
                p.nome AS plano_nome, p.pode_postar_aulas_ilimitadas, p.pode_criar_salas,
                p.pode_criar_comunidades_privadas, p.limite_video_aula_free
         FROM usuarios u
         LEFT JOIN planos p ON p.id = u.plano_id
         WHERE (LOWER(u.email) = LOWER(:login) OR LOWER(u.username) = LOWER(:login))
           AND u.perfil IN (\'estudante\', \'professor\')
         LIMIT 1'
    );
    $stmt->execute(['login' => $login]);
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

    $senhaValida = password_verify($senha, $usuario['senha']);
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
        auth_debug_log('login_session_error', ['message' => $e->getMessage()]);
        $_SESSION['erro_login'] = 'E-mail/usuário ou senha inválidos.';
        header('Location: login.php');
        exit;
    }

    auth_debug_log('login_success', [
        'usuario_id' => $_SESSION['usuario_id'],
        'perfil' => $_SESSION['usuario_perfil'],
    ]);

    header('Location: dashboard.php');
    exit;
} catch (PDOException $e) {
    auth_debug_log('login_exception', ['message' => $e->getMessage()]);
    $_SESSION['erro_login'] = 'Não foi possível realizar o login agora.';
    header('Location: login.php');
    exit;
}

