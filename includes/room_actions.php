<?php
declare(strict_types=1);

require_once __DIR__ . '/room_access.php';

function room_action_get(PDO $pdo, int $roomId): array {
    $statement = $pdo->prepare(
        'SELECT id, criador_id, institution_id, privacidade
         FROM salas WHERE id = :room_id LIMIT 1'
    );
    $statement->execute(['room_id' => $roomId]);
    $room = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($room)) {
        throw new DomainException('Sala indisponível.');
    }
    return $room;
}

function room_refresh_participant_count(PDO $pdo, int $roomId): void {
    $statement = $pdo->prepare(
        'UPDATE salas SET alunos = (
            SELECT COUNT(*) FROM salas_usuarios
            WHERE sala_id = :room_id AND papel = "aluno" AND status = "ativo"
         ) WHERE id = :update_room_id'
    );
    $statement->execute(['room_id' => $roomId, 'update_room_id' => $roomId]);
}

function room_join(PDO $pdo, int $roomId, int $userId, bool $hasInviteCode = false, bool $isAdmin = false): void {
    if ($userId <= 0) {
        throw new DomainException('Autenticação necessária.');
    }
    $room = room_action_get($pdo, $roomId);
    $privacy = strtolower((string) $room['privacidade']);
    if ($privacy === 'instituicao' && !room_user_can_access($pdo, $room, $userId, $isAdmin)) {
        throw new DomainException('Esta sala é restrita à instituição vinculada.');
    }
    if ($privacy !== 'publica' && !$hasInviteCode && !$isAdmin) {
        throw new DomainException('Esta sala exige código de convite ou aprovação dos responsáveis.');
    }
    if (!in_array($privacy, ['publica', 'privada', 'instituicao'], true)) {
        throw new DomainException('Sala indisponível.');
    }

    $membershipSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status)
           VALUES (:room_id, :user_id, "aluno", "ativo")
           ON CONFLICT(sala_id, usuario_id, status) DO UPDATE SET entrou_em = CURRENT_TIMESTAMP'
        : 'INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status)
           VALUES (:room_id, :user_id, "aluno", "ativo")
           ON DUPLICATE KEY UPDATE entrou_em = CURRENT_TIMESTAMP';
    if ($pdo->inTransaction()) {
        throw new LogicException('A entrada na sala precisa controlar sua própria transação.');
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($membershipSql);
        $statement->execute(['room_id' => $roomId, 'user_id' => $userId]);
        room_refresh_participant_count($pdo, $roomId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function room_request_join(PDO $pdo, int $roomId, int $userId): bool {
    if ($userId <= 0) {
        throw new DomainException('Autenticação necessária.');
    }
    $room = room_action_get($pdo, $roomId);
    if (strtolower((string) $room['privacidade']) !== 'privada') {
        throw new DomainException('Esta sala não aceita solicitações de entrada.');
    }
    if (room_user_is_member($pdo, $roomId, $userId)) {
        throw new DomainException('Você já participa desta sala.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('A solicitação de entrada precisa controlar sua própria transação.');
    }
    $pdo->beginTransaction();
    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $roomLock = $pdo->prepare('SELECT id FROM salas WHERE id = :room_id' . $lockClause);
        $roomLock->execute(['room_id' => $roomId]);
        if ($roomLock->fetchColumn() === false) {
            throw new DomainException('Sala indisponível.');
        }
        $pending = $pdo->prepare(
            'SELECT id FROM room_join_requests
             WHERE room_id = :room_id AND user_id = :user_id AND status = "pending" LIMIT 1'
        );
        $pending->execute(['room_id' => $roomId, 'user_id' => $userId]);
        if ($pending->fetchColumn() !== false) {
            $pdo->commit();
            return false;
        }
        $statement = $pdo->prepare(
            'INSERT INTO room_join_requests (room_id, user_id, status)
             VALUES (:room_id, :user_id, "pending")'
        );
        $statement->execute(['room_id' => $roomId, 'user_id' => $userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function room_leave(PDO $pdo, int $roomId, int $userId): void {
    $room = room_action_get($pdo, $roomId);
    if ($userId <= 0 || !room_user_is_member($pdo, $roomId, $userId)) {
        throw new DomainException('Você não participa desta sala.');
    }
    if ((int) ($room['criador_id'] ?? 0) === $userId) {
        throw new DomainException('O responsável não pode sair da própria sala.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('A saída da sala precisa controlar sua própria transação.');
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'UPDATE salas_usuarios SET status = "removido"
             WHERE sala_id = :room_id AND usuario_id = :user_id AND status = "ativo"'
        );
        $statement->execute(['room_id' => $roomId, 'user_id' => $userId]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('Não foi possível atualizar sua participação.');
        }
        room_refresh_participant_count($pdo, $roomId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function room_review_join_request(
    PDO $pdo,
    int $roomId,
    int $requestId,
    int $actorUserId,
    bool $approve,
    bool $isAdmin = false
): void {
    $room = room_action_get($pdo, $roomId);
    if (!room_user_can_manage($pdo, $room, $actorUserId, $isAdmin)) {
        throw new DomainException('Você não pode revisar solicitações desta sala.');
    }
    if (strtolower((string) $room['privacidade']) !== 'privada') {
        throw new DomainException('Esta sala não usa solicitações de entrada.');
    }

    $pdo->beginTransaction();
    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $roomLock = $pdo->prepare('SELECT id FROM salas WHERE id = :room_id' . $lockClause);
        $roomLock->execute(['room_id' => $roomId]);
        if ($roomLock->fetchColumn() === false) {
            throw new DomainException('Sala indisponível.');
        }
        $statement = $pdo->prepare(
            'SELECT user_id FROM room_join_requests
             WHERE id = :request_id AND room_id = :room_id AND status = "pending"'
             . $lockClause
        );
        $statement->execute(['request_id' => $requestId, 'room_id' => $roomId]);
        $requestedUserId = $statement->fetchColumn();
        if ($requestedUserId === false) {
            throw new DomainException('A solicitação não está mais pendente.');
        }
        if ($approve) {
            $membershipSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status)
                   VALUES (:room_id, :user_id, "aluno", "ativo")
                   ON CONFLICT(sala_id, usuario_id, status) DO UPDATE SET entrou_em = CURRENT_TIMESTAMP'
                : 'INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status)
                   VALUES (:room_id, :user_id, "aluno", "ativo")
                   ON DUPLICATE KEY UPDATE entrou_em = CURRENT_TIMESTAMP';
            $membership = $pdo->prepare(
                $membershipSql
            );
            $membership->execute(['room_id' => $roomId, 'user_id' => (int) $requestedUserId]);
        }
        $update = $pdo->prepare(
            'UPDATE room_join_requests
             SET status = :status, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = :reviewer_id
             WHERE id = :request_id AND status = "pending"'
        );
        $update->execute([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewer_id' => $actorUserId,
            'request_id' => $requestId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('A solicitação já foi revisada.');
        }
        if ($approve) {
            room_refresh_participant_count($pdo, $roomId);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function room_remove_member(PDO $pdo, int $roomId, int $targetUserId, int $actorUserId, bool $isAdmin = false): void {
    $room = room_action_get($pdo, $roomId);
    if (!room_user_can_manage($pdo, $room, $actorUserId, $isAdmin)) {
        throw new DomainException('Você não pode gerenciar os participantes desta sala.');
    }
    if ($targetUserId <= 0 || $targetUserId === (int) ($room['criador_id'] ?? 0)) {
        throw new InvalidArgumentException('Membro inválido.');
    }
    $statement = $pdo->prepare(
        'SELECT papel FROM salas_usuarios
         WHERE sala_id = :room_id AND usuario_id = :user_id AND status = "ativo" LIMIT 1'
    );
    $statement->execute(['room_id' => $roomId, 'user_id' => $targetUserId]);
    $role = $statement->fetchColumn();
    if ($role === false) {
        throw new DomainException('Membro não encontrado.');
    }
    if (!$isAdmin && (int) ($room['criador_id'] ?? 0) !== $actorUserId && in_array($role, ['professor', 'moderador'], true)) {
        throw new DomainException('Moderadores não podem remover outros responsáveis.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('A remoção do participante precisa controlar sua própria transação.');
    }
    $pdo->beginTransaction();
    try {
        $remove = $pdo->prepare(
            'UPDATE salas_usuarios SET status = "removido"
             WHERE sala_id = :room_id AND usuario_id = :user_id AND status = "ativo"'
        );
        $remove->execute(['room_id' => $roomId, 'user_id' => $targetUserId]);
        if ($remove->rowCount() !== 1) {
            throw new DomainException('O acesso do membro não foi atualizado.');
        }
        room_refresh_participant_count($pdo, $roomId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function room_submit_report(
    PDO $pdo,
    int $roomId,
    int $reporterId,
    string $reason,
    string $details,
    string $targetType = 'room',
    int $targetId = 0
): bool {
    if (
        !in_array($reason, ['spam', 'assédio', 'conteúdo inadequado', 'outro'], true)
        || mb_strlen(trim($details)) > 1000
        || !in_array($targetType, ['room', 'publication', 'comment'], true)
        || $targetId < 0
        || ($targetType === 'room' && $targetId !== 0)
        || ($targetType !== 'room' && $targetId <= 0)
    ) {
        throw new InvalidArgumentException('Revise os dados da denúncia.');
    }
    $room = room_action_get($pdo, $roomId);
    if (!room_user_can_access($pdo, $room, $reporterId)) {
        throw new DomainException('Você não pode denunciar uma sala à qual não tem acesso.');
    }
    if ($targetType === 'publication') {
        $target = $pdo->prepare(
            'SELECT 1 FROM publicacoes
             WHERE id = :target_id AND sala_id = :room_id
               AND visibilidade = "sala" AND status = "publicado" LIMIT 1'
        );
        $target->execute(['target_id' => $targetId, 'room_id' => $roomId]);
    } elseif ($targetType === 'comment') {
        $target = $pdo->prepare(
            'SELECT 1 FROM publicacao_comentarios c
             INNER JOIN publicacoes p ON p.id = c.publicacao_id
             WHERE c.id = :target_id AND p.sala_id = :room_id
               AND p.visibilidade = "sala" AND p.status = "publicado" LIMIT 1'
        );
        $target->execute(['target_id' => $targetId, 'room_id' => $roomId]);
    } else {
        $target = null;
    }
    if ($target !== null && !$target->fetchColumn()) {
        throw new DomainException('O conteúdo denunciado não pertence a esta sala ou não está disponível.');
    }
    $insertPrefix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT OR IGNORE'
        : 'INSERT IGNORE';
    $statement = $pdo->prepare(
        $insertPrefix . ' INTO room_reports
            (room_id, reporter_user_id, target_type, target_id, reason, details)
         VALUES (:room_id, :reporter_id, :target_type, :target_id, :reason, :details)'
    );
    $statement->execute([
        'room_id' => $roomId,
        'reporter_id' => $reporterId,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'reason' => $reason,
        'details' => trim($details) !== '' ? trim($details) : null,
    ]);
    return $statement->rowCount() === 1;
}

function room_review_report(
    PDO $pdo,
    int $roomId,
    int $reportId,
    string $status,
    bool $isAdmin,
    int $reviewerId
): void {
    if ($reviewerId <= 0 || (!$isAdmin && !admin_user_can($pdo, $reviewerId, 'admin.reports.manage'))) {
        throw new DomainException('A revisão de denúncias é exclusiva da administração.');
    }
    if ($reportId <= 0 || !in_array($status, ['reviewed', 'dismissed'], true)) {
        throw new InvalidArgumentException('Revisão de denúncia inválida.');
    }
    $statement = $pdo->prepare(
        'UPDATE room_reports SET status = :status, reviewed_by = :reviewer_id
         WHERE id = :report_id AND room_id = :room_id AND status = "pending"'
    );
    $statement->execute([
        'status' => $status,
        'reviewer_id' => $reviewerId,
        'report_id' => $reportId,
        'room_id' => $roomId,
    ]);
    if ($statement->rowCount() !== 1) {
        throw new DomainException('A denúncia já foi analisada ou não existe.');
    }
}
