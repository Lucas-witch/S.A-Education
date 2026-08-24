<?php
require_once 'includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<title>S.A Education — Cadastro</title>
	<link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<main class="login-page">
	<section class="login-wrap">
		<div class="brand-side">
			<div class="brand-logo">S.A<small>education</small></div>
			<div class="wave"><img src="logo-nova.png" alt="S.A logo" style="max-width:120px"></div>
			<p>Crie sua conta e<br>comece a aprender.</p>
		</div>
		<div class="auth">
			<h1>Criar conta</h1>
			<p>Preencha seus dados para começar.</p>
			<form action="processa_cadastro.php" method="post">
				<input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
				<div class="form-group"><label>Nome completo</label><input class="form-control" name="nome_completo" required></div>
				<div class="form-group"><label>Nome de usuário</label><input class="form-control" name="username" required></div>
				<div class="form-group"><label>E-mail</label><input class="form-control" type="email" name="email" required></div>
				<div class="grid grid-2">
					<div class="form-group"><label>Data de nascimento</label><input class="form-control" type="date" name="nascimento"></div>
					<div class="form-group"><label>Senha</label><input class="form-control" type="password" name="senha" required></div>
				</div>
				<div class="form-group"><label>Confirmar senha</label><input class="form-control" type="password" name="confirmar_senha" required></div>
				<div class="form-group"><label>Perfil</label>
					<select class="form-control" name="perfil">
						<option value="estudante">Estudante</option>
						<option value="professor">Professor</option>
					</select>
				</div>
				<button class="btn full" type="submit">Criar conta</button>
			</form>
			<p style="text-align:center;margin-top:20px">Já possui conta? <a href="login.php" style="color:var(--purple);font-weight:700">Entrar</a></p>
		</div>
	</section>
</main>
</body>
</html>
