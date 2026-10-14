<?php
require_once __DIR__ . '/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);
$perfilAtual = strtolower((string) ($_SESSION['usuario_perfil'] ?? 'estudante'));

$footerMenu = [
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
<script>
    if ('serviceWorker' in navigator) {
        const swScriptUrl = new URL('assets/js/sw.js', window.location.href);
        const swScope = new URL('.', swScriptUrl).pathname;
        navigator.serviceWorker.register(swScriptUrl, { scope: swScope }).catch((error) => {
            console.warn('Service worker não foi registrado:', error);
        });
    }
</script>
<script src="assets/js/app.js"></script>
