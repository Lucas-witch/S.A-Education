<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require_auth();
require __DIR__ . '/includes/data.php';

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
$usuarioId = (int) $_SESSION['usuario_id'];
$usuarioStmt = $pdo->prepare(
    'SELECT id, nome_completo, email, username, perfil_imagem, ranking_publico
     FROM usuarios
     WHERE id = :id
     LIMIT 1'
);
$usuarioStmt->execute(['id' => $usuarioId]);
$usuarioPerfil = $usuarioStmt->fetch();

if (!$usuarioPerfil) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

sync_authenticated_profile_session($usuarioPerfil);
$nomeUsuario = $_SESSION['usuario_nome'];
$username = $_SESSION['usuario_username'];
$email = $_SESSION['usuario_email'];
$perfilLabel = perfil_label($perfilAtual ?: 'estudante');

$avatarSource = $_SESSION['perfil_imagem'];
$profileFlash = $_SESSION['profile_edit_flash'] ?? null;
$profileFormValues = $_SESSION['profile_edit_form'] ?? [];
unset($_SESSION['profile_edit_flash'], $_SESSION['profile_edit_form']);
$formName = (string) ($profileFormValues['nome_completo'] ?? $nomeUsuario);
$formEmail = (string) ($profileFormValues['email'] ?? $email);
$formUsername = (string) ($profileFormValues['username'] ?? $username);
$formRankingPublico = (string) ($profileFormValues['ranking_publico'] ?? $usuarioPerfil['ranking_publico'] ?? '0') === '1';

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
    <script src="assets/js/perfil.js" defer></script>
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

                <?php if (is_professor() && empty($_SESSION['usuario_aprovado'])): ?>
                    <section class="panel" role="status">
                        <h2>Perfil de professor em análise</h2>
                        <p><?= ($_SESSION['usuario_professor_status'] ?? '') === 'rejected'
                            ? 'A solicitação foi rejeitada. Entre em contato com a administração para obter orientação.'
                            : 'A administração precisa aprovar seu perfil antes de você publicar como professor.' ?></p>
                    </section>
                <?php endif; ?>

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

                <?php if (is_array($profileFlash) && isset($profileFlash['message'])): ?>
                    <div class="profile-edit-alert <?= ($profileFlash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?>" role="<?= ($profileFlash['type'] ?? '') === 'success' ? 'status' : 'alert' ?>">
                        <?= htmlspecialchars((string) $profileFlash['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <section class="panel profile-edit-panel" id="editar-perfil" aria-labelledby="profile-edit-title">
                    <div class="panel-header">
                        <div>
                            <h3 id="profile-edit-title">Editar perfil</h3>
                            <span>Atualize seus dados pessoais e sua foto.</span>
                        </div>
                    </div>

                    <form action="processa_perfil.php" method="post" enctype="multipart/form-data" class="profile-edit-form" data-profile-form>
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="profile-edit-photo">
                            <img src="<?= htmlspecialchars((string) $avatarSource, ENT_QUOTES, 'UTF-8') ?>" alt="Prévia da foto de perfil" data-profile-preview>
                            <div>
                                <label for="profile-photo">Foto de perfil</label>
                                <input type="file" id="profile-photo" name="foto" accept="image/jpeg,image/png,image/webp" data-profile-file>
                                <small>JPEG, PNG ou WebP; tamanho máximo de 5 MB.</small>
                                <small class="profile-upload-status" role="status" aria-live="polite" data-profile-upload-status></small>
                                <?php if ($avatarSource !== 'assets/images/icons/user-icon.png'): ?>
                                    <label class="profile-remove-photo">
                                        <input type="checkbox" name="remover_foto" value="1" data-profile-remove>
                                        Remover foto personalizada
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-edit-fields">
                            <label class="profile-edit-field">
                                <span>Nome completo <strong aria-hidden="true">*</strong></span>
                                <input type="text" name="nome_completo" value="<?= htmlspecialchars($formName, ENT_QUOTES, 'UTF-8') ?>" minlength="3" maxlength="150" autocomplete="name" required>
                            </label>
                            <label class="profile-edit-field">
                                <span>Nome de usuário <strong aria-hidden="true">*</strong></span>
                                <input type="text" name="username" value="<?= htmlspecialchars($formUsername, ENT_QUOTES, 'UTF-8') ?>" minlength="3" maxlength="40" pattern="[A-Za-z0-9_.-]{3,40}" autocomplete="username" required>
                            </label>
                            <label class="profile-edit-field">
                                <span>E-mail <strong aria-hidden="true">*</strong></span>
                                <input type="email" name="email" value="<?= htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" autocomplete="email" required>
                            </label>
                        </div>

                        <label class="profile-remove-photo">
                            <input type="checkbox" name="ranking_publico" value="1" <?= $formRankingPublico ? 'checked' : '' ?>>
                            Participar do ranking público usando somente meu nome de usuário e pontuação.
                        </label>
                        <small>Esta escolha é opcional. Seu nome completo e e-mail não serão exibidos no ranking.</small>

                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </form>
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
                                    <text x="<?= $x ?>" y="<?= $chartHeight - $chartPadding + 18 ?>" font-size="11" text-anchor="middle" fill="#625E72"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></text>
                                <?php endforeach; ?>

                                <polyline points="<?= htmlspecialchars((string) $acertosPoints, ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="#4C2365" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <polyline points="<?= htmlspecialchars((string) $errosPoints, ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="#66BB6A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <div class="legend">
                                <span class="legend-item"><span class="legend-dot" style="background:#4C2365"></span> Acertos</span>
                                <span class="legend-item"><span class="legend-dot" style="background:#66BB6A"></span> Erros</span>
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
