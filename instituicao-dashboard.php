<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_institution_auth();
require_once __DIR__ . '/conexao.php';

$stmt = $pdo->prepare(
    'SELECT i.id, i.nome, i.tipo, i.natureza, i.responsavel, i.cidade, i.validation_status, u.email
     FROM instituicoes i
     INNER JOIN usuarios u ON u.id = i.usuario_id
     WHERE i.usuario_id = :usuario_id
     LIMIT 1'
);
$stmt->execute(['usuario_id' => (int) $_SESSION['instituicao_usuario_id']]);
$institution = $stmt->fetch();
if (!$institution) {
    $_SESSION = [];
    header('Location: login-instituicao.php');
    exit;
}
$institutionId = (int) $institution['id'];
$activeMembersQuery = $pdo->prepare(
    'SELECT im.user_id, im.member_type, im.joined_at, u.nome_completo, u.email
     FROM institution_members im
     INNER JOIN usuarios u ON u.id = im.user_id
     WHERE im.institution_id = :institution_id AND im.status = "active"
     ORDER BY u.nome_completo'
);
$activeMembersQuery->execute(['institution_id' => $institutionId]);
$members = $activeMembersQuery->fetchAll(PDO::FETCH_ASSOC);
$pendingInvitationsQuery = $pdo->prepare(
    'SELECT ii.id, ii.member_type, ii.expires_at, ii.created_at, u.nome_completo, u.email
     FROM institution_invitations ii
     INNER JOIN usuarios u ON u.id = ii.invited_user_id
     WHERE ii.institution_id = :institution_id AND ii.status = "pending"
     ORDER BY ii.created_at DESC'
);
$pendingInvitationsQuery->execute(['institution_id' => $institutionId]);
$pendingInvitations = $pendingInvitationsQuery->fetchAll(PDO::FETCH_ASSOC);
$membersFlash = $_SESSION['institution_members_flash'] ?? null;
unset($_SESSION['institution_members_flash']);
$institutionPlanType = resolve_institution_plan_type(
    (string) $institution['tipo'],
    (string) $institution['natureza']
);
$institutionPrice = null;
if ($institutionPlanType !== null) {
    $priceQuery = $pdo->prepare(
        'SELECT monthly_price FROM product_plans
         WHERE account_type = "institution" AND institution_type = :institution_type
           AND is_enabled = 1
         ORDER BY code LIMIT 1'
    );
    $priceQuery->execute(['institution_type' => $institutionPlanType]);
    $price = $priceQuery->fetchColumn();
    $institutionPrice = $price === false ? null : (float) $price;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel institucional | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card">
        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app.png" alt="S.A Education"></div>
            <h1><?= esc((string) $institution['nome']) ?></h1>
            <span>Painel de acesso institucional</span>
        </div>
        <p><strong>Responsável:</strong> <?= esc((string) $institution['responsavel']) ?></p>
        <p><strong>E-mail:</strong> <?= esc((string) $institution['email']) ?></p>
        <p><strong>Cidade:</strong> <?= esc((string) $institution['cidade']) ?></p>
        <?php if ($institutionPrice !== null): ?>
            <p><strong>Valor mensal configurado:</strong> R$ <?= number_format($institutionPrice, 2, ',', '.') ?>. A assinatura ainda depende de validação e confirmação de pagamento.</p>
        <?php endif; ?>
        <?php if (($_SESSION['instituicao_validada'] ?? false) !== true): ?>
            <p role="status">A instituição ainda não foi validada pela administração. Os recursos institucionais permanecem bloqueados.</p>
        <?php elseif (($_SESSION['instituicao_assinatura_ativa'] ?? false) !== true): ?>
            <p role="status">A instituição foi validada, mas não há assinatura institucional ativa com pagamento confirmado.</p>
        <?php else: ?>
            <p role="status">Validação e assinatura institucional ativas.</p>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="instituicao-portal.php">Abrir portal acadêmico</a></p>
        <section id="members" aria-labelledby="members-heading">
            <h2 id="members-heading">Membros da instituição</h2>
            <?php if (is_array($membersFlash) && isset($membersFlash['message'])): ?>
                <p role="<?= ($membersFlash['type'] ?? '') === 'success' ? 'status' : 'alert' ?>">
                    <?= esc((string) $membersFlash['message']) ?>
                </p>
            <?php endif; ?>
            <?php if (($_SESSION['instituicao_validada'] ?? false) === true && ($_SESSION['instituicao_assinatura_ativa'] ?? false) === true): ?>
                <form action="processa_membros_instituicao.php" method="post">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="action" value="invite">
                    <label for="member-email">E-mail de uma conta já cadastrada</label>
                    <input id="member-email" name="email" type="email" maxlength="255" required autocomplete="email">
                    <button class="btn btn-primary" type="submit">Enviar convite</button>
                    <p>O convite será enviado por e-mail e só poderá ser aceito pela conta correspondente.</p>
                </form>
            <?php else: ?>
                <p>Convites ficam disponíveis quando a instituição estiver validada e com assinatura ativa.</p>
            <?php endif; ?>
            <?php if ($members === []): ?>
                <p>A instituição ainda não possui membros ativos.</p>
            <?php else: ?>
                <h3>Membros ativos (<?= count($members) ?>)</h3>
                <ul>
                    <?php foreach ($members as $member): ?>
                        <li>
                            <strong><?= esc((string) $member['nome_completo']) ?></strong>
                            — <?= esc((string) $member['email']) ?>
                            (<?= (string) $member['member_type'] === 'teacher' ? 'Professor' : 'Estudante' ?>)
                            <form action="processa_membros_instituicao.php" method="post">
                                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="user_id" value="<?= (int) $member['user_id'] ?>">
                                <button class="btn btn-secondary" type="submit">Revogar acesso</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($pendingInvitations !== []): ?>
                <h3>Convites pendentes (<?= count($pendingInvitations) ?>)</h3>
                <ul>
                    <?php foreach ($pendingInvitations as $invitation): ?>
                        <li>
                            <strong><?= esc((string) $invitation['nome_completo']) ?></strong>
                            — <?= esc((string) $invitation['email']) ?>
                            (expira em <?= esc((string) $invitation['expires_at']) ?>)
                            <form action="processa_membros_instituicao.php" method="post">
                                <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="invitation_id" value="<?= (int) $invitation['id'] ?>">
                                <button class="btn btn-secondary" type="submit">Cancelar convite</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <button class="btn btn-primary" type="submit">Sair</button>
        </form>
    </section>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
