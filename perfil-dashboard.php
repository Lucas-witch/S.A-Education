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

$performanceData = obter_dados_dashboard_usuario($pdo, (int) ($_SESSION['usuario_id'] ?? 0));
$timelineLabels = $performanceData['labels'] ?? [];
$acertos = array_values(array_map('intval', $performanceData['acertos'] ?? []));
$erros = array_values(array_map('intval', $performanceData['erros'] ?? []));
$recentRooms = $performanceData['salas_recentes'] ?? [];
$hasActivityHistory = !empty($performanceData['historico_disponivel']);
$totalTentativas = (int) ($performanceData['total_tentativas'] ?? 0);
$showPerformanceChart = dashboard_pode_exibir_grafico($performanceData);
$performanceSummary = [
    'total_acertos' => (int) ($performanceData['total_acertos'] ?? 0),
    'total_erros' => (int) ($performanceData['total_erros'] ?? 0),
    'percentual_acerto' => (float) ($performanceData['percentual_acerto'] ?? 0.0),
    'total_respostas' => (int) ($performanceData['total_respostas'] ?? 0),
];

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
                    <div class="profile-shell">
                        <div class="profile-avatar-wrap">
                            <img src="<?= htmlspecialchars((string) $avatarSource, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil de <?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="profile-name-block">
                            <h2 class="profile-name"><?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="profile-meta-list">
                                <span class="profile-meta-item">@<?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="profile-meta-item"><?= htmlspecialchars((string) $perfilLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($email !== '—'): ?>
                                    <span class="profile-meta-item"><?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <?php if ($totalTentativas > 0): ?>
                        <div class="quick-metrics">
                            <div class="quick-stat"><strong><?= $totalTentativas ?></strong><span>Tentativas registradas</span></div>
                            <div class="quick-stat"><strong><?= number_format((float) $performanceSummary['total_respostas'], 0, ',', '.') ?></strong><span>Respostas registradas</span></div>
                            <div class="quick-stat"><strong><?= number_format((float) $performanceSummary['percentual_acerto'], 1, ',', '.') ?>%</strong><span>Taxa de acerto</span></div>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($showPerformanceChart): ?>
                    <div class="content-grid">
                        <section class="panel chart-panel" aria-label="Gráfico de desempenho ao longo do tempo">
                            <div class="panel-header">
                                <h3>Acertos e erros ao longo do tempo</h3>
                                <span>Até 7 períodos com atividade</span>
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
                            <?php if ($performanceSummary['total_respostas'] > 0): ?>
                                <div class="performance-summary" aria-label="Resumo percentual de acertos e erros">
                                    <span>Total de respostas: <strong><?= (int) $performanceSummary['total_respostas'] ?></strong></span>
                                    <span>Taxa de acerto: <strong><?= number_format((float) $performanceSummary['percentual_acerto'], 1, ',', '.') ?>%</strong></span>
                                </div>
                            <?php endif; ?>
                        </section>

                        <aside class="panel side-panel" aria-label="Resumo rápido do perfil">
                            <div class="panel-header">
                                <h3>Resumo rápido</h3>
                            </div>

                            <ul class="summary-list">
                                <li class="summary-item"><span>Tentativas</span><strong><?= $totalTentativas ?></strong></li>
                                <li class="summary-item"><span>Acertos</span><strong><?= number_format((float) $performanceSummary['total_acertos'], 0, ',', '.') ?></strong></li>
                                <li class="summary-item"><span>Erros</span><strong><?= number_format((float) $performanceSummary['total_erros'], 0, ',', '.') ?></strong></li>
                                <li class="summary-item"><span>Taxa de acerto</span><strong><?= number_format((float) $performanceSummary['percentual_acerto'], 1, ',', '.') ?>%</strong></li>
                            </ul>
                        </aside>
                    </div>
                <?php elseif (!$hasActivityHistory): ?>
                    <section class="panel metrics-panel" role="status">
                        <div class="panel-header"><h3>Histórico indisponível</h3></div>
                        <p>Não foi possível carregar suas atividades agora. Tente novamente mais tarde.</p>
                    </section>
                <?php elseif ($totalTentativas === 0): ?>
                    <section class="panel metrics-panel" role="status">
                        <div class="panel-header"><h3>Seu histórico começa aqui</h3></div>
                        <p>Suas tentativas concluídas aparecerão neste espaço depois que você responder uma questão.</p>
                        <a class="btn btn-primary" href="quiz.php">Resolver uma questão</a>
                    </section>
                <?php else: ?>
                    <section class="panel metrics-panel" role="status">
                        <div class="panel-header"><h3>Desempenho registrado</h3></div>
                        <p>Há tentativas concluídas, mas ainda não existem registros em períodos diferentes para formar um gráfico de evolução.</p>
                    </section>
                <?php endif; ?>

                <?php if ($totalTentativas > 0 || (int) ($performanceData['emprestimos_devolvidos'] ?? 0) > 0): ?>
                    <section class="panel metrics-panel" aria-label="Relatório de aulas e leitura">
                        <div class="panel-header">
                            <h3>Atividades e materiais</h3>
                            <span>Registros persistidos</span>
                        </div>

                        <div class="metrics-grid">
                            <?php if ((int) ($performanceData['quizzes_com_tentativa'] ?? 0) > 0): ?><div class="mini-stat"><strong><?= (int) $performanceData['quizzes_com_tentativa'] ?></strong><span>respostas de quiz registradas</span></div><?php endif; ?>
                            <?php if ((int) ($performanceData['exercicios_com_tentativa'] ?? 0) > 0): ?><div class="mini-stat"><strong><?= (int) $performanceData['exercicios_com_tentativa'] ?></strong><span>respostas de exercício registradas</span></div><?php endif; ?>
                            <?php if (!empty($performanceData['emprestimos_disponiveis']) && (int) ($performanceData['emprestimos_devolvidos'] ?? 0) > 0): ?><div class="mini-stat"><strong><?= (int) $performanceData['emprestimos_devolvidos'] ?></strong><span>empréstimos devolvidos</span></div><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($recentRooms)): ?>
                    <section class="panel rooms-panel" aria-label="Salas recentes do usuário">
                        <div class="panel-header">
                            <h3>Salas recentes</h3>
                            <span>Participações ativas</span>
                        </div>

                        <div class="recent-rooms-grid">
                            <?php foreach ($recentRooms as $room): ?>
                                <?php $roomId = (int) ($room['id'] ?? 0); $roomName = (string) ($room['nome'] ?? 'Sala'); $roomMateria = (string) ($room['materia'] ?? ''); $roomIcon = !empty($room['icone']) ? (string) $room['icone'] : 'assets/images/icons/user-icon.png'; ?>
                                <a class="recent-room-card" href="sala.php?id=<?= $roomId ?>">
                                    <div class="recent-room-icon">
                                        <img src="<?= htmlspecialchars((string) $roomIcon, ENT_QUOTES, 'UTF-8') ?>" alt="Ícone da sala <?= htmlspecialchars((string) $roomName, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="recent-room-info">
                                        <strong><?= htmlspecialchars((string) $roomName, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($roomMateria !== ''): ?><span><?= htmlspecialchars((string) $roomMateria, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                        <?php if (!empty($room['prof'])): ?><small><?= htmlspecialchars((string) $room['prof'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    </div>
                                </a>
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
