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
<aside class="sidebar" aria-label="Menu lateral">
  <div class="sidebar-inner">
    <div class="sidebar-header">
      <span>Menu</span>
    </div>
    <nav class="nav">
      <?php foreach ($desktopItems as $item): ?>
        <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>"><span class="icon"><?= htmlspecialchars((string) $item['icon']) ?></span><?= htmlspecialchars((string) $item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-tip"><strong>Mantenha o foco!</strong><br>Estude um pouco todos os dias.</div>
  </div>
</aside>

<div class="top-header-shell">
  <div class="brand-mark" aria-label="S.A Education">
    <img src="assets/images/logo-app.png" alt="Logo S.A Education">
  </div>

  <div class="top-header-actions">
    <button class="quick-guide-button" type="button" aria-label="Abrir guia rápida" aria-expanded="false" data-guide-toggle>
      <img src="assets/images/guia-rapida.png" alt="Guia rápida">
    </button>
  </div>
</div>

<div id="quick-guide-panel" class="quick-guide-panel" hidden>
  <div class="quick-guide-card">
    <h3>Guia rápida</h3>
    <div class="quick-guide-links">
      <?php foreach ($desktopItems as $item): ?>
        <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="quick-guide-link"><?= htmlspecialchars((string) $item['label']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
if (isset($_SESSION['usuario_id'])):
    $usrName = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário';
    ?>
    <div style="position:fixed;right:22px;top:16px;z-index:40;display:flex;align-items:center;gap:10px">
        <a href="perfil-dashboard.php" style="display:flex;align-items:center;gap:10px;color:var(--text);text-decoration:none">
            <img src="assets/images/icons/user-icon.png" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fff;background:#f3f7ef;padding:4px"> <strong style="font-size:14px"><?= htmlspecialchars($usrName) ?></strong>
        </a>
        <a href="logout.php" style="padding:8px 10px;border-radius:10px;background:transparent;border:1px solid var(--border);font-size:13px">Sair</a>
    </div>
<?php endif; ?>
