<?php
require 'includes/auth.php';
require 'includes/data.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sala — Matemática Avançada</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
  <div class="app">
    <?php require 'includes/header.php'; ?>
    <main class="main">
      <header class="topbar">
        <a href="salas.php">← Voltar</a>
        <div class="top-actions">
          <button class="icon-btn" type="button">↗</button>
          <button class="icon-btn" type="button">⋮</button>
        </div>
      </header>

      <section class="room-banner">
        <div class="big-icon">π</div>
        <div>
          <h1>Matemática Avançada</h1>
          <p>Prof. Lucas · 24 alunos · Matemática</p>
        </div>
      </section>

      <nav class="tabs">
        <a class="active">Sobre</a>
        <a>Aulas</a>
        <a>Quiz</a>
        <a>Membros</a>
      </nav>

      <section style="max-width:800px;margin-top:25px;">
        <h2>Sobre a sala</h2>
        <p>Espaço para discutir e aprender sobre tópicos avançados de matemática.</p>

        <h2 style="margin-top:25px;">Próxima aula</h2>
        <div class="card room-card">
          <div class="room-icon">▣</div>
          <div>
            <h3>Equações Diferenciais</h3>
            <p>12/05 · 19:00</p>
          </div>
          <span class="arrow">›</span>
        </div>

        <h2 style="margin-top:25px;">Atividades recentes</h2>
        <div class="card room-card">
          <div class="room-icon">☑</div>
          <div>
            <h3>Novo quiz disponível!</h3>
            <p>Funções e Gráficos</p>
          </div>
          <a class="btn light" href="quiz.php">Entrar na sala</a>
        </div>
      </section>
    </main>
  </div>
  <?php require 'includes/footer.php'; ?>
</body>
</html>

