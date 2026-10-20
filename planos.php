<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/conexao.php';

$referenceShell = true;
$stmt = $pdo->query(
    'SELECT id, nome, preco, descricao, pode_postar_aulas_ilimitadas,
            pode_criar_salas, pode_criar_comunidades_privadas, pode_postar_artigos,
            limite_video_aula_free
     FROM planos
     ORDER BY preco ASC, id ASC'
);
$planos = $stmt->fetchAll();
$planoAtualId = (int) ($_SESSION['usuario_plano_id'] ?? 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planos | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <link rel="stylesheet" href="assets/css/reference-shell.css">
  <link rel="stylesheet" href="assets/css/propaganda.css">
</head>
<body class="reference-shell">
  <div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="main">
      <section class="plans-page" aria-labelledby="plans-title">
        <header class="plans-heading">
          <p class="plans-eyebrow">S.A Education</p>
          <h1 id="plans-title">Planos disponíveis</h1>
          <p>Consulte os planos cadastrados na plataforma e os recursos associados a cada um.</p>
        </header>

        <?php if ($planos === []): ?>
          <p class="plans-state" role="status">Nenhum plano está disponível no momento.</p>
        <?php else: ?>
          <div class="plans-grid">
            <?php foreach ($planos as $plano): ?>
              <?php
                $features = [];
                if ((bool) $plano['pode_postar_aulas_ilimitadas']) {
                    $features[] = 'Publicação ilimitada de aulas';
                } elseif ($plano['limite_video_aula_free'] !== null) {
                    $features[] = 'Limite de ' . (int) $plano['limite_video_aula_free'] . ' aula(s) em vídeo';
                }
                if ((bool) $plano['pode_criar_salas']) {
                    $features[] = 'Criação de salas';
                }
                if ((bool) $plano['pode_criar_comunidades_privadas']) {
                    $features[] = 'Comunidades privadas';
                }
                if ((bool) $plano['pode_postar_artigos']) {
                    $features[] = 'Publicação de artigos';
                }
              ?>
              <article class="plan-card <?= (int) $plano['id'] === $planoAtualId ? 'is-current' : '' ?>">
                <?php if ((int) $plano['id'] === $planoAtualId): ?>
                  <span class="plan-current-label">Seu plano atual</span>
                <?php endif; ?>
                <h2><?= esc((string) $plano['nome']) ?></h2>
                <p class="plan-price">
                  <?= (float) $plano['preco'] === 0.0 ? 'Gratuito' : 'R$ ' . number_format((float) $plano['preco'], 2, ',', '.') ?>
                </p>
                <?php if (trim((string) ($plano['descricao'] ?? '')) !== ''): ?>
                  <p class="plan-description"><?= esc((string) $plano['descricao']) ?></p>
                <?php endif; ?>
                <?php if ($features !== []): ?>
                  <ul class="plan-features">
                    <?php foreach ($features as $feature): ?>
                      <li><?= esc($feature) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
