<?php require 'includes/auth.php'; require 'includes/data.php'; require_auth(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>S.A Education — Início</title><link rel="stylesheet" href="assets/css/estilo.css">
    </head>
    <body>
<div class="app"><?php require 'includes/header.php'; ?><main class="main">
<?php 
$nomeUser = $_SESSION['usuario_nome'] 
?? $_SESSION['usuario_username'] 
?? 'Usuário';
 ?>
<header class="topbar">
    <div>
        <h1>Olá, <?= htmlspecialchars($nomeUser) ?></h1>
        <p>Vamos aprender algo novo hoje?</p>
    </div>
    <div class="top-actions">
        <button class="icon-btn"><img src="assets/images/logo_SAeducation_pocket.png" alt="logo" style="width:20px;height:20px"></button>
    </div>
</header>
<section class="hero">
    <div>
        <h2>Desafie seus conhecimentos!</h2>
        <p>Participe de quizzes e ganhe pontos para subir no ranking.</p><a class="btn light" href="quiz.php">Jogar agora</a>
    </div>
    <div class="trophy"><img src="assets/images/winn-icon.png" alt="trofeu"></div>
</section>
<div class="section-title">
    <h2>Salas recentes</h2><a href="salas.php">Ver todas</a>
</div>
<div class="grid grid-3">
    <?php foreach($recentes as $r): ?><a class="card room-card" href="sala.php">
    <div class="room-icon"><?= $r['icone'] ?>
    </div>
        <div>
            <h3><?= $r['nome'] ?></h3>
            <p><?= $r['prof'] ?> · <?= $r['alunos'] ?> alunos</p>
        </div>
        <span class="arrow">›

        </span>
    </a><?php endforeach; ?>
    </div>
</main>
</div>
<?php require 'includes/footer.php'; ?>