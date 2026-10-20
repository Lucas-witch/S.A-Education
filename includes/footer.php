<?php
require_once __DIR__ . '/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);
$footerItems = $mobileItems ?? $desktopItems ?? [];
?>
<footer class="app-footer">
  <nav class="app-footer-nav" aria-label="Navegação principal">
    <?php foreach ($footerItems as $item): ?>
      <a class="<?= $current === basename($item['href']) ? 'active' : '' ?>" href="<?= htmlspecialchars((string) $item['href']) ?>" <?= $current === basename($item['href']) ? 'aria-current="page"' : '' ?>>
        <span><img class="app-nav-icon" src="<?= esc((string) $item['icon']) ?>" alt="" aria-hidden="true"></span>
        <small><?= htmlspecialchars((string) $item['label']) ?></small>
      </a>
    <?php endforeach; ?>
  </nav>
</footer>
<script src="assets/js/app.js"></script>
<script src="assets/js/sidebar.js"></script>
