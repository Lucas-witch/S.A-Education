<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <a class="logo" href="index.php">S.A<small>education</small></a>
  <nav class="nav">
    <a class="<?= $current==='index.php'?'active':'' ?>" href="index.php"><span class="icon">⌂</span>Início</a>
    <a class="<?= in_array($current,['salas.php','sala.php','criar-sala.php','entrar-sala.php'])?'active':'' ?>" href="salas.php"><span class="icon">▦</span>Salas</a>
    <a class="<?= $current==='quiz.php'?'active':'' ?>" href="quiz.php"><span class="icon">☑</span>Quiz</a>
    <a class="<?= $current==='ranking.php'?'active':'' ?>" href="ranking.php"><span class="icon">♜</span>Ranking</a>
    <a class="<?= $current==='perfil.php'?'active':'' ?>" href="perfil.php"><span class="icon">◉</span>Perfil</a>
  </nav>
  <div class="sidebar-tip"><strong>Mantenha o foco!</strong><br>Estude um pouco todos os dias. 🌱</div>
</aside>

<nav class="mobile-nav">
  <a class="<?= $current==='index.php'?'active':'' ?>" href="index.php">⌂<br>Início</a>
  <a class="<?= in_array($current,['salas.php','sala.php'])?'active':'' ?>" href="salas.php">▦<br>Salas</a>
  <a class="<?= $current==='quiz.php'?'active':'' ?>" href="quiz.php">☑<br>Quiz</a>
  <a class="<?= $current==='ranking.php'?'active':'' ?>" href="ranking.php">♜<br>Ranking</a>
  <a class="<?= $current==='perfil.php'?'active':'' ?>" href="perfil.php">◉<br>Perfil</a>
</nav>
