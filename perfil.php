<?php require 'includes/auth.php'; ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Meu Perfil</title><link rel="stylesheet" href="assets/css/style.css"></head><body><div class="app"><?php require 'includes/header.php'; ?><main class="main">
<header class="topbar"><div><h1>Meu Perfil</h1><p>Seu desempenho acadêmico.</p></div><button class="icon-btn">⚙</button></header>
<div class="card" style="padding:25px">
<div class="profile"><div class="avatar">👩🏻</div><div><h2 style="font-size:17px;margin:0 0 5px">Ana Clara</h2><p style="margin:0;font-size:12px;color:var(--muted)">Estudante</p><small style="color:var(--muted)">ana.clara@email.com</small></div></div>
<div class="stats" style="margin-top:22px"><div class="stat"><strong>2.450</strong><span>Pontos</span></div><div class="stat"><strong>12</strong><span>Salas</span></div><div class="stat"><strong>28</strong><span>Quizzes</span></div></div>
</div>
<div class="card" style="margin-top:18px">
<?php foreach(['Minhas salas','Histórico de quizzes','Conquistas','Editar perfil','Sair'] as $item): ?><a class="rank-row" href="#"><span class="rank-avatar">◉</span><strong style="font-size:13px"><?= $item ?></strong><span class="arrow">›</span></a><?php endforeach; ?>
</div>
</main></div><?php require 'includes/footer.php'; ?>
