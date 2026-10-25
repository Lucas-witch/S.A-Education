<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/library_lending.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_SESSION['instituicao_usuario_id'])) {
            $institution = library_lending_institution($pdo);
            $copies = $pdo->prepare(
                'SELECT c.id, c.material_id, c.copy_code, c.format, c.status,
                        c.distribution_authorized, c.authorization_evidence, c.access_rules,
                        b.titulo, b.autor
                 FROM library_copies c
                 INNER JOIN biblioteca b ON b.id = c.material_id
                 WHERE c.institution_id = :institution_id
                 ORDER BY b.titulo, c.copy_code'
            );
            $copies->execute(['institution_id' => $institution['id']]);
            $materials = $pdo->prepare(
                'SELECT id, titulo, autor, tipo
                 FROM biblioteca
                 WHERE instituicao_id = :institution_id
                   AND revisao_status = "approved"
                   AND status = "ativo"
                   AND direitos_declarados = 1
                   AND direitos_status <> "unknown"
                 ORDER BY titulo
                 LIMIT 200'
            );
            $materials->execute(['institution_id' => $institution['id']]);
            $loans = $pdo->prepare(
                'SELECT l.id,
                        CASE WHEN l.status = "active" AND l.due_at < CURRENT_TIMESTAMP THEN "overdue" ELSE l.status END AS status,
                        l.borrowed_at, l.due_at, l.returned_at,
                        l.user_id, u.nome_completo AS borrower_name, u.email AS borrower_email,
                        c.copy_code, c.format, b.titulo
                 FROM library_loans l
                 INNER JOIN library_copies c ON c.id = l.copy_id
                 INNER JOIN biblioteca b ON b.id = c.material_id
                 INNER JOIN usuarios u ON u.id = l.user_id
                 WHERE c.institution_id = :institution_id
                 ORDER BY CASE l.status WHEN "requested" THEN 0 WHEN "active" THEN 1 WHEN "overdue" THEN 2 ELSE 3 END,
                          l.created_at DESC
                 LIMIT 200'
            );
            $loans->execute(['institution_id' => $institution['id']]);
            echo json_encode([
                'mode' => 'institution',
                'copies' => $copies->fetchAll(PDO::FETCH_ASSOC),
                'materials' => $materials->fetchAll(PDO::FETCH_ASSOC),
                'loans' => $loans->fetchAll(PDO::FETCH_ASSOC),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        require_auth();
        $userId = (int) $_SESSION['usuario_id'];
        $copies = $pdo->prepare(
            'SELECT c.id, c.copy_code, c.format, c.access_rules, c.institution_id,
                    i.nome AS institution_name, b.titulo, b.autor, b.materia
             FROM library_copies c
             INNER JOIN instituicoes i ON i.id = c.institution_id AND i.validation_status = "validated"
             INNER JOIN biblioteca b ON b.id = c.material_id
             WHERE c.status = "available"
               AND b.disponibilidade = "emprestimo"
               AND b.revisao_status = "approved"
               AND b.status = "ativo"
               AND b.direitos_declarados = 1
               AND b.direitos_status <> "unknown"
               AND (c.format <> "digital" OR (c.distribution_authorized = 1 AND c.authorization_evidence IS NOT NULL AND c.authorization_evidence <> ""))
               AND NOT EXISTS (
                   SELECT 1 FROM library_loans active_loan
                   WHERE active_loan.copy_id = c.id
                     AND active_loan.user_id = :user_id
                     AND active_loan.status IN ("requested", "active", "overdue")
               )
             ORDER BY i.nome, b.titulo
             LIMIT 200'
        );
        $copies->execute(['user_id' => $userId]);
        $loans = $pdo->prepare(
            'SELECT l.id,
                    CASE WHEN l.status = "active" AND l.due_at < CURRENT_TIMESTAMP THEN "overdue" ELSE l.status END AS status,
                    l.borrowed_at, l.due_at, l.returned_at,
                    c.copy_code, c.format, b.titulo, i.nome AS institution_name
             FROM library_loans l
             INNER JOIN library_copies c ON c.id = l.copy_id
             INNER JOIN biblioteca b ON b.id = c.material_id
             INNER JOIN instituicoes i ON i.id = c.institution_id
             WHERE l.user_id = :user_id
             ORDER BY l.created_at DESC
             LIMIT 100'
        );
        $loans->execute(['user_id' => $userId]);
        echo json_encode([
            'mode' => 'reader',
            'copies' => $copies->fetchAll(PDO::FETCH_ASSOC),
            'loans' => $loans->fetchAll(PDO::FETCH_ASSOC),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }

    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        throw new InvalidArgumentException('Ação inválida.');
    }

    if ($action === 'request') {
        if (isset($_SESSION['instituicao_usuario_id'])) {
            throw new PermissionDeniedException('Uma conta institucional não pode solicitar empréstimo como leitor.');
        }
        require_auth();
        $copyId = filter_var($_POST['copy_id'] ?? null, FILTER_VALIDATE_INT);
        if ($copyId === false || $copyId === null || $copyId <= 0) {
            throw new InvalidArgumentException('Exemplar inválido.');
        }
        $loanId = library_lending_request($pdo, (int) $_SESSION['usuario_id'], (int) $copyId);
        echo json_encode(['ok' => true, 'loan_id' => $loanId, 'message' => 'Solicitação enviada à instituição.']);
        exit;
    }

    $institution = library_lending_institution($pdo);
    if ($action === 'create_copy') {
        $copyId = library_lending_create_copy($pdo, $institution, $_POST);
        echo json_encode(['ok' => true, 'copy_id' => $copyId, 'message' => 'Exemplar cadastrado.']);
        exit;
    }
    if ($action === 'decide') {
        $loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = $_POST['decision'] ?? '';
        $loanDays = filter_var($_POST['loan_days'] ?? '14', FILTER_VALIDATE_INT);
        if (
            $loanId === false || $loanId === null || $loanId <= 0
            || !is_string($decision)
            || $loanDays === false || $loanDays === null
        ) {
            throw new InvalidArgumentException('Decisão de empréstimo inválida.');
        }
        library_lending_decide($pdo, $institution, (int) $loanId, $decision, (int) $loanDays);
        echo json_encode(['ok' => true, 'message' => 'Empréstimo atualizado.']);
        exit;
    }
    if ($action === 'availability') {
        $copyId = filter_var($_POST['copy_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';
        if ($copyId === false || $copyId === null || $copyId <= 0 || !is_string($status)) {
            throw new InvalidArgumentException('Disponibilidade do exemplar inválida.');
        }
        library_lending_set_copy_status($pdo, $institution, (int) $copyId, $status);
        echo json_encode(['ok' => true, 'message' => 'Disponibilidade atualizada.']);
        exit;
    }

    throw new InvalidArgumentException('Ação de empréstimo inválida.');
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
    error_log('[S.A Education][library_lending][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar o empréstimo agora.']);
} catch (Throwable $error) {
    error_log('[S.A Education][library_lending] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar o empréstimo agora.']);
}
