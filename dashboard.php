<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();

$perfilAtual = current_user_perfil();
$nomeUsuario = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário';
$perfilNome = perfil_label($perfilAtual ?: 'estudante');

$welcomeTitle = [
    'estudante' => 'Bem-vindo à sua jornada de estudo!',
    'professor' => 'Painel do professor em ação!',
    'instituicao' => 'Painel institucional da sua escola!',
][$perfilAtual] ?? 'Bem-vindo!';

$welcomeText = [
    'estudante' => 'Sua conta está ativa e pronta para acompanhar salas, materiais e desafios.',
    'professor' => 'Você pode acompanhar salas, materiais e o progresso da sua turma em um só lugar.',
    'instituicao' => 'Acompanhe o funcionamento das salas, materiais e atividades da instituição.',
][$perfilAtual] ?? 'Sua conta está ativa.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Plataforma</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<main class="dashboard">
    <div class="dashboard-top">
        <div class="logo">
            <div class="logo-mark"><img src="assets/images/logo_mark.svg" alt="S.A" style="width:36px;height:36px"></div>
            <div><strong>S.A</strong><span>Education</span></div>
        </div>
        <a href="logout.php" class="btn btn-outline compact">Sair</a>
    </div>

    <section class="welcome-panel">
        <div>
            <span class="eyebrow">S.A EDUCATION</span>
            <h1>Olá, <?= htmlspecialchars((string) $nomeUsuario) ?>!</h1>
            <p><?= htmlspecialchars($welcomeText) ?></p>
            <div class="profile-pill">
                <img src="assets/images/icons/<?= $perfilAtual === 'professor' ? 'professor' : ($perfilAtual === 'instituicao' ? 'institution' : 'student') ?>.svg" alt="perfil" style="width:18px;height:18px;vertical-align:middle;margin-right:8px">
                <?= htmlspecialchars($perfilNome) ?>
                · @<?= htmlspecialchars((string) ($_SESSION['usuario_username'] ?? 'usuario')) ?>
            </div>
        </div>
        <div class="dashboard-art">📚✨</div>
    </section>

    <section class="card" style="margin-top:22px;padding:20px;">
        <h2 style="margin:0 0 12px;">Resumo do perfil</h2>
        <p style="margin:0; color:var(--muted);">
            <?= htmlspecialchars($welcomeTitle) ?>
        </p>
    </section>
</main>
</body>
</html>
