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
	<link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
	<main class="form-page">
	<section class="form-card">
		<a class="back" href="index.php">←</a>

		<div class="page-title">
			<div class="login-icon"><img src="assets/images/logo-app.png" alt="Logotype"></div>
			<h1>Criar conta</h1>
			<span>Preencha seus dados para começar.</span>
		</div>

		<form action="processa_cadastro.php" method="POST" autocomplete="on">
			<input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">

			<label>Nome completo:
				<input type="text" name="nome_completo" required>
			</label>

			<label>Nome de usuário:
				<input type="text" name="username" required>
			</label>

			<label>E-mail:
				<input type="email" name="email" required>
			</label>

			<label>Data de nascimento:
				<input type="date" name="nascimento">
			</label>

			<label>Senha:
				<input type="password" name="senha" required>
			</label>

			<label>Confirmar senha:
				<input type="password" name="confirmar_senha" required>
			</label>

			<label>Perfil:
				<select name="perfil">
					<option value="estudante">Estudante</option>
					<option value="professor">Professor</option>
				</select>
			</label>

			<button class="btn btn-primary" type="submit">Criar conta</button>
		</form>

		<p class="switch">Já possui conta? <a href="login.php">Entrar</a></p>
		<p class="switch">Representa uma instituição de ensino? <a href="cadastro-instituicao.php">Cadastrar instituição</a></p>
	</section>
</main>
</body>
</html>
