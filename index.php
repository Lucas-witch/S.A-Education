<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

$perfilAtual = current_user_perfil();
$nomeUser = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? 'Usuário';
$perfilLabel = perfil_label($perfilAtual ?: 'estudante');

$heroTitle = [
    'estudante' => 'Continue evoluindo hoje!',
    'professor' => 'Gerencie sua turma com mais agilidade!',
    'instituicao' => 'Acompanhe sua instituição e o aprendizado em tempo real!',
][$perfilAtual] ?? 'Continue evoluindo hoje!';

$heroText = [
    'estudante' => 'Participe de salas, materiais e quizzes para evoluir cada dia.',
    'professor' => 'Crie salas, acompanhe progresso e compartilhe materiais com os alunos.',
    'instituicao' => 'Monitore salas, materiais e atividades vinculadas à sua instituição.',
][$perfilAtual] ?? 'Participe de salas, materiais e quizzes para evoluir cada dia.';

$heroButton = [
    'estudante' => 'Entrar em sala',
    'professor' => 'Criar sala',
    'instituicao' => 'Ver instituição',
][$perfilAtual] ?? 'Entrar em sala';

$heroLink = [
    'estudante' => 'salas.php',
    'professor' => 'criar-sala.php',
    'instituicao' => 'perfil.php',
][$perfilAtual] ?? 'salas.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>S.A Education — Início</title><link rel="stylesheet" href="assets/css/estilo.css">
    </head>
    <body>
<div class="app"><?php require __DIR__ . '/includes/header.php'; ?><main class="main">
<header class="topbar">
    <div>
        <h1>Olá, <?= htmlspecialchars((string) $nomeUser) ?></h1>
        <p><?= htmlspecialchars($perfilLabel) ?> · Vamos aprender algo novo hoje?</p>
    </div>
    <div class="top-actions">
        <button class="icon-btn"><img src="assets/images/logo_SAeducation_pocket.png" alt="logo" style="width:20px;height:20px"></button>
    </div>
</header>

<section class="hero">
    <div>
        <h2><?= htmlspecialchars($heroTitle) ?></h2>
        <p><?= htmlspecialchars($heroText) ?></p>
        <a class="btn light" href="<?= htmlspecialchars($heroLink) ?>"><?= htmlspecialchars($heroButton) ?></a>
    </div>
    <div class="trophy"><img src="assets/images/winn-icon.png" alt="trofeu"></div>
</section>

<section class="stats-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0;">
    <div class="card" style="padding:18px;">
        <strong><?= (int) $estatisticas['salas'] ?></strong>
        <p>Salas</p>
    </div>
    <div class="card" style="padding:18px;">
        <strong><?= (int) $estatisticas['biblioteca'] ?></strong>
        <p>Biblioteca</p>
    </div>
    <div class="card" style="padding:18px;">
        <strong><?= (int) $estatisticas['publicacoes'] ?></strong>
        <p>Publicações</p>
    </div>
    <div class="card" style="padding:18px;">
        <strong><?= (int) $estatisticas['instituicoes'] ?></strong>
        <p>Instituições</p>
    </div>
</section>

<div class="section-title">
    <h2>Salas recentes</h2><a href="salas.php">Ver todas</a>
</div>
<div class="grid grid-3">
    <?php foreach ($recentes as $r): ?>
        <a class="card room-card" href="sala.php">
            <div class="room-icon"><?= isset($r['icone']) ? htmlspecialchars((string) $r['icone']) : '📚' ?></div>
            <div>
                <h3><?= htmlspecialchars((string) ($r['nome'] ?? 'Sala')) ?></h3>
                <p><?= htmlspecialchars((string) ($r['prof'] ?? 'Professor')) ?> · <?= (int) ($r['alunos'] ?? 0) ?> alunos</p>
            </div>
            <span class="arrow">›</span>
        </a>
    <?php endforeach; ?>
</div>

<div class="section-title" style="margin-top:28px;">
    <h2>Biblioteca em destaque</h2><a href="biblioteca.php">Abrir biblioteca</a>
</div>
<div class="grid grid-3">
    <?php foreach ($bibliotecaRecente as $item): ?>
        <article class="card" style="padding:18px;">
            <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">
                <?= htmlspecialchars(strtoupper((string) ($item['tipo'] ?? 'material'))) ?>
            </div>
            <h3 style="margin:10px 0 4px;"><?= htmlspecialchars((string) ($item['titulo'] ?? 'Item')) ?></h3>
            <p style="margin:0;color:var(--muted);"><?= htmlspecialchars((string) ($item['autor'] ?? 'Autor')) ?></p>
            <small style="display:block;margin-top:8px;"><?= htmlspecialchars((string) ($item['disponibilidade'] ?? 'gratis')) ?></small>
        </article>
    <?php endforeach; ?>
</div>

<div class="section-title" style="margin-top:28px;">
    <h2>Últimas publicações</h2><a href="feed.php">Ver feed</a>
</div>
<div class="card" style="padding:18px;">
    <?php foreach ($publicacoesRecentes as $pub): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <strong><?= htmlspecialchars((string) ($pub['titulo'] ?? 'Publicação')) ?></strong>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                <?= htmlspecialchars((string) ($pub['tipo'] ?? 'material')) ?> · <?= htmlspecialchars((string) ($pub['status'] ?? 'publicado')) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>