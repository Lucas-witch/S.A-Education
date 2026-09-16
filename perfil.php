<?php 
require 'includes/auth.php';
require 'includes/data.php'; 
require_auth(); 
?>
<!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width,initial-scale=1.0">
		<title>Meu Perfil</title>
		<link rel="stylesheet" href="assets/css/estilo.css">
		<link rel="stylesheet" href="assets/css/Perfil.css">
	</head>
	<body>
		<div class="app"><?php require 'includes/header.php'; ?>
			<main class="main">
				<header class="topbar">
					<div>
						<h1>Meu Perfil</h1>
						<p>Seu desempenho acadêmico.</p>
					</div>
					<button class="icon-btn">⚙</button></header>
		</div>
<?php
// Se foi passado ?id= tente buscar esse usuário; caso contrário exiba o usuário da sessão
$profile = null;
if (isset($_GET['id']) && isset($pdo) && $pdo instanceof PDO) {
	$stmt = $pdo->prepare('SELECT id,nome_completo AS nome,email,perfil FROM usuarios WHERE id = ? LIMIT 1');
	$stmt->execute([intval($_GET['id'])]);
	$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$profile) {
	$profile = [
		'nome' => $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário'),
		'email' => $_SESSION['usuario_email'] ?? '—',
		'perfil' => $_SESSION['usuario_perfil'] ?? 'Estudante'
	];
}
$avatar = 'assets/images/avatars/default.png';
?>
			<div class="card" style="padding:25px">
				<div class="profile">
					<div class="avatar">
						<img src="<?= $avatar ?>" alt="avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
					</div>
					<div>
						<h2 style="font-size:17px;margin:0 0 5px"><?= htmlspecialchars($profile['nome']) ?></h2>
						<p style="margin:0;font-size:12px;color:var(--muted)"><?= htmlspecialchars($profile['perfil']) ?></p>
						<small style="color:var(--muted)"><?= htmlspecialchars($profile['email']) ?></small>
				</div>
			</div>
			<div class="stats" style="margin-top:22px">
				<div class="stat">
					<strong>2.450</strong>
					<span>Pontos</span>
				</div>
				<div class="stat">
					<strong>12</strong>
					<span>Salas</span>
				</div>
				<div class="stat">
					<strong>28</strong>
					<span>Quizzes</span>
				</div>
			</div>
		</div>
		<div class="card" style="margin-top:18px">
<?php 
foreach(['Minhas salas','Histórico de quizzes','Conquistas','Editar perfil','Sair'] as $item): 
?>
<a class="rank-row" href="#">
	<span class="rank-avatar">◉</span>
	<strong style="font-size:13px">
		<?= $item ?>
	</strong>
	<span class="arrow">›</span>
</a><?php endforeach; 
?>
</div>
</main>
</div>
<?php 
require 'includes/footer.php'; 
?>
