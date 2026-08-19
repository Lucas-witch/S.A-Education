<?php
declare(strict_types=1);
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Bem-vindo</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<main class="auth-shell">
    <section class="brand-side">
        <div class="logo">
            <div class="logo-mark">🎓</div>
            <div><strong>S.A</strong><span>Education</span></div>
        </div>

        <p class="brand-slogan">Aprenda, compartilhe<br>e transforme o futuro.</p>

        <div class="feature-list">
            <div class="feature"><b>👥</b><div><strong>Comunidade</strong><small>Conecte-se com estudantes e professores.</small></div></div>
            <div class="feature"><b>📚</b><div><strong>Estude</strong><small>Acesse materiais, exercícios e aulas gratuitamente.</small></div></div>
            <div class="feature"><b>❓</b><div><strong>Quizzes</strong><small>Teste seus conhecimentos e acompanhe seu desempenho.</small></div></div>
            <div class="feature"><b>🧑‍🏫</b><div><strong>Ensine</strong><small>Compartilhe seu conhecimento e ajude outros alunos.</small></div></div>
        </div>

        <div class="ods-card">
            <strong>♥ Educação de qualidade<br>para todos!</strong>
        </div>
    </section>

    <section class="auth-card welcome-card">
        <div class="mini-logo">🎓 <strong>S.A <span>Education</span></strong></div>

        <div class="welcome-copy">
            <h1>Bem-vindo ao<br>S.A Education! 👋</h1>
            <p>Sua plataforma completa para estudar, ensinar e crescer junto com a comunidade.</p>
        </div>

        <div class="hero-illustration">
            <div class="person">👨🏻‍🎓</div>
            <div class="person center">👩🏻‍💻</div>
            <div class="person">🧑🏿‍🎓</div>
            <span class="cap">🎓</span>
        </div>

        <a class="btn btn-primary" href="cadastro.php">Criar conta</a>
        <a class="btn btn-outline" href="login.php">Entrar</a>

        <p class="terms">Ao continuar, você concorda com nossos<br>
            <a href="#">Termos de Uso</a> e <a href="#">Política de Privacidade</a>.
        </p>
    </section>
</main>
</body>
</html>
