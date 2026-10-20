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
    ['label' => 'Início', 'href' => 'index.php', 'icon' => 'assets/images/icons/home (1).png'],
    ['label' => 'Salas', 'href' => 'salas.php', 'icon' => 'assets/images/icons/training.png'],
    ['label' => $useReferenceShell ? 'Biblioteca' : 'Cursos', 'href' => 'biblioteca.php', 'icon' => 'assets/images/icons/book (1).png'],
    ['label' => 'Feed', 'href' => 'feed.php', 'icon' => 'assets/images/icons/reading-book.png'],
    ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => 'assets/images/icons/questoes.png'],
    ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => 'assets/images/icons/winn-icon.png'],
    ['label' => 'Meu perfil', 'href' => 'perfil-dashboard.php', 'icon' => 'assets/images/icons/perfil-de-usuario.png'],
    ['label' => 'Planos', 'href' => 'planos.php', 'icon' => 'assets/images/icons/moedas.png'],
];
$mobileHrefs = ['index.php', 'feed.php', 'biblioteca.php', 'quiz.php', 'planos.php'];
$mobileItems = array_values(array_filter(
    $desktopItems,
    static fn (array $item): bool => in_array($item['href'], $mobileHrefs, true)
));
?>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden aria-hidden="true"></div>
<aside class="sidebar" id="site-navigation" role="dialog" aria-modal="true" aria-label="Menu principal" aria-hidden="true" inert>
  <div class="sidebar-inner">
    <div class="sidebar-header">
      <span>Menu</span>
      <button type="button" class="sidebar-close" aria-label="Fechar menu" data-sidebar-close>×</button>
    </div>
    <nav class="nav">
      <?php foreach ($desktopItems as $item): ?>
        <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= esc((string) $item['href']) ?>" <?= $current === basename($item['href']) ? 'aria-current="page"' : '' ?>><span class="icon"><img class="app-nav-icon" src="<?= esc((string) $item['icon']) ?>" alt="" aria-hidden="true"></span><?= esc((string) $item['label']) ?></a>
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
    <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="site-navigation" data-sidebar-toggle>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </button>
    <?php if (isset($_SESSION['usuario_id'])): ?>
      <a class="top-user" href="perfil-dashboard.php">
        <img src="<?= esc((string) ($_SESSION['perfil_imagem'] ?? 'assets/images/icons/user-icon.png')) ?>" alt="">
        <?php if ($useReferenceShell): ?>
          <span><?= esc((string) ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário')) ?></span>
        <?php endif; ?>
      </a>
    <?php endif; ?>
  </div>
</div>
