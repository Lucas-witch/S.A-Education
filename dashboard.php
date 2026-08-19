<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Plataforma</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<main class="dashboard">
    <div class="dashboard-top">
        <div class="logo">
            <div class="logo-mark">🎓</div>
            <div><strong>S.A</strong><span>Education</span></div>
        </div>
        <a href="logout.php" class="btn btn-outline compact">Sair</a>
    </div>

    <section class="welcome-panel">
        <div>
            <span class="eyebrow">S.A EDUCATION</span>
            <h1>Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>! 👋</h1>
            <p>Sua conta está ativa. Agora você pode explorar a plataforma.</p>
            <div class="profile-pill">
                <?= $_SESSION['usuario_perfil'] === 'professor' ? '🧑‍🏫 Professor' : '🎓 Estudante' ?>
                · @<?= htmlspecialchars($_SESSION['usuario_username']) ?>
            </div>
        </div>
        <div class="dashboard-art">📚✨</div>
    </section>
</main>
</body>
</html>
