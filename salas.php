<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

$perfilAtual = current_user_perfil();
$perfilTitulo = [
    'estudante' => 'Salas para aprender',
    'professor' => 'Minhas salas e acompanhamento',
    'instituicao' => 'Salas da instituição',
][$perfilAtual] ?? 'Salas';

$acaoTexto = [
    'estudante' => 'Entrar em sala',
    'professor' => 'Criar sala',
    'instituicao' => 'Gerenciar salas',
][$perfilAtual] ?? 'Entrar em sala';

$acaoLink = [
    'estudante' => 'entrar-sala.php',
    'professor' => 'criar-sala.php',
    'instituicao' => 'criar-sala.php',
][$perfilAtual] ?? 'entrar-sala.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Salas</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="app">
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <header class="topbar">
      <div>
        <h1>Salas</h1>
        <p><?= htmlspecialchars($perfilTitulo) ?></p>
      </div>
      <a href="<?= htmlspecialchars($acaoLink) ?>" class="btn btn-primary"><?= htmlspecialchars($acaoTexto) ?></a>
    </header>

    <div class="section-title"><h2>Salas disponíveis</h2></div>
    <div class="grid grid-3">
      <?php foreach ($salas as $s): ?>
        <a class="card room-card" href="sala.php">
          <div class="room-icon">
            <?php if (!empty($s['icone'])): ?>
              <img src="<?= esc((string) $s['icone']) ?>" alt="" style="width:38px;height:38px;object-fit:contain">
            <?php else: ?>
              📚
            <?php endif; ?>
          </div>
          <div>
            <h3><?= esc((string) ($s['nome'] ?? 'Sala')) ?></h3>
            <p><?= esc((string) ($s['prof'] ?? 'Professor')) ?> · <?= intval($s['alunos'] ?? 0) ?> alunos</p>
          </div>
          <span class="arrow">›</span>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
