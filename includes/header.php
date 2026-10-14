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

$menuDesktop = [
    'estudante' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
    'professor' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
    'instituicao' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
];

$menuMobile = [
    'estudante' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
    'professor' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
    'instituicao' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Salas', 'href' => 'salas.php', 'icon' => '▦'],
        ['label' => 'Cursos', 'href' => 'biblioteca.php', 'icon' => '▣'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Questões', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Ranking', 'href' => 'ranking.php', 'icon' => '♜'],
    ],
];

$desktopItems = $menuDesktop[$perfilAtual] ?? $menuDesktop['estudante'];
$mobileItems = $menuMobile[$perfilAtual] ?? $menuMobile['estudante'];
?>
<aside class="sidebar" aria-label="Menu lateral">
  <div class="sidebar-inner">
    <div class="sidebar-header">
      <span>Menu</span>
      <button type="button" class="sidebar-close" aria-label="Fechar guia rápida" data-sidebar-close>×</button>
    </div>
    <nav class="nav">
      <?php foreach ($desktopItems as $item): ?>
        <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>"><span class="icon"><?= htmlspecialchars((string) $item['icon']) ?></span><?= htmlspecialchars((string) $item['label']) ?></a>
      <?php endforeach; ?>
      <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="logout.php" class="logout-link-sidebar"><span class="icon">⎋</span>Sair da conta</a>
      <?php endif; ?>
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

