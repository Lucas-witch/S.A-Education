<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

$mensagem = '';
$ultimaResposta = $_GET['resultado'] ?? '';
if ($ultimaResposta === 'correta') {
    $mensagem = 'Resposta correta! Seu desempenho foi registrado no perfil.';
} elseif ($ultimaResposta === 'incorreta') {
    $mensagem = 'Resposta registrada como incorreta. O histórico será atualizado no seu perfil.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Quiz</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
  <div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main">
      <header class="topbar">
        <a href="index.php">← Voltar</a>
        <div><strong>Quiz</strong></div>
        <span style="color:#54a13f">◷ 00:20</span>
      </header>

      <?php if ($mensagem !== ''): ?>
        <div class="alert success" style="margin-bottom:16px;"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form action="processa_quiz.php" method="post" class="question">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <p style="font-size:12px">Pergunta 1 de 10</p>
        <div class="progress"><span style="width:10%"></span></div>
        <h2>Qual é a capital do Brasil?</h2>

        <label class="option" for="opcao-a">
          <input type="radio" id="opcao-a" name="resposta" value="A" required>
          <span class="letter">A</span> São Paulo
        </label>

        <label class="option" for="opcao-b">
          <input type="radio" id="opcao-b" name="resposta" value="B" required>
          <span class="letter">B</span> Brasília
        </label>

        <label class="option" for="opcao-c">
          <input type="radio" id="opcao-c" name="resposta" value="C" required>
          <span class="letter">C</span> Rio de Janeiro
        </label>

        <label class="option" for="opcao-d">
          <input type="radio" id="opcao-d" name="resposta" value="D" required>
          <span class="letter">D</span> Belo Horizonte
        </label>

        <button class="btn full" type="submit" style="margin-top:25px">Enviar resposta</button>
      </form>
    </main>
  </div>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
