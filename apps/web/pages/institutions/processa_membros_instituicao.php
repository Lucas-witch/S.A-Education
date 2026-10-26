<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_institution_auth();
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/rate_limit.php';
require_once SA_PROJECT_ROOT . '/includes/institution_members.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf($_POST['csrf'] ?? null);
$actorUserId = (int) $_SESSION['instituicao_usuario_id'];
$institutionQuery = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
$institutionQuery->execute(['user_id' => $actorUserId]);
$institutionId = (int) $institutionQuery->fetchColumn();
if ($institutionId <= 0) {
    http_response_code(403);
    exit('Instituição não encontrada para esta conta.');
}

$action = (string) ($_POST['action'] ?? '');
$message = '';
$messageType = 'error';
try {
    if ($action === 'invite') {
        if (auth_rate_limit_remaining($pdo, 'institution-invite', (string) $institutionId, 10, 3600) > 0) {
            throw new DomainException('Limite temporário de convites atingido. Tente novamente mais tarde.');
        }
        auth_rate_limit_record_failure($pdo, 'institution-invite', (string) $institutionId, 10, 3600);
        create_institution_invitation(
            $pdo,
            $institutionId,
            $actorUserId,
            (string) ($_POST['email'] ?? ''),
            app_base_url()
        );
        $message = 'Convite enviado. A pessoa precisa entrar na conta vinculada ao e-mail para aceitá-lo.';
        $messageType = 'success';
    } elseif ($action === 'revoke') {
        $memberUserId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($memberUserId === false) {
            throw new InvalidArgumentException('Membro inválido.');
        }
        revoke_institution_member($pdo, $institutionId, $actorUserId, (int) $memberUserId);
        $message = 'Acesso do membro revogado.';
        $messageType = 'success';
    } elseif ($action === 'cancel') {
        $invitationId = filter_var($_POST['invitation_id'] ?? null, FILTER_VALIDATE_INT);
        if ($invitationId === false || $invitationId <= 0) {
            throw new InvalidArgumentException('Convite inválido.');
        }
        cancel_institution_invitation($pdo, $institutionId, $actorUserId, (int) $invitationId);
        $message = 'Convite cancelado.';
        $messageType = 'success';
    } else {
        throw new InvalidArgumentException('Ação inválida.');
    }
} catch (InvalidArgumentException | DomainException | RuntimeException $error) {
    $message = $error->getMessage();
} catch (PDOException $error) {
    error_log('[S.A Education][institution_members][PDO] ' . $error->getMessage());
    $message = 'Não foi possível atualizar os membros agora. Tente novamente.';
}

$_SESSION['institution_members_flash'] = ['message' => $message, 'type' => $messageType];
header('Location: instituicao-dashboard.php#members');
exit;
