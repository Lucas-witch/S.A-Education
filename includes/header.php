<?php
require_once __DIR__ . '/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$current = basename($_SERVER['PHP_SELF']);
$perfilAtual = strtolower((string) ($_SESSION['usuario_perfil'] ?? 'estudante'));
$useReferenceShell = !empty($referenceShell);
$desktopItems = [
    ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
    ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
    ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
    ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
    ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
    ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
];
  $mobileItems = $desktopItems;
if ($useReferenceShell) {
  foreach ($desktopItems as &$menuItem) {
    if ($menuItem['href'] === 'biblioteca.php') {
      $menuItem['label'] = 'Biblioteca';
    }
  }
  unset($menuItem);
  $desktopItems[] = ['label' => 'Meu perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'];
}
?>
<aside class="sidebar" aria-label="Menu lateral">
  <div class="sidebar-inner">
    <div class="sidebar-header">
      <span>Menu</span>
      <button type="button" class="sidebar-close" aria-label="Fechar guia rápida" data-sidebar-close>×</button>
    </div>
    <nav class="nav">
      <?php foreach ($desktopItems as $item): ?>
        <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>" <?= $current === basename($item['href']) ? 'aria-current="page"' : '' ?>><span class="icon"><?= htmlspecialchars((string) $item['icon']) ?></span><?= htmlspecialchars((string) $item['label']) ?></a>
      <?php endforeach; ?>
      <?php if (isset($_SESSION['usuario_id']) && !$useReferenceShell): ?>
        <form action="logout.php" method="post" class="logout-form">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <button type="submit" class="logout-link-sidebar"><span class="icon">⎋</span>Sair da conta</button>
        </form>
      <?php endif; ?>
    </nav>
    <div class="sidebar-tip"><strong>Mantenha o foco!</strong><br>Estude um pouco todos os dias.</div>
    <?php if ($useReferenceShell && isset($_SESSION['usuario_id'])): ?>
      <div class="sidebar-account">
        <a class="sidebar-profile" href="perfil-dashboard.php">
          <img src="<?= esc((string) ($_SESSION['perfil_imagem'] ?? 'assets/images/icons/user-icon.png')) ?>" alt="">
          <span><strong><?= esc((string) ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário')) ?></strong><small><?= esc(perfil_label($perfilAtual)) ?></small></span>
        </a>
        <form action="logout.php" method="post" class="logout-form">
          <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
          <button type="submit" class="logout-link-sidebar"><span class="icon">⎋</span>Sair</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</aside>

<div class="top-header-shell">
  <?php if ($useReferenceShell): ?>
    <form class="global-search" action="pesquisa.php" method="get" role="search">
      <span aria-hidden="true">#</span>
      <input type="search" name="q" maxlength="120" placeholder="Buscar na comunidade" aria-label="Buscar na comunidade">
    </form>
  <?php endif; ?>
  <a class="brand-mark" href="index.php" aria-label="Início">
    <img src="assets/images/logo-app.png" alt="S.A Education" id="logo">
  </a>
  <div class="top-header-actions">
    <button class="quick-guide-button" type="button" aria-label="Abrir guia rápida" aria-expanded="false" data-guide-toggle>
      <img src="assets/images/guia-rapida.png" alt="">
    </button>
    <?php if ($useReferenceShell): ?>
      <a class="top-user" href="perfil-dashboard.php">
        <img src="<?= esc((string) ($_SESSION['perfil_imagem'] ?? 'assets/images/icons/user-icon.png')) ?>" alt="">
        <span><?= esc((string) ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário')) ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div id="quick-guide-overlay" class="quick-guide-overlay" hidden></div>
<div id="quick-guide-panel" class="quick-guide-panel" hidden aria-hidden="true" aria-label="Guia rápida">
  <div class="quick-guide-card">
    <div class="quick-guide-header">
      <h3>Guia rápida</h3>
      <button type="button" class="sidebar-close" aria-label="Fechar guia rápida" data-guide-close>×</button>
    </div>
    <div class="quick-guide-links">
      <?php foreach ($desktopItems as $item): ?>
        <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="quick-guide-link"><?= htmlspecialchars((string) $item['label']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

