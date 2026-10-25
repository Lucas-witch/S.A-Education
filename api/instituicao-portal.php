<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/institution_portal.php';

try {
    $institutionLogin = isset($_SESSION['instituicao_usuario_id']);
    if ($institutionLogin) {
        require_institution_auth();
        $actorUserId = (int) $_SESSION['instituicao_usuario_id'];
    } else {
        require_auth();
        if (!in_array(current_user_perfil(), ['estudante', 'professor'], true)) {
            throw new PermissionDeniedException('Este perfil não possui acesso ao portal acadêmico.');
        }
        if (current_user_perfil() === 'professor' && ($_SESSION['usuario_aprovado'] ?? false) !== true) {
            throw new PermissionDeniedException('O perfil docente precisa estar aprovado.');
        }
        $actorUserId = (int) $_SESSION['usuario_id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $requestedId = filter_input(INPUT_GET, 'institution_id', FILTER_VALIDATE_INT);
        if ($requestedId === false || ($requestedId !== null && $requestedId <= 0)) {
            throw new InvalidArgumentException('Instituição inválida.');
        }
        $context = institution_portal_context(
            $pdo,
            $actorUserId,
            $requestedId !== null ? (int) $requestedId : null,
            $institutionLogin
        );
        $dashboard = institution_portal_dashboard($pdo, $context);
        $availableInstitutions = [];
        if (!$institutionLogin) {
            $memberships = $pdo->prepare(
                'SELECT i.id, i.nome
                 FROM institution_members im
                 INNER JOIN instituicoes i ON i.id = im.institution_id
                 WHERE im.user_id = :user_id AND im.status = "active"
                 ORDER BY i.nome'
            );
            $memberships->execute(['user_id' => $actorUserId]);
            $availableInstitutions = $memberships->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $availableInstitutions[] = [
                'id' => $context['institution_id'],
                'nome' => $context['institution_name'],
            ];
        }
        echo json_encode(
            $dashboard + ['available_institutions' => $availableInstitutions],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }
    verify_csrf($_POST['csrf'] ?? null);
    $requestedId = filter_var($_POST['institution_id'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId === false || $requestedId === null || $requestedId <= 0) {
        throw new InvalidArgumentException('Instituição inválida.');
    }
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        throw new InvalidArgumentException('Ação inválida.');
    }
    $context = institution_portal_context($pdo, $actorUserId, (int) $requestedId, $institutionLogin);
    institution_portal_require_active($context);
    $recordId = institution_portal_handle_action($pdo, $context, $action, $_POST);
    echo json_encode(
        ['ok' => true, 'id' => $recordId, 'message' => 'Alterações acadêmicas salvas.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (PermissionDeniedException $error) {
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DomainException $error) {
    http_response_code(409);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $error) {
    error_log('[S.A Education][institution_portal][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar o portal acadêmico agora.']);
} catch (Throwable $error) {
    error_log('[S.A Education][institution_portal] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar o portal acadêmico agora.']);
}
