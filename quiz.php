<?php require 'includes/auth.php'; ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Quiz</title><link rel="stylesheet" href="assets/css/style.css"></head><body><div class="app"><?php require 'includes/header.php'; ?><main class="main">
<header class="topbar"><a href="index.php">← Voltar</a><div><strong>Quiz</strong></div><span style="color:#54a13f">◷ 00:20</span></header>
<section class="question"><p style="font-size:12px">Pergunta 2 de 10</p><div class="progress"><span></span></div>
<h2>Qual é a capital do Brasil?</h2>
<div class="option"><span class="letter">A</span>São Paulo</div>
<div class="option correct"><span class="letter">B</span>Brasília <strong style="margin-left:auto">✓</strong></div>
<div class="option"><span class="letter">C</span>Rio de Janeiro</div>
<div class="option"><span class="letter">D</span>Belo Horizonte</div>
<button class="btn full" style="margin-top:25px">Próxima pergunta</button>
</section></main></div><?php require 'includes/footer.php'; ?>
