<?php require 'includes/auth.php'; ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Criar Sala</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body><div class="app"><?php require 'includes/header.php'; ?><main class="main">
<header class="topbar"><div><h1>Criar Sala</h1><p>Monte um espaço para sua turma.</p></div></header>
<form class="card form-card" action="#" method="post">
<div style="text-align:center;font-size:48px;margin-bottom:20px">👥</div>
<div class="form-group"><label>Nome da sala</label><input class="form-control" placeholder="Ex: Matemática Básica"></div>
<div class="form-group"><label>Matéria</label><select class="form-control"><option>Selecione a matéria</option><option>Matemática</option><option>Química</option><option>Biologia</option><option>História</option><option>Linguagens</option></select></div>
<div class="form-group"><label>Descrição</label><textarea class="form-control" placeholder="Descreva o objetivo da sala..."></textarea></div>
<div class="form-group"><label>Privacidade</label><select class="form-control"><option>Pública</option><option>Privada</option></select></div>
<button class="btn light full" type="button" onclick="alert('Sala criada com sucesso!')">Criar sala</button>
</form></main></div><?php require 'includes/footer.php'; ?>
