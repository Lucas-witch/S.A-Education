<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

function room_creation_is_allowed(array $permissions): bool {
    if (!empty($permissions['is_admin'])) {
        return true;
    }

    if (($permissions['profile'] ?? '') === 'instituicao') {
        return !empty($permissions['premium']) && !empty($permissions['institution_validated']);
    }

    return in_array($permissions['profile'] ?? '', ['estudante', 'professor'], true)
        && !empty($permissions['premium'])
        && !empty($permissions['approved']);
}

function room_has_institution_access(PDO $pdo, int $institutionId, int $userId): bool {
    if ($institutionId <= 0 || $userId <= 0) {
        return false;
    }

    $accessCondition = institution_content_access_condition(
        'i.id',
        ':institution_owner_user_id',
        ':institution_member_user_id'
    );
    $statement = $pdo->prepare(
        'SELECT 1 FROM instituicoes i
         WHERE i.id = :institution_id AND ' . $accessCondition . '
         LIMIT 1'
    );
    $statement->execute([
        'institution_id' => $institutionId,
        'institution_owner_user_id' => $userId,
        'institution_member_user_id' => $userId,
    ]);

    return (bool) $statement->fetchColumn();
}

function room_user_is_member(PDO $pdo, int $roomId, int $userId): bool {
    $statement = $pdo->prepare(
        'SELECT 1 FROM salas_usuarios
         WHERE sala_id = :room_id AND usuario_id = :user_id AND status = "ativo"
         LIMIT 1'
    );
    $statement->execute(['room_id' => $roomId, 'user_id' => $userId]);
    return (bool) $statement->fetchColumn();
}

function room_user_can_access(PDO $pdo, array $room, int $userId, bool $isAdmin = false): bool {
    if ($isAdmin) {
        return true;
    }

    $privacy = strtolower((string) ($room['privacidade'] ?? ''));
    if ($privacy === 'publica') {
        return true;
    }

    if ($privacy === 'instituicao') {
        return room_has_institution_access($pdo, (int) ($room['institution_id'] ?? 0), $userId);
    }

    return $privacy === 'privada'
        && $userId > 0
        && (
            (int) ($room['criador_id'] ?? 0) === $userId
            || room_user_is_member($pdo, (int) ($room['id'] ?? 0), $userId)
        );
}

function room_user_can_manage(PDO $pdo, array $room, int $userId, bool $isAdmin = false): bool {
    if ($isAdmin) {
        return true;
    }

    if ($userId <= 0 || !room_user_can_access($pdo, $room, $userId)) {
        return false;
    }
    if ((int) ($room['criador_id'] ?? 0) === $userId) {
        return true;
    }

    $statement = $pdo->prepare(
        'SELECT 1 FROM salas_usuarios
         WHERE sala_id = :room_id AND usuario_id = :user_id
           AND status = "ativo" AND papel IN ("professor", "moderador")
         LIMIT 1'
    );
    $statement->execute(['room_id' => (int) ($room['id'] ?? 0), 'user_id' => $userId]);
    return (bool) $statement->fetchColumn();
}
