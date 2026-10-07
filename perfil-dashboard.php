<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

function build_line_points(array $values, int $width, int $height, int $padding): string {
    if ($values === []) {
        return '';
    }

    $min = min($values);
    $max = max($values);
    $range = max(1, $max - $min);
    $count = count($values);
    $points = [];

    foreach ($values as $index => $value) {
        $x = $padding + (($count === 1 ? 0 : $index / ($count - 1)) * ($width - ($padding * 2)));
        $y = $height - $padding - (($value - $min) / $range) * ($height - ($padding * 2));
        $points[] = $x . ',' . $y;
    }

    return implode(' ', $points);
}

$perfilAtual = current_user_perfil();
$nomeUsuario = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário');
$username = $_SESSION['usuario_username'] ?? 'usuario';
$email = $_SESSION['usuario_email'] ?? '—';
$perfilLabel = perfil_label($perfilAtual ?: 'estudante');

$avatarSource = $_SESSION['usuario_foto'] ?? ($_SESSION['usuario_imagem'] ?? ($_SESSION['perfil_imagem'] ?? 'assets/images/icons/user-icon.png'));
if ($avatarSource === null || $avatarSource === '') {
    $avatarSource = 'assets/images/icons/user-icon.png';
}

$summaryStats = [
    'pontos' => (int) ($_SESSION['usuario_pontos'] ?? 2540),
    'moedas' => (int) ($_SESSION['usuario_moedas'] ?? 1480),
    'seguidores' => (int) ($_SESSION['usuario_seguidores'] ?? 864),
    'grupos' => (int) ($_SESSION['usuario_grupos'] ?? 12),
];

$timelineLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'];
$acertos = is_array($_SESSION['usuario_acertos_timeline'] ?? null) ? array_map('intval', $_SESSION['usuario_acertos_timeline']) : [35, 42, 48, 58, 66, 74, 82];
$erros = is_array($_SESSION['usuario_erros_timeline'] ?? null) ? array_map('intval', $_SESSION['usuario_erros_timeline']) : [52, 48, 42, 36, 29, 23, 18];

$combinedMetrics = [
    ['label' => 'Aulas', 'value' => (int) ($_SESSION['usuario_horas_aulas'] ?? 38)],
    ['label' => 'Livros', 'value' => (int) ($_SESSION['usuario_livros_lidos'] ?? 7)],
    ['label' => 'Quizzes', 'value' => (int) ($_SESSION['usuario_quizzes_pontos'] ?? 1450)],
    ['label' => 'Atividades', 'value' => (int) ($_SESSION['usuario_atividades_pontos'] ?? 930)],
];

$combinedMax = max(array_column($combinedMetrics, 'value'));

$writingFeatures = [
    [
        'label' => 'Artigos',
        'score' => $_SESSION['usuario_artigos_pontuacao'] ?? null,
        'criteria' => 'Avaliação baseada em clareza, relevância, pesquisa e originalidade, em uma escala de 0 a 1000.',
    ],
    [
        'label' => 'Escritos',
        'score' => $_SESSION['usuario_escritos_pontuacao'] ?? null,
        'criteria' => 'Critério de coesão, estrutura, gramática, argumentação e profundidade do conteúdo.',
    ],
    [
        'label' => 'Ensaios',
        'score' => $_SESSION['usuario_ensaios_pontuacao'] ?? null,
        'criteria' => 'Nota atribuída pela capacidade de desenvolver ideias, análise e organização do texto.',
    ],
    [
        'label' => 'Redações',
        'score' => $_SESSION['usuario_redacoes_pontuacao'] ?? null,
        'criteria' => 'Avaliação de 0 a 1000 com foco em tema, estrutura, linguagem e proposta textual.',
    ],
];

$writingFeatures = array_values(array_filter($writingFeatures, static fn (array $feature): bool => $feature['score'] !== null && $feature['score'] !== ''));

$chartWidth = 780;
$chartHeight = 220;
$chartPadding = 28;
$acertosPoints = build_line_points($acertos, $chartWidth, $chartHeight, $chartPadding);
$errosPoints = build_line_points($erros, $chartWidth, $chartHeight, $chartPadding);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/perfil.css">
</head>
<body>
    <div class="app">
        <?php require __DIR__ . '/includes/header.php'; ?>

        <main class="main">
            <div class="perfil-dashboard">
                <header class="topbar">
                    <div>
                        <h1>Meu Perfil</h1>
                        <p>Seu desempenho, progresso e atividades recentes em um só lugar.</p>
                    </div>
                    <div class="top-actions">
                        <a href="index.php" class="icon-btn" aria-label="Voltar ao início">←</a>
                    </div>
                </header>

                <section class="panel hero-panel">
                    <div class="profile-header">
                        <div class="profile-identity">
                            <div class="avatar-shell">
                                <img src="<?= htmlspecialchars((string) $avatarSource, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil de <?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="profile-meta">
                                <span class="eyebrow">Perfil <?= htmlspecialchars((string) $perfilLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <h2><?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?></h2>
                                <p><?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?> · @<?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></p>

                                <div class="pill-row">
                                    <span class="status-pill">Ativo agora</span>
                                    <span class="status-pill green">Nível 12</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="quick-metrics">
                        <div class="quick-stat">
                            <strong><?= number_format((float) $summaryStats['pontos'], 0, ',', '.') ?></strong>
                            <span>Pontos</span>
                        </div>
                        <div class="quick-stat">
                            <strong><?= number_format((float) $summaryStats['moedas'], 0, ',', '.') ?></strong>
                            <span>Moedas</span>
                        </div>
                        <div class="quick-stat">
                            <strong><?= number_format((float) $summaryStats['seguidores'], 0, ',', '.') ?></strong>
                            <span>Seguidores</span>
                        </div>
                        <div class="quick-stat">
                            <strong><?= number_format((float) $summaryStats['grupos'], 0, ',', '.') ?></strong>
                            <span>Grupos</span>
                        </div>
                    </div>
                </section>

                <div class="content-grid">
                    <section class="panel chart-panel" aria-label="Gráfico de desempenho ao longo do tempo">
                        <div class="panel-header">
                            <h3>Acertos e erros ao longo do tempo</h3>
                            <span>Últimos 7 meses</span>
                        </div>

                        <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="chart-svg" role="img" aria-label="Gráfico de acertos e erros ao longo do tempo">
                            <?php for ($i = 0; $i <= 4; $i++):
                                $y = $chartPadding + (($chartHeight - ($chartPadding * 2)) / 4) * $i;
                            ?>
                                <line x1="<?= $chartPadding ?>" x2="<?= $chartWidth - $chartPadding ?>" y1="<?= $y ?>" y2="<?= $y ?>" stroke="rgba(111,104,114,0.18)" stroke-width="1" />
                            <?php endfor; ?>

                            <?php foreach ($timelineLabels as $index => $label):
                                $x = $chartPadding + (($chartWidth - ($chartPadding * 2)) / (count($timelineLabels) - 1)) * $index;
                            ?>
                                <text x="<?= $x ?>" y="<?= $chartHeight - $chartPadding + 18 ?>" font-size="11" text-anchor="middle" fill="#6f6872"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></text>
                            <?php endforeach; ?>

                            <polyline points="<?= htmlspecialchars((string) $acertosPoints, ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="#71308b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            <polyline points="<?= htmlspecialchars((string) $errosPoints, ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="#9edb86" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>

                        <div class="legend">
                            <span class="legend-item"><span class="legend-dot" style="background:#71308b"></span> Acertos</span>
                            <span class="legend-item"><span class="legend-dot" style="background:#9edb86"></span> Erros</span>
                        </div>
                    </section>

                    <aside class="panel side-panel" aria-label="Resumo rápido do perfil">
                        <div class="panel-header">
                            <h3>Resumo rápido</h3>
                        </div>

                        <ul class="summary-list">
                            <li class="summary-item">
                                <span>Pontos</span>
                                <strong><?= number_format((float) $summaryStats['pontos'], 0, ',', '.') ?></strong>
                            </li>
                            <li class="summary-item">
                                <span>Moedas</span>
                                <strong><?= number_format((float) $summaryStats['moedas'], 0, ',', '.') ?></strong>
                            </li>
                            <li class="summary-item">
                                <span>Seguidores</span>
                                <strong><?= number_format((float) $summaryStats['seguidores'], 0, ',', '.') ?></strong>
                            </li>
                            <li class="summary-item">
                                <span>Grupos</span>
                                <strong><?= number_format((float) $summaryStats['grupos'], 0, ',', '.') ?></strong>
                            </li>
                        </ul>
                    </aside>
                </div>

                <section class="panel metrics-panel" aria-label="Relatório de aulas e leitura">
                    <div class="panel-header">
                        <h3>Estudo e atividades</h3>
                        <span>Horas, leitura e pontos</span>
                    </div>

                    <div class="metrics-grid">
                        <div class="mini-stat">
                            <strong><?= (int) ($combinedMetrics[0]['value']) ?>h</strong>
                            <span>assistidas em aulas</span>
                        </div>
                        <div class="mini-stat">
                            <strong><?= (int) ($combinedMetrics[1]['value']) ?></strong>
                            <span>livros da biblioteca</span>
                        </div>
                        <div class="mini-stat">
                            <strong><?= number_format((float) $combinedMetrics[2]['value'], 0, ',', '.') ?></strong>
                            <span>pontos em quizzes</span>
                        </div>
                        <div class="mini-stat">
                            <strong><?= number_format((float) $combinedMetrics[3]['value'], 0, ',', '.') ?></strong>
                            <span>pontos em atividades</span>
                        </div>
                    </div>

                    <svg viewBox="0 0 760 180" class="chart-svg" role="img" aria-label="Gráfico com horas assistidas, livros lidos e pontos em atividades">
                        <?php
                        $barGap = 55;
                        $barWidth = 78;
                        $baseY = 150;
                        foreach ($combinedMetrics as $index => $metric):
                            $label = $metric['label'];
                            $value = (int) $metric['value'];
                            $x = 50 + ($index * $barGap);
                            $barHeight = max(18, ($value / max(1, $combinedMax)) * 100);
                            $y = $baseY - $barHeight;
                            echo '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" rx="12" fill="rgba(113,48,139,0.86)" />';
                            echo '<rect x="' . ($x + 18) . '" y="' . ($y + 12) . '" width="' . ($barWidth - 30) . '" height="' . max(14, $barHeight - 18) . '" rx="10" fill="rgba(155,219,134,0.8)" />';
                            echo '<text x="' . ($x + ($barWidth / 2)) . '" y="170" font-size="11" text-anchor="middle" fill="#6f6872">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</text>';
                        endforeach;
                        ?>
                    </svg>
                </section>

                <?php if (!empty($writingFeatures)): ?>
                    <section class="panel writing-panel" aria-label="Avaliações de trabalhos e redações">
                        <div class="panel-header">
                            <h3>Trabalhos e avaliações</h3>
                            <span>Disponível para o usuário</span>
                        </div>

                        <div class="writing-grid">
                            <?php foreach ($writingFeatures as $feature): ?>
                                <?php
                                $score = is_numeric($feature['score']) ? (float) $feature['score'] : null;
                                $scoreDisplay = $score !== null ? number_format($score, 0, ',', '.') : 'N/A';
                                ?>
                                <article class="writing-card">
                                    <h4><?= htmlspecialchars((string) $feature['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <div class="score-badge">Nota: <?= htmlspecialchars((string) $scoreDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                    <p><?= htmlspecialchars((string) $feature['criteria'], ENT_QUOTES, 'UTF-8') ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
