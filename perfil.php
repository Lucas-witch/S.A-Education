<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

$perfilAtual = current_user_perfil();
$profile = [
    'nome' => $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário'),
    'email' => $_SESSION['usuario_email'] ?? '—',
    'perfil' => perfil_label($perfilAtual ?: 'estudante'),
];

$statMap = [
    'estudante' => ['Pontos' => '2.450', 'Salas' => '12', 'Quizzes' => '28'],
    'professor' => ['Turmas' => '8', 'Aulas' => '32', 'Alunos' => '240'],
    'instituicao' => ['Unidades' => '4', 'Salas' => '18', 'Atividades' => '56'],
];

$menuMap = [
    'estudante' => ['Minhas salas', 'Histórico de quizzes', 'Conquistas', 'Editar perfil', 'Sair'],
    'professor' => ['Minhas turmas', 'Aulas publicadas', 'Exercícios', 'Editar perfil', 'Sair'],
    'instituicao' => ['Estrutura da instituição', 'Salas cadastradas', 'Relatórios', 'Editar perfil', 'Sair'],
];

$avatar = 'assets/images/avatars/default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width,initial-scale=1.0">
		<title>Meu Perfil</title>
		<link rel="stylesheet" href="assets/css/estilo.css">
		<link rel="stylesheet" href="assets/css/perfil.css">
	</head>
	<body>
		<div class="app"><?php require __DIR__ . '/includes/header.php'; ?>
			<main class="main">
				<header class="topbar">
					<div>
						<h1>Meu Perfil</h1>
						<p>Seu desempenho e contexto de acesso.</p>
					</div>
					<button class="icon-btn">⚙</button>
				</header>

			<div class="card" style="padding:25px">
				<div class="profile">
					<div class="avatar">
						<img src="<?= htmlspecialchars($avatar) ?>" alt="avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
					</div>
					<div>
						<h2 style="font-size:17px;margin:0 0 5px"><?= htmlspecialchars((string) $profile['nome']) ?></h2>
						<p style="margin:0;font-size:12px;color:var(--muted)"><?= htmlspecialchars((string) $profile['perfil']) ?></p>
						<small style="color:var(--muted)"><?= htmlspecialchars((string) $profile['email']) ?></small>
					</div>
				</div>
				<div class="stats" style="margin-top:22px">
					<?php foreach (($statMap[$perfilAtual] ?? $statMap['estudante']) as $label => $valor): ?>
					<div class="stat">
						<strong><?= htmlspecialchars((string) $valor) ?></strong>
						<span><?= htmlspecialchars((string) $label) ?></span>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="card" style="margin-top:18px">
				<?php foreach (($menuMap[$perfilAtual] ?? $menuMap['estudante']) as $item): ?>
				<a class="rank-row" href="#">
					<span class="rank-avatar">◉</span>
					<strong style="font-size:13px"><?= htmlspecialchars((string) $item) ?></strong>
					<span class="arrow">›</span>
				</a>
				<?php endforeach; ?>
			</div>
		</main>
		</div>
		<?php require __DIR__ . '/includes/footer.php'; ?>
	</body>
</html>
