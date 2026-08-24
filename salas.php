<?php
require 'includes/auth.php'; require 'includes/data.php'; require_auth();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Salas</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="app">
  <?php require 'includes/header.php'; ?>
  <main class="main">
    <header class="topbar"><div><h1>Salas</h1><p>Encontre uma sala para estudar</p></div></header>
    <div class="section-title"><h2>Salas disponíveis</h2></div>
    <div class="grid grid-3">
      <?php foreach($salas as $s): ?>
        <a class="card room-card" href="sala.php">
          <div class="room-icon"><img src="<?= esc($s['icone']) ?>" alt="" style="width:38px;height:38px;object-fit:contain"></div>
          <div><h3><?= esc($s['nome']) ?></h3><p><?= esc($s['prof']) ?> · <?= intval($s['alunos']) ?> alunos</p></div>
          <span class="arrow">›</span>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<?php require 'includes/footer.php'; ?>
</body>
</html>
