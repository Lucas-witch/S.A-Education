<?php
require SA_PROJECT_ROOT . '/includes/auth.php';
require SA_PROJECT_ROOT . '/includes/data.php';
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
    'instituicao' => 'perfil-dashboard.php',
][$perfilAtual] ?? 'salas.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>S.A Education — Início</title><link rel="stylesheet" href="assets/css/estilo.css">
    </head>
    <body>
<div class="app"><?php require SA_PROJECT_ROOT . '/includes/header.php'; ?><main class="main">
<header class="topbar">
    <div>
        <h1>Olá, <?= htmlspecialchars((string) $nomeUser) ?></h1>
        <p><?= htmlspecialchars($perfilLabel) ?> · Vamos aprender algo novo hoje?</p>
    </div>
</header>

<section class="hero">
    <div>
        <h2><?= htmlspecialchars($heroTitle) ?></h2>
        <p><?= htmlspecialchars($heroText) ?></p>
        <a class="btn light" href="<?= htmlspecialchars($heroLink) ?>"><?= htmlspecialchars($heroButton) ?></a>
    </div>
    <div class="trophy" aria-label="Conquista"><img src="assets/images/icons/winn-icon.png" alt=""></div>
</section>

<section class="stats-grid" aria-label="Estatísticas da plataforma">
    <div class="card stat-card">
        <strong><?= (int) $estatisticas['salas'] ?></strong>
        <p>Salas</p>
    </div>
    <div class="card stat-card">
        <strong><?= (int) $estatisticas['biblioteca'] ?></strong>
        <p>Biblioteca</p>
    </div>
    <div class="card stat-card">
        <strong><?= (int) $estatisticas['publicacoes'] ?></strong>
        <p>Publicações</p>
    </div>
    <div class="card stat-card">
        <strong><?= (int) $estatisticas['instituicoes'] ?></strong>
        <p>Instituições</p>
    </div>
</section>

<div class="section-title">
    <h2>Salas recentes</h2><a href="salas.php">Ver todas</a>
</div>
<div class="grid grid-3">
    <?php foreach ($recentes as $r): ?>
        <?php $roomId = (int) ($r['id'] ?? 0); $roomIcon = subject_icon_for((string) ($r['materia'] ?? '')); ?>
        <a class="card room-card" href="sala.php?id=<?= $roomId ?>">
            <div class="room-icon">
                <img src="<?= htmlspecialchars($roomIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" class="room-subject-icon">
            </div>
            <div>
                <h3><?= htmlspecialchars((string) ($r['nome'] ?? 'Sala')) ?></h3>
                <p><?= htmlspecialchars((string) ($r['prof'] ?? 'Professor')) ?> · <?= (int) ($r['alunos'] ?? 0) ?> alunos</p>
            </div>
            <span class="arrow" aria-hidden="true">›</span>
        </a>
    <?php endforeach; ?>
    <?php if ($recentes === []): ?>
        <p class="card empty-state" role="status">Você ainda não participa de nenhuma sala.</p>
    <?php endif; ?>
</div>

<div class="section-title" style="margin-top:28px;">
    <h2>Biblioteca em destaque</h2><a href="biblioteca.php">Abrir biblioteca</a>
</div>
<div class="grid grid-3">
    <?php foreach ($bibliotecaRecente as $item): ?>
        <article class="card library-item">
            <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">
                <?= htmlspecialchars(strtoupper((string) ($item['tipo'] ?? 'material'))) ?>
            </div>
            <h3><?= htmlspecialchars((string) ($item['titulo'] ?? 'Item')) ?></h3>
            <p style="margin:0;color:var(--muted);"><?= htmlspecialchars((string) ($item['autor'] ?? 'Autor')) ?></p>
            <small style="display:block;margin-top:8px;"><?= htmlspecialchars((string) ($item['disponibilidade'] ?? 'gratis')) ?></small>
        </article>
    <?php endforeach; ?>
</div>

<div class="section-title" style="margin-top:28px;">
    <h2>Últimas publicações</h2><a href="feed.php">Ver feed</a>
</div>
<div class="card content-list">
    <?php foreach ($publicacoesRecentes as $pub): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--border);">
            <strong><?= htmlspecialchars((string) ($pub['titulo'] ?? 'Publicação')) ?></strong>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                <?= htmlspecialchars((string) ($pub['tipo'] ?? 'material')) ?> · <?= htmlspecialchars((string) ($pub['status'] ?? 'publicado')) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</main>
</div>
<?php require SA_PROJECT_ROOT . '/includes/footer.php'; ?>