<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/institution_members.php';

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$message = '';
$messageType = 'error';
$institutionName = '';
$invitedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    require_auth();
    try {
        accept_institution_invitation($pdo, (int) $_SESSION['usuario_id'], $token);
        $message = 'Convite aceito. Você agora faz parte da instituição.';
        $messageType = 'success';
    } catch (InvalidArgumentException | DomainException $error) {
        $message = $error->getMessage();
    } catch (PDOException $error) {
        error_log('[S.A Education][institution_invitation_accept][PDO] ' . $error->getMessage());
        http_response_code(503);
        $message = 'Não foi possível confirmar o convite agora. Tente novamente.';
    }
} elseif (preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
    try {
        $query = $pdo->prepare(
            'SELECT i.nome, u.email, ii.status, ii.expires_at
             FROM institution_invitations ii
             INNER JOIN instituicoes i ON i.id = ii.institution_id
             INNER JOIN usuarios u ON u.id = ii.invited_user_id
             WHERE ii.token_hash = :token_hash LIMIT 1'
        );
        $query->execute(['token_hash' => hash('sha256', $token)]);
        $invitation = $query->fetch(PDO::FETCH_ASSOC);
        if (
            is_array($invitation)
            && (string) $invitation['status'] === 'pending'
            && new DateTimeImmutable((string) $invitation['expires_at'], new DateTimeZone('UTC'))
                > new DateTimeImmutable('now', new DateTimeZone('UTC'))
        ) {
            $institutionName = (string) $invitation['nome'];
            $invitedEmail = (string) $invitation['email'];
        } else {
            $message = 'Este convite é inválido, já foi utilizado ou expirou.';
        }
    } catch (PDOException $error) {
        error_log('[S.A Education][institution_invitation_lookup][PDO] ' . $error->getMessage());
        http_response_code(503);
        $message = 'Não foi possível consultar este convite agora. Tente novamente.';
    }
} else {
    $message = 'O link do convite é inválido.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceitar convite institucional | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card">
        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app.png" alt="S.A Education"></div>
            <h1>Convite institucional</h1>
        </div>
        <?php if ($message !== ''): ?>
            <p role="<?= $messageType === 'success' ? 'status' : 'alert' ?>"><?= esc($message) ?></p>
        <?php elseif ($institutionName !== ''): ?>
            <p><?= esc($institutionName) ?> convidou a conta <strong><?= esc($invitedEmail) ?></strong> para fazer parte da instituição.</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <?php if (strtolower((string) ($_SESSION['usuario_email'] ?? '')) === strtolower($invitedEmail)): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                        <input type="hidden" name="token" value="<?= esc($token) ?>">
                        <button class="btn btn-primary" type="submit">Aceitar convite</button>
                    </form>
                <?php else: ?>
                    <p role="alert">Entre na conta <?= esc($invitedEmail) ?> e abra novamente o link recebido por e-mail.</p>
                    <p>Saia da conta atual pelo painel e abra novamente o link recebido por e-mail.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Entre na conta vinculada ao convite e abra novamente este link.</p>
                <a class="btn btn-primary" href="login.php">Entrar na minha conta</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="index.php">Voltar para a S.A Education</a>
    </section>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
