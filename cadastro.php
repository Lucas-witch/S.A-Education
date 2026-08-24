<?php
session_start();
$nome = htmlspecialchars($_POST['nome'] ?? 'Novo estudante');
$_SESSION['usuario'] = ['nome'=>$nome,'email'=>htmlspecialchars($_POST['email'] ?? ''),'tipo'=>'Estudante'];
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cadastro realizado</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="login-page"><div class="card" style="padding:40px;text-align:center;max-width:500px">
<div style="font-size:60px">🎓</div><h1>Cadastro realizado!</h1><p>Olá, <?= $nome ?>. Sua conta foi criada para esta demonstração.</p>
<a class="btn" href="index.php">Ir para o aplicativo</a></div></main></body></html>
