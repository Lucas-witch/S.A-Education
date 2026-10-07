<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);
$perfilAtual = strtolower((string) ($_SESSION['usuario_perfil'] ?? 'estudante'));

$menuDesktop = [
    'estudante' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Quiz', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'professor' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Criar sala', 'href' => 'criar-sala.php', 'icon' => '＋'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'instituicao' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Biblioteca', 'href' => 'biblioteca.php', 'icon' => '📚'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
];

$menuMobile = [
    'estudante' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Biblioteca', 'href' => 'biblioteca.php', 'icon' => '📚'],
        ['label' => 'Exercícios', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'professor' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Criar', 'href' => 'criar-sala.php', 'icon' => '＋'],
        ['label' => 'Biblioteca', 'href' => 'biblioteca.php', 'icon' => '📚'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'instituicao' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Biblioteca', 'href' => 'biblioteca.php', 'icon' => '📚'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
];

$desktopItems = $menuDesktop[$perfilAtual] ?? $menuDesktop['estudante'];
$mobileItems = $menuMobile[$perfilAtual] ?? $menuMobile['estudante'];
?>
<aside class="sidebar">
  <a class="logo" href="index.php"><img src="assets/images/logo_mark.svg" alt="S.A Education" style="height:38px;vertical-align:middle;margin-right:8px"> S.A<small>education</small></a>
  <nav class="nav">
    <?php foreach ($desktopItems as $item): ?>
      <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>"><span class="icon"><?= htmlspecialchars((string) $item['icon']) ?></span><?= htmlspecialchars((string) $item['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-tip"><strong>Mantenha o foco!</strong><br>Estude um pouco todos os dias.</div>
</aside>

<nav class="mobile-nav">
  <?php foreach ($mobileItems as $item): ?>
    <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>"><?= htmlspecialchars((string) $item['icon']) ?><br><?= htmlspecialchars((string) $item['label']) ?></a>
  <?php endforeach; ?>
</nav>

<?php
if (isset($_SESSION['usuario_id'])):
    $usrName = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário';
    ?>
    <div style="position:fixed;right:22px;top:16px;z-index:40;display:flex;align-items:center;gap:10px">
        <a href="perfil-dashboard.php" style="display:flex;align-items:center;gap:10px;color:var(--text);text-decoration:none">
            <img src="assets/images/avatars/default.png" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fff"> <strong style="font-size:14px"><?= htmlspecialchars($usrName) ?></strong>
        </a>
        <a href="logout.php" style="padding:8px 10px;border-radius:10px;background:transparent;border:1px solid var(--border);font-size:13px">Sair</a>
    </div>
<?php endif; ?>
