<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/room_search.php';
require_once __DIR__ . '/includes/room_access.php';
require_once __DIR__ . '/includes/room_actions.php';

$isInstitutionSession = isset($_SESSION['instituicao_usuario_id']);
if ($isInstitutionSession) {
    require_institution_auth();
    $userId = (int) $_SESSION['instituicao_usuario_id'];
} else {
    require_auth();
    $userId = (int) $_SESSION['usuario_id'];
}
$isAdmin = !$isInstitutionSession && is_admin_user();

$idInput = $_GET['id'] ?? '';
if (!is_string($idInput) || !ctype_digit($idInput) || (int) $idInput <= 0) {
    header('Location: salas.php', true, 303);
    exit;
}
$roomId = (int) $idInput;
$roomQuery = $pdo->prepare(
    'SELECT s.id, s.nome, s.criador_id, s.institution_id, s.prof, s.alunos, s.materia,
            s.categoria, s.area_conhecimento, s.icone, s.privacidade, s.descricao, s.criado_em
     FROM salas s WHERE s.id = :room_id LIMIT 1'
);
$roomQuery->execute(['room_id' => $roomId]);
$sala = $roomQuery->fetch(PDO::FETCH_ASSOC);
if (!is_array($sala)) {
    http_response_code(404);
    exit('Sala não encontrada.');
}

$privacy = strtolower((string) $sala['privacidade']);
$hasAccess = room_user_can_access($pdo, $sala, $userId, $isAdmin);
$canRequestPrivate = $privacy === 'privada' && !$hasAccess;
if (!$hasAccess && !$canRequestPrivate) {
    http_response_code(404);
    exit('Sala não encontrada.');
}

$isMember = $hasAccess && room_user_is_member($pdo, $roomId, $userId);
$canManage = $hasAccess && room_user_can_manage($pdo, $sala, $userId, $isAdmin);
$flash = '';
$flashType = 'status';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit('Ação inválida.');
    }

    try {
        if ($action === 'request_join' && $canRequestPrivate) {
            $created = room_request_join($pdo, $roomId, $userId);
            $flash = $created
                ? 'Sua solicitação foi enviada aos responsáveis pela sala.'
                : 'Você já tem uma solicitação pendente para esta sala.';
        } elseif ($action === 'leave' && $isMember) {
            room_leave($pdo, $roomId, $userId);
            $flash = 'Você saiu da sala.';
        } elseif (in_array($action, ['approve_request', 'reject_request'], true)) {
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            if ($requestId === false || $requestId <= 0) {
                throw new InvalidArgumentException('Solicitação inválida.');
            }
            room_review_join_request($pdo, $roomId, $requestId, $userId, $action === 'approve_request', $isAdmin);
            $flash = $action === 'approve_request' ? 'Solicitação aprovada.' : 'Solicitação recusada.';
        } elseif ($action === 'remove_member') {
            $targetUserId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if ($targetUserId === false || $targetUserId <= 0) {
                throw new InvalidArgumentException('Membro inválido.');
            }
            room_remove_member($pdo, $roomId, $targetUserId, $userId, $isAdmin);
            $flash = 'Acesso do membro removido.';
        } elseif ($action === 'review_report') {
            $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
            $reportStatus = $_POST['report_status'] ?? '';
            if ($reportId === false || $reportId <= 0 || !is_string($reportStatus)) {
                throw new InvalidArgumentException('Revisão de denúncia inválida.');
            }
            room_review_report($pdo, $roomId, $reportId, $reportStatus, $isAdmin, $userId);
            $flash = 'Denúncia atualizada.';
        } elseif ($action === 'report' && $hasAccess) {
            $reason = $_POST['reason'] ?? '';
            $details = $_POST['details'] ?? '';
            if (!is_string($reason) || !is_string($details)) {
                throw new InvalidArgumentException('Revise os dados da denúncia.');
            }
            $reported = room_submit_report($pdo, $roomId, $userId, $reason, $details);
            $flash = $reported
                ? 'A denúncia foi encaminhada para análise.'
                : 'Você já enviou uma denúncia pendente para esta sala.';
        } else {
            http_response_code(403);
            throw new DomainException('Você não tem permissão para esta ação.');
        }

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof DomainException) {
            http_response_code($exception instanceof InvalidArgumentException ? 400 : 403);
            $flash = $exception->getMessage();
            $flashType = 'alert';
        } else {
            error_log('[S.A Education][room_action] ' . $exception->getMessage());
            $flash = 'Não foi possível concluir a ação agora.';
            $flashType = 'alert';
        }
    }

    if ($action === 'leave' && $flashType === 'status') {
        header('Location: salas.php', true, 303);
        exit;
    }
    $roomQuery->execute(['room_id' => $roomId]);
    $sala = $roomQuery->fetch(PDO::FETCH_ASSOC);
    $hasAccess = room_user_can_access($pdo, $sala, $userId, $isAdmin);
    $isMember = $hasAccess && room_user_is_member($pdo, $roomId, $userId);
    $canManage = $hasAccess && room_user_can_manage($pdo, $sala, $userId, $isAdmin);
}

if (!$hasAccess && !$canRequestPrivate) {
    http_response_code(404);
    exit('Sala não encontrada.');
}

$roomIcon = subject_icon_for((string) ($sala['materia'] ?? ''));
$codigoCriado = $_SESSION['sala_codigo_criado'][$roomId] ?? null;
unset($_SESSION['sala_codigo_criado'][$roomId]);
$members = [];
$requests = [];
$reports = [];
if ($canManage) {
    $memberQuery = $pdo->prepare(
        'SELECT su.usuario_id, su.papel, u.nome_completo, u.username
         FROM salas_usuarios su INNER JOIN usuarios u ON u.id = su.usuario_id
         WHERE su.sala_id = :room_id AND su.status = "ativo"
         ORDER BY FIELD(su.papel, "professor", "moderador", "aluno"), u.nome_completo'
    );
    $memberQuery->execute(['room_id' => $roomId]);
    $members = $memberQuery->fetchAll(PDO::FETCH_ASSOC);
    if ($privacy === 'privada') {
        $requestQuery = $pdo->prepare(
            'SELECT r.id, r.user_id, r.created_at, u.nome_completo, u.username
             FROM room_join_requests r INNER JOIN usuarios u ON u.id = r.user_id
             WHERE r.room_id = :room_id AND r.status = "pending"
             ORDER BY r.created_at'
        );
        $requestQuery->execute(['room_id' => $roomId]);
        $requests = $requestQuery->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($isAdmin) {
        $reportQuery = $pdo->prepare(
            'SELECT r.id, r.reporter_user_id, r.target_type, r.target_id,
                    r.reason, r.details, r.created_at, u.username
             FROM room_reports r INNER JOIN usuarios u ON u.id = r.reporter_user_id
             WHERE r.room_id = :room_id AND r.status = "pending"
             ORDER BY r.created_at'
        );
        $reportQuery->execute(['room_id' => $roomId]);
        $reports = $reportQuery->fetchAll(PDO::FETCH_ASSOC);
    }
}
$displayName = $isInstitutionSession
    ? (string) ($_SESSION['instituicao_nome'] ?? 'Instituição')
    : (string) ($_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $hasAccess ? esc((string) $sala['nome']) : 'Sala privada' ?> | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/sala-config.css">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= $hasAccess ? esc((string) $sala['nome']) : 'Sala privada' ?></h1>
                <p><?= $hasAccess ? 'Comunidade · ' . esc($displayName) : 'Acesso controlado pelos responsáveis da sala.' ?></p>
            </div>
            <a href="salas.php" class="btn btn-outline">Voltar às salas</a>
        </header>

        <?php if ($flash !== ''): ?>
            <p class="alert <?= $flashType === 'alert' ? 'error' : 'success' ?>" role="<?= $flashType === 'alert' ? 'alert' : 'status' ?>"><?= esc($flash) ?></p>
        <?php endif; ?>

        <?php if ($canRequestPrivate): ?>
            <section class="card form-card" aria-labelledby="private-room-heading">
                <h2 id="private-room-heading">Solicitar acesso</h2>
                <p>Esta sala é privada. Envie uma solicitação para que seus responsáveis avaliem sua entrada. Se recebeu um código de convite, use a página Entrar em uma sala.</p>
                <form method="post" action="sala.php?id=<?= $roomId ?>">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_join">
                    <button class="btn btn-primary" type="submit">Solicitar entrada</button>
                </form>
            </section>
        <?php else: ?>
            <section class="card room-detail-card">
                <div class="room-banner">
                    <div class="big-icon">
                        <img src="<?= esc($roomIcon) ?>" alt="" aria-hidden="true" class="room-subject-icon room-subject-icon-large">
                    </div>
                    <div>
                        <strong class="room-detail-eyebrow">Sala <?= $privacy === 'instituicao' ? 'institucional' : '' ?></strong>
                        <h2><?= esc((string) $sala['nome']) ?></h2>
                        <p><?= esc((string) ($sala['prof'] ?? 'Responsável não informado')) ?> · <?= (int) ($sala['alunos'] ?? 0) ?> participantes</p>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="card room-detail-item"><p>Matéria</p><strong><?= esc((string) ($sala['materia'] ?? 'Geral')) ?></strong></div>
                    <div class="card room-detail-item"><p>Acesso</p><strong><?= $privacy === 'instituicao' ? 'Restrito à instituição' : ($privacy === 'privada' ? 'Privada' : 'Pública') ?></strong></div>
                    <?php if (trim((string) ($sala['categoria'] ?? '')) !== ''): ?>
                        <div class="card room-detail-item"><p>Categoria</p><strong><?= esc((string) $sala['categoria']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($sala['area_conhecimento'] ?? '')) !== ''): ?>
                        <div class="card room-detail-item"><p>Área do conhecimento</p><strong><?= esc((string) $sala['area_conhecimento']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div class="card room-detail-item">
                    <p>Descrição</p>
                    <div><?= trim((string) ($sala['descricao'] ?? '')) !== '' ? nl2br(esc((string) $sala['descricao'])) : 'Descrição ainda não informada.' ?></div>
                </div>

                <?php if (is_string($codigoCriado) && $codigoCriado !== ''): ?>
                    <div class="alert success" role="status">Código de convite (mostrado uma única vez): <strong><?= esc($codigoCriado) ?></strong></div>
                <?php endif; ?>

                <div class="room-actions">
                    <?php if (!$isMember && $privacy === 'publica'): ?>
                        <form action="entrar-sala.php" method="post">
                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="sala_id" value="<?= $roomId ?>">
                            <button type="submit" class="btn btn-primary">Entrar na sala</button>
                        </form>
                    <?php elseif ($isMember && (int) ($sala['criador_id'] ?? 0) !== $userId): ?>
                        <form method="post" action="sala.php?id=<?= $roomId ?>">
                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="leave">
                            <button type="submit" class="btn btn-outline">Sair da sala</button>
                        </form>
                    <?php elseif ($isMember): ?>
                        <span class="btn btn-outline">Você é responsável por esta sala</span>
                    <?php endif; ?>
                    <a href="feed.php?sala=<?= $roomId ?>" class="btn btn-outline">Abrir discussões da sala</a>
                </div>

                <?php if ($hasAccess): ?>
                    <details class="room-report">
                        <summary>Denunciar esta sala</summary>
                        <form method="post" action="sala.php?id=<?= $roomId ?>">
                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="report">
                            <label for="report-reason">Motivo</label>
                            <select id="report-reason" name="reason" required>
                                <option value="spam">Spam</option>
                                <option value="assédio">Assédio</option>
                                <option value="conteúdo inadequado">Conteúdo inadequado</option>
                                <option value="outro">Outro</option>
                            </select>
                            <label for="report-details">Detalhes (opcional)</label>
                            <textarea id="report-details" name="details" maxlength="1000"></textarea>
                            <button class="btn btn-outline" type="submit">Enviar denúncia</button>
                        </form>
                    </details>
                <?php endif; ?>

                <?php if ($canManage): ?>
                    <section class="room-management" aria-labelledby="room-management-heading">
                        <h2 id="room-management-heading">Gerenciar participantes</h2>
                        <?php if ($isAdmin && $reports !== []): ?>
                            <h3>Denúncias pendentes</h3>
                            <ul>
                                <?php foreach ($reports as $report): ?>
                                    <li>
                                        <?= esc((string) $report['reason']) ?> — @<?= esc((string) $report['username']) ?>
                                        <span>(<?= esc((string) $report['target_type']) ?><?= (int) $report['target_id'] > 0 ? ' #' . (int) $report['target_id'] : '' ?>)</span>
                                        <?php if ((string) $report['target_type'] !== 'room'): ?>
                                            <a href="feed.php?sala=<?= $roomId ?>">Abrir discussões da sala</a>
                                        <?php endif; ?>
                                        <?php if (trim((string) ($report['details'] ?? '')) !== ''): ?>
                                            <p><?= nl2br(esc((string) $report['details'])) ?></p>
                                        <?php endif; ?>
                                        <form method="post" action="sala.php?id=<?= $roomId ?>">
                                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="review_report">
                                            <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                            <button class="btn btn-primary" name="report_status" value="reviewed" type="submit">Marcar analisada</button>
                                            <button class="btn btn-outline" name="report_status" value="dismissed" type="submit">Descartar denúncia</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($requests !== []): ?>
                            <h3>Solicitações pendentes</h3>
                            <ul>
                                <?php foreach ($requests as $request): ?>
                                    <li>
                                        <?= esc((string) ($request['nome_completo'] ?: $request['username'])) ?>
                                        <form method="post" action="sala.php?id=<?= $roomId ?>">
                                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <button class="btn btn-primary" name="action" value="approve_request" type="submit">Aprovar</button>
                                            <button class="btn btn-outline" name="action" value="reject_request" type="submit">Recusar</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <ul>
                            <?php foreach ($members as $member): ?>
                                <li>
                                    <?= esc((string) ($member['nome_completo'] ?: $member['username'])) ?>
                                    — <?= esc((string) $member['papel']) ?>
                                    <?php if ((int) $member['usuario_id'] !== (int) ($sala['criador_id'] ?? 0)): ?>
                                        <form method="post" action="sala.php?id=<?= $roomId ?>">
                                            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove_member">
                                            <input type="hidden" name="user_id" value="<?= (int) $member['usuario_id'] ?>">
                                            <button class="btn btn-outline" type="submit">Remover acesso</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
