<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <a class="logo" href="index.php" style="display:flex;align-items:center;gap:10px;">
    <img src="assets/images/logo_mark.svg" alt="S.A Education" style="height:38px;width:38px;object-fit:contain;">
    <span style="display:flex;flex-direction:column;line-height:1.05;font-size:18px;font-weight:800;">
      S.A<small style="font-size:10px;letter-spacing:3px;text-transform:lowercase;opacity:.9;">education</small>
    </span>
  </a>
  <nav class="nav">
    <a class="<?= $current==='index.php'?'active':'' ?>" href="index.php"><span class="icon">⌂</span>Início</a>
    <a class="<?= in_array($current,['salas.php','sala.php','criar-sala.php','entrar-sala.php'])?'active':'' ?>" href="salas.php"><span class="icon">▦</span>Salas</a>
    <a class="<?= $current==='quiz.php'?'active':'' ?>" href="quiz.php"><span class="icon">☑</span>Quiz</a>
    <a class="<?= $current==='ranking.php'?'active':'' ?>" href="ranking.php"><span class="icon">♜</span>Ranking</a>
    <a class="<?= $current==='perfil.php'?'active':'' ?>" href="perfil.php"><span class="icon">◉</span>Perfil</a>
  </nav>
  <div class="sidebar-tip"><strong>Mantenha o foco!</strong><br>Estude um pouco todos os dias.</div>
</aside>

<nav class="mobile-nav">
  <a class="<?= $current==='index.php'?'active':'' ?>" href="index.php">⌂<br>Início</a>
  <a class="<?= in_array($current,['salas.php','sala.php'])?'active':'' ?>" href="salas.php">▦<br>Salas</a>
  <a class="<?= $current==='quiz.php'?'active':'' ?>" href="quiz.php">☑<br>Quiz</a>
  <a class="<?= $current==='ranking.php'?'active':'' ?>" href="ranking.php">♜<br>Ranking</a>
  <a class="<?= $current==='perfil.php'?'active':'' ?>" href="perfil.php">◉<br>Perfil</a>
</nav>

<?php
// Pequeno bloco para exibir usuário no topo em telas maiores
if (isset($_SESSION['usuario_id'])):
    $usrName = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário';
    ?>
    <div style="position:fixed;right:22px;top:16px;z-index:40;display:flex;align-items:center;gap:10px">
        <a href="perfil.php" style="display:flex;align-items:center;gap:10px;color:var(--text);text-decoration:none">
            <img src="assets/images/avatars/default.png" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fff"> <strong style="font-size:14px"><?= htmlspecialchars(
                $usrName
            ) ?></strong>
        </a>
        <a href="logout.php" style="padding:8px 10px;border-radius:10px;background:transparent;border:1px solid var(--border);font-size:13px">Sair</a>
    </div>
<?php endif; ?>
