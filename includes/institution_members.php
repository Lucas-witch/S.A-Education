<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/smtp_mailer.php';

function institution_assert_owner(PDO $pdo, int $institutionId, int $actorUserId, bool $requireActiveSubscription): array {
    $statement = $pdo->prepare(
        'SELECT id, usuario_id, nome, validation_status
         FROM instituicoes WHERE id = :institution_id LIMIT 1'
    );
    $statement->execute(['institution_id' => $institutionId]);
    $institution = $statement->fetch(PDO::FETCH_ASSOC);
    if (
        !is_array($institution)
        || (int) $institution['usuario_id'] !== $actorUserId
        || $actorUserId <= 0
    ) {
        throw new DomainException('A instituição não corresponde à conta autenticada.');
    }

    if ($requireActiveSubscription) {
        $permissions = resolve_account_permissions($pdo, $actorUserId);
        if (
            $permissions['profile'] !== 'instituicao'
            || !$permissions['institution_validated']
            || !$permissions['premium']
        ) {
            throw new DomainException('A instituição precisa estar validada e ter uma assinatura ativa para convidar membros.');
        }
    }

    return $institution;
}

function institution_record_audit(
    PDO $pdo,
    int $institutionId,
    int $actorUserId,
    string $action,
    ?int $targetUserId = null,
    array $details = []
): void {
    $statement = $pdo->prepare(
        'INSERT INTO institution_audit_log
            (institution_id, actor_user_id, action, target_user_id, details)
         VALUES (:institution_id, :actor_user_id, :action, :target_user_id, :details)'
    );
    $statement->execute([
        'institution_id' => $institutionId,
        'actor_user_id' => $actorUserId,
        'action' => $action,
        'target_user_id' => $targetUserId,
        'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
    ]);
}

function create_institution_invitation(
    PDO $pdo,
    int $institutionId,
    int $actorUserId,
    string $email,
    string $baseUrl,
    ?callable $sendEmail = null
): void {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        throw new InvalidArgumentException('Informe um e-mail válido de uma conta já cadastrada.');
    }

    $institution = institution_assert_owner($pdo, $institutionId, $actorUserId, true);
    $userQuery = $pdo->prepare(
        'SELECT id, email, perfil, professor_status
         FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1'
    );
    $userQuery->execute(['email' => $email]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        throw new DomainException('Não existe uma conta cadastrada com esse e-mail.');
    }

    $profile = (string) $user['perfil'];
    if ($profile === 'estudante') {
        $memberType = 'student';
    } elseif ($profile === 'professor' && (string) $user['professor_status'] === 'approved') {
        $memberType = 'teacher';
    } else {
        throw new DomainException('Somente contas de estudante ou de professor aprovado podem ser convidadas.');
    }

    $targetUserId = (int) $user['id'];
    if ($targetUserId === $actorUserId) {
        throw new DomainException('A conta institucional não pode ser convidada como membro.');
    }

    $existingMember = $pdo->prepare(
        'SELECT status FROM institution_members
         WHERE institution_id = :institution_id AND user_id = :user_id LIMIT 1'
    );
    $existingMember->execute(['institution_id' => $institutionId, 'user_id' => $targetUserId]);
    if ($existingMember->fetchColumn() === 'active') {
        throw new DomainException('Essa conta já é membro ativo da instituição.');
    }

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+7 days')
        ->format('Y-m-d H:i:s');
    $invitationId = 0;

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE institution_invitations
             SET status = "canceled"
             WHERE institution_id = :institution_id AND invited_user_id = :user_id AND status = "pending"'
        )->execute(['institution_id' => $institutionId, 'user_id' => $targetUserId]);

        $insert = $pdo->prepare(
            'INSERT INTO institution_invitations
                (institution_id, invited_user_id, inviter_user_id, member_type, token_hash, expires_at)
             VALUES (:institution_id, :invited_user_id, :inviter_user_id, :member_type, :token_hash, :expires_at)'
        );
        $insert->execute([
            'institution_id' => $institutionId,
            'invited_user_id' => $targetUserId,
            'inviter_user_id' => $actorUserId,
            'member_type' => $memberType,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
        $invitationId = (int) $pdo->lastInsertId();
        institution_record_audit($pdo, $institutionId, $actorUserId, 'member_invitation_created', $targetUserId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $sendEmail ??= static function (string $recipient, string $subject, string $body): void {
        send_smtp_message($recipient, $subject, $body);
    };
    $url = rtrim($baseUrl, '/') . '/aceitar-convite-instituicao.php?token=' . rawurlencode($token);
    $body = 'A instituição ' . (string) $institution['nome']
        . " convidou você para fazer parte da S.A Education.\n\n"
        . "Entre na sua conta S.A Education e abra este link para aceitar o convite:\n"
        . $url . "\n\n"
        . "O convite expira em 7 dias. Se você não esperava este convite, ignore esta mensagem.";

    try {
        $sendEmail((string) $user['email'], 'Convite institucional | S.A Education', $body);
    } catch (Throwable $error) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE institution_invitations SET status = "failed"
                 WHERE id = :invitation_id AND status = "pending"'
            )->execute(['invitation_id' => $invitationId]);
            institution_record_audit(
                $pdo,
                $institutionId,
                $actorUserId,
                'member_invitation_email_failed',
                $targetUserId
            );
            $pdo->commit();
        } catch (Throwable $auditError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[S.A Education][institution_invitation_audit] ' . $auditError->getMessage());
        }
        error_log('[S.A Education][institution_invitation_email] ' . $error->getMessage());
        throw new RuntimeException('Não foi possível enviar o convite. Verifique a configuração de e-mail e tente novamente.', 0, $error);
    }
}

function accept_institution_invitation(PDO $pdo, int $userId, string $token): void {
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
        throw new InvalidArgumentException('O convite é inválido ou expirou.');
    }

    $pdo->beginTransaction();
    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $pdo->prepare(
            'SELECT ii.id, ii.institution_id, ii.invited_user_id, ii.member_type, ii.status,
                    ii.expires_at, i.usuario_id AS institution_owner_id
             FROM institution_invitations ii
             INNER JOIN instituicoes i ON i.id = ii.institution_id
             WHERE ii.token_hash = :token_hash
             LIMIT 1' . $lockClause
        );
        $query->execute(['token_hash' => hash('sha256', $token)]);
        $invitation = $query->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($invitation)
            || (int) $invitation['invited_user_id'] !== $userId
            || (string) $invitation['status'] !== 'pending'
            || new DateTimeImmutable((string) $invitation['expires_at'], new DateTimeZone('UTC'))
                <= new DateTimeImmutable('now', new DateTimeZone('UTC'))
        ) {
            throw new DomainException('O convite é inválido, expirou ou pertence a outra conta.');
        }

        $institutionId = (int) $invitation['institution_id'];
        $institutionOwnerId = (int) $invitation['institution_owner_id'];
        institution_assert_owner($pdo, $institutionId, $institutionOwnerId, true);

        $userQuery = $pdo->prepare('SELECT perfil, professor_status FROM usuarios WHERE id = :id LIMIT 1');
        $userQuery->execute(['id' => $userId]);
        $user = $userQuery->fetch(PDO::FETCH_ASSOC);
        $validType = is_array($user)
            && (
                ((string) $user['perfil'] === 'estudante' && (string) $invitation['member_type'] === 'student')
                || ((string) $user['perfil'] === 'professor'
                    && (string) $user['professor_status'] === 'approved'
                    && (string) $invitation['member_type'] === 'teacher')
            );
        if (!$validType) {
            throw new DomainException('O perfil da conta não pode aceitar este convite.');
        }

        $memberQuery = $pdo->prepare(
            'SELECT id FROM institution_members
             WHERE institution_id = :institution_id AND user_id = :user_id LIMIT 1'
        );
        $memberQuery->execute(['institution_id' => $institutionId, 'user_id' => $userId]);
        $memberId = $memberQuery->fetchColumn();
        if ($memberId === false) {
            $pdo->prepare(
                'INSERT INTO institution_members (institution_id, user_id, member_type, status)
                 VALUES (:institution_id, :user_id, :member_type, "active")'
            )->execute([
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'member_type' => (string) $invitation['member_type'],
            ]);
        } else {
            $pdo->prepare(
                'UPDATE institution_members
                 SET member_type = :member_type, status = "active", joined_at = CURRENT_TIMESTAMP, revoked_at = NULL
                 WHERE id = :id'
            )->execute(['member_type' => (string) $invitation['member_type'], 'id' => (int) $memberId]);
        }
        $acceptedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $pdo->prepare(
            'UPDATE institution_invitations
             SET status = "accepted", accepted_at = :accepted_at
             WHERE id = :id'
        )->execute(['accepted_at' => $acceptedAt, 'id' => (int) $invitation['id']]);
        institution_record_audit($pdo, $institutionId, $userId, 'member_invitation_accepted', $userId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function revoke_institution_member(PDO $pdo, int $institutionId, int $actorUserId, int $memberUserId): void {
    institution_assert_owner($pdo, $institutionId, $actorUserId, false);
    if ($memberUserId <= 0 || $memberUserId === $actorUserId) {
        throw new InvalidArgumentException('Membro inválido.');
    }

    $pdo->beginTransaction();
    try {
        $revokedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $statement = $pdo->prepare(
            'UPDATE institution_members
             SET status = "revoked", revoked_at = :revoked_at
             WHERE institution_id = :institution_id AND user_id = :user_id AND status = "active"'
        );
        $statement->execute([
            'revoked_at' => $revokedAt,
            'institution_id' => $institutionId,
            'user_id' => $memberUserId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('O membro ativo não foi encontrado nesta instituição.');
        }
        institution_record_audit($pdo, $institutionId, $actorUserId, 'member_access_revoked', $memberUserId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function cancel_institution_invitation(PDO $pdo, int $institutionId, int $actorUserId, int $invitationId): void {
    institution_assert_owner($pdo, $institutionId, $actorUserId, false);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'UPDATE institution_invitations SET status = "canceled"
             WHERE id = :id AND institution_id = :institution_id AND status = "pending"'
        );
        $statement->execute(['id' => $invitationId, 'institution_id' => $institutionId]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('O convite pendente não foi encontrado nesta instituição.');
        }
        institution_record_audit($pdo, $institutionId, $actorUserId, 'member_invitation_canceled');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
