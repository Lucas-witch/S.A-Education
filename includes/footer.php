<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);
$perfilAtual = strtolower((string) ($_SESSION['usuario_perfil'] ?? 'estudante'));

$footerMenu = [
    'estudante' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Exercícios', 'href' => 'quiz.php', 'icon' => '☑'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'professor' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Criar', 'href' => 'criar-sala.php', 'icon' => '＋'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
    'instituicao' => [
        ['label' => 'Início', 'href' => 'index.php', 'icon' => '⌂'],
        ['label' => 'Feed', 'href' => 'feed.php', 'icon' => '✦'],
        ['label' => 'Biblioteca', 'href' => 'biblioteca.php', 'icon' => '📚'],
        ['label' => 'Perfil', 'href' => 'perfil-dashboard.php', 'icon' => '◉'],
    ],
];

$footerItems = $footerMenu[$perfilAtual] ?? $footerMenu['estudante'];
?>
<footer class="app-footer">
  <nav class="app-footer-nav" aria-label="Navegação principal">
    <?php foreach ($footerItems as $item): ?>
      <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>">
        <span><?= htmlspecialchars((string) $item['icon']) ?></span>
        <small><?= htmlspecialchars((string) $item['label']) ?></small>
      </a>
    <?php endforeach; ?>
  </nav>
</footer>
<script src="assets/js/app.js"></script>
