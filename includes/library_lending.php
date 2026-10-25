<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function library_lending_institution(PDO $pdo): array {
    if (!isset($_SESSION['instituicao_usuario_id'])) {
        throw new PermissionDeniedException('Apenas uma instituição autenticada pode gerenciar exemplares.');
    }
    require_institution_auth();
    if (
        ($_SESSION['instituicao_validada'] ?? false) !== true
        || ($_SESSION['instituicao_assinatura_ativa'] ?? false) !== true
    ) {
        throw new PermissionDeniedException('A instituição precisa estar validada e com assinatura ativa.');
    }

    $statement = $pdo->prepare(
        'SELECT i.id, i.usuario_id, i.nome
         FROM instituicoes i
         WHERE i.usuario_id = :user_id AND i.validation_status = "validated"
         LIMIT 1'
    );
    $statement->execute(['user_id' => (int) $_SESSION['instituicao_usuario_id']]);
    $institution = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($institution)) {
        throw new PermissionDeniedException('A instituição autenticada não tem acesso ao gerenciamento de empréstimos.');
    }

    return [
        'id' => (int) $institution['id'],
        'user_id' => (int) $institution['usuario_id'],
        'name' => (string) $institution['nome'],
    ];
}

function library_lending_readable_material(PDO $pdo, int $materialId, int $institutionId): ?array {
    $statement = $pdo->prepare(
        'SELECT id, titulo, disponibilidade, revisao_status, status,
                direitos_status, direitos_declarados, instituicao_id
         FROM biblioteca
         WHERE id = :material_id AND instituicao_id = :institution_id
         LIMIT 1'
    );
    $statement->execute(['material_id' => $materialId, 'institution_id' => $institutionId]);
    $material = $statement->fetch(PDO::FETCH_ASSOC);
    if (
        !is_array($material)
        || (string) $material['revisao_status'] !== 'approved'
        || (string) $material['status'] !== 'ativo'
        || (int) $material['direitos_declarados'] !== 1
        || (string) $material['direitos_status'] === 'unknown'
    ) {
        return null;
    }
    return $material;
}

function library_lending_create_copy(PDO $pdo, array $institution, array $input): int {
    $materialId = filter_var($input['material_id'] ?? null, FILTER_VALIDATE_INT);
    $copyCode = $input['copy_code'] ?? '';
    $format = $input['format'] ?? '';
    $accessRules = $input['access_rules'] ?? '';
    $authorizationEvidence = $input['authorization_evidence'] ?? '';
    $authorized = ($input['distribution_authorized'] ?? '') === '1';
    if (
        $materialId === false || $materialId === null || $materialId <= 0
        || !is_string($copyCode) || trim($copyCode) === '' || mb_strlen(trim($copyCode), 'UTF-8') > 100
        || !is_string($format) || !in_array($format, ['physical', 'digital'], true)
        || !is_string($accessRules) || mb_strlen($accessRules, 'UTF-8') > 2000
        || !is_string($authorizationEvidence) || mb_strlen($authorizationEvidence, 'UTF-8') > 2000
    ) {
        throw new InvalidArgumentException('Revise os dados do exemplar.');
    }
    if ($format === 'digital' && (!$authorized || trim($authorizationEvidence) === '')) {
        throw new InvalidArgumentException('Confirme e informe a evidência de autorização para disponibilizar uma cópia digital.');
    }
    if ($format === 'physical' && $authorized) {
        throw new InvalidArgumentException('A confirmação de distribuição digital só se aplica a exemplares digitais.');
    }

    $material = library_lending_readable_material($pdo, (int) $materialId, (int) $institution['id']);
    if ($material === null) {
        throw new DomainException('Material institucional não encontrado ou indisponível para empréstimo.');
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE biblioteca SET disponibilidade = "emprestimo"
             WHERE id = :material_id AND instituicao_id = :institution_id'
        );
        $update->execute(['material_id' => (int) $materialId, 'institution_id' => (int) $institution['id']]);
        $insert = $pdo->prepare(
            'INSERT INTO library_copies
                (material_id, institution_id, copy_code, format, status, distribution_authorized, access_rules, authorization_evidence)
             VALUES
                (:material_id, :institution_id, :copy_code, :format, "available", :distribution_authorized, :access_rules, :authorization_evidence)'
        );
        $insert->execute([
            'material_id' => (int) $materialId,
            'institution_id' => (int) $institution['id'],
            'copy_code' => trim($copyCode),
            'format' => $format,
            'distribution_authorized' => $authorized ? 1 : 0,
            'access_rules' => trim($accessRules) !== '' ? trim($accessRules) : null,
            'authorization_evidence' => $format === 'digital' ? trim($authorizationEvidence) : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function library_lending_request(PDO $pdo, int $userId, int $copyId): int {
    if ($userId <= 0 || $copyId <= 0) {
        throw new InvalidArgumentException('Exemplar inválido.');
    }
    $pdo->beginTransaction();
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $copyQuery = $pdo->prepare(
            'SELECT c.id, c.institution_id, c.format, c.status, c.distribution_authorized,
                    c.authorization_evidence, i.usuario_id
             FROM library_copies c
             INNER JOIN instituicoes i ON i.id = c.institution_id
             INNER JOIN biblioteca b ON b.id = c.material_id
             WHERE c.id = :copy_id
               AND c.status = "available"
               AND i.validation_status = "validated"
               AND b.disponibilidade = "emprestimo"
               AND b.revisao_status = "approved"
               AND b.status = "ativo"
               AND b.direitos_declarados = 1
               AND b.direitos_status <> "unknown"' . $lock
        );
        $copyQuery->execute(['copy_id' => $copyId]);
        $copy = $copyQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($copy)) {
            throw new DomainException('Este exemplar não está disponível para solicitação.');
        }
        if (
            (string) $copy['format'] === 'digital'
            && ((int) $copy['distribution_authorized'] !== 1 || trim((string) $copy['authorization_evidence']) === '')
        ) {
            throw new DomainException('O exemplar digital não possui autorização registrada para distribuição.');
        }
        $existing = $pdo->prepare(
            'SELECT id FROM library_loans
             WHERE copy_id = :copy_id AND status IN ("requested", "active", "overdue")
             LIMIT 1'
        );
        $existing->execute(['copy_id' => $copyId]);
        if ($existing->fetchColumn() !== false) {
            throw new DomainException('Este exemplar já tem uma solicitação ou empréstimo em andamento.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO library_loans
                (copy_id, user_id, responsible_user_id, status, borrowed_at, due_at)
             VALUES
                (:copy_id, :user_id, :responsible_user_id, "requested", NULL, NULL)'
        );
        $insert->execute([
            'copy_id' => $copyId,
            'user_id' => $userId,
            'responsible_user_id' => (int) $copy['usuario_id'],
        ]);
        $markRequested = $pdo->prepare(
            'UPDATE library_copies SET status = "requested" WHERE id = :copy_id AND status = "available"'
        );
        $markRequested->execute(['copy_id' => $copyId]);
        if ($markRequested->rowCount() !== 1) {
            throw new DomainException('Este exemplar recebeu outra solicitação. Recarregue a página.');
        }
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function library_lending_decide(PDO $pdo, array $institution, int $loanId, string $decision, int $loanDays = 14): void {
    if ($loanId <= 0 || !in_array($decision, ['issue', 'reject', 'return'], true)) {
        throw new InvalidArgumentException('Decisão de empréstimo inválida.');
    }
    if ($decision === 'issue' && ($loanDays < 1 || $loanDays > 90)) {
        throw new InvalidArgumentException('O período deve ser de 1 a 90 dias.');
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $pdo->prepare(
            'SELECT l.id, l.status
             FROM library_loans l
             INNER JOIN library_copies c ON c.id = l.copy_id
             WHERE l.id = :loan_id AND c.institution_id = :institution_id' . $lock
        );
        $statement->execute(['loan_id' => $loanId, 'institution_id' => (int) $institution['id']]);
        $loan = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($loan)) {
            throw new DomainException('Solicitação de empréstimo não encontrada.');
        }

        if ($decision === 'issue' && (string) $loan['status'] === 'requested') {
            $dueDateExpression = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "datetime('now', '+{$loanDays} days')"
                : 'DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . $loanDays . ' DAY)';
            $update = $pdo->prepare(
                'UPDATE library_loans
                 SET status = "active", borrowed_at = CURRENT_TIMESTAMP,
                     due_at = ' . $dueDateExpression . '
                 WHERE id = :loan_id AND status = "requested"'
            );
        } elseif ($decision === 'reject' && (string) $loan['status'] === 'requested') {
            $update = $pdo->prepare('UPDATE library_loans SET status = "canceled" WHERE id = :loan_id AND status = "requested"');
        } elseif (
            $decision === 'return'
            && in_array((string) $loan['status'], ['active', 'overdue'], true)
        ) {
            $update = $pdo->prepare(
                'UPDATE library_loans
                 SET status = "returned", returned_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id AND status IN ("active", "overdue")'
            );
        } else {
            throw new DomainException('A solicitação não está em um estado que permita essa ação.');
        }
        $update->execute(['loan_id' => $loanId]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('O empréstimo foi atualizado por outra operação. Recarregue a página.');
        }
        if ($decision === 'issue') {
            $copy = $pdo->prepare('UPDATE library_copies SET status = "on_loan" WHERE id = (SELECT copy_id FROM library_loans WHERE id = :loan_id)');
            $copy->execute(['loan_id' => $loanId]);
        } elseif ($decision === 'reject' || $decision === 'return') {
            $copy = $pdo->prepare('UPDATE library_copies SET status = "available" WHERE id = (SELECT copy_id FROM library_loans WHERE id = :loan_id)');
            $copy->execute(['loan_id' => $loanId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function library_lending_set_copy_status(PDO $pdo, array $institution, int $copyId, string $status): void {
    if ($copyId <= 0 || !in_array($status, ['available', 'maintenance', 'unavailable'], true)) {
        throw new InvalidArgumentException('Disponibilidade do exemplar inválida.');
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $copy = $pdo->prepare(
            'SELECT c.id, c.status
             FROM library_copies c
             WHERE c.id = :copy_id AND c.institution_id = :institution_id' . $lock
        );
        $copy->execute(['copy_id' => $copyId, 'institution_id' => (int) $institution['id']]);
        $current = $copy->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current)) {
            throw new DomainException('Exemplar não encontrado.');
        }
        if (!in_array((string) $current['status'], ['available', 'maintenance', 'unavailable'], true)) {
            throw new DomainException('Não é possível alterar a disponibilidade de um exemplar emprestado.');
        }
        $openLoan = $pdo->prepare(
            'SELECT id FROM library_loans
             WHERE copy_id = :copy_id AND status IN ("requested", "active", "overdue")
             LIMIT 1'
        );
        $openLoan->execute(['copy_id' => $copyId]);
        if ($openLoan->fetchColumn() !== false) {
            throw new DomainException('Resolva as solicitações ou empréstimos pendentes antes de alterar a disponibilidade.');
        }
        $update = $pdo->prepare(
            'UPDATE library_copies
             SET status = :status
             WHERE id = :copy_id AND institution_id = :institution_id'
        );
        $update->execute([
            'status' => $status,
            'copy_id' => $copyId,
            'institution_id' => (int) $institution['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
