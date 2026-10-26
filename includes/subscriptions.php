<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function normalize_plan_benefits(string $input): array {
    $benefits = [];
    foreach (preg_split('/\R/u', $input) ?: [] as $line) {
        $benefit = trim($line);
        if ($benefit === '') {
            continue;
        }
        if (mb_strlen($benefit, 'UTF-8') > 240 || count($benefits) >= 20) {
            throw new InvalidArgumentException('Informe até 20 benefícios, com no máximo 240 caracteres cada.');
        }
        $benefits[] = $benefit;
    }

    return $benefits;
}

function replace_product_plan_benefits(PDO $pdo, string $planCode, array $benefits): void {
    $delete = $pdo->prepare('DELETE FROM product_plan_benefits WHERE plan_code = :plan_code');
    $delete->execute(['plan_code' => $planCode]);
    if ($benefits === []) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO product_plan_benefits (plan_code, label, position)
         VALUES (:plan_code, :label, :position)'
    );
    foreach (array_values($benefits) as $position => $benefit) {
        $insert->execute([
            'plan_code' => $planCode,
            'label' => $benefit,
            'position' => $position,
        ]);
    }
}

function account_subscription_target(PDO $pdo, int $userId, bool $institutionAccount): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Conta inválida.');
    }
    if (!$institutionAccount) {
        return ['column' => 'user_id', 'id' => $userId];
    }

    $statement = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
    $statement->execute(['user_id' => $userId]);
    $institutionId = $statement->fetchColumn();
    if ($institutionId === false) {
        throw new DomainException('A instituição desta conta não foi encontrada.');
    }

    return ['column' => 'institution_id', 'id' => (int) $institutionId];
}

function find_account_subscription(PDO $pdo, int $userId, bool $institutionAccount): ?array {
    $target = account_subscription_target($pdo, $userId, $institutionAccount);
    $query = $pdo->prepare(
        'SELECT id, user_id, institution_id, plan_code, status, payment_reference,
                payment_confirmed_at, starts_at, expires_at, canceled_at, created_at
         FROM subscriptions
         WHERE ' . $target['column'] . ' = :account_id
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $query->execute(['account_id' => $target['id']]);
    $subscription = $query->fetch(PDO::FETCH_ASSOC);

    return is_array($subscription) ? $subscription : null;
}

function cancel_account_subscription(
    PDO $pdo,
    int $userId,
    bool $institutionAccount,
    int $subscriptionId
): void {
    if ($subscriptionId <= 0) {
        throw new InvalidArgumentException('Assinatura inválida.');
    }
    $target = account_subscription_target($pdo, $userId, $institutionAccount);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $lockSuffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $pdo->prepare(
            'SELECT s.id, s.status
             FROM subscriptions s
             WHERE s.id = :subscription_id AND s.' . $target['column'] . ' = :account_id' . $lockSuffix
        );
        $query->execute([
            'subscription_id' => $subscriptionId,
            'account_id' => $target['id'],
        ]);
        $subscription = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($subscription)) {
            throw new DomainException('Assinatura não encontrada para esta conta.');
        }
        if (!in_array((string) $subscription['status'], ['active', 'pending'], true)) {
            throw new DomainException('Esta assinatura não pode mais ser cancelada.');
        }

        $update = $pdo->prepare(
            'UPDATE subscriptions
             SET status = "canceled", canceled_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status IN ("active", "pending")'
        );
        $update->execute(['id' => $subscriptionId]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('O estado da assinatura mudou. Atualize a página e tente novamente.');
        }

        $event = $pdo->prepare(
            'INSERT INTO subscription_events
                (subscription_id, event_type, previous_status, new_status, actor_user_id, source)
             VALUES (:subscription_id, "canceled_by_account", :previous_status, "canceled", :actor_user_id, "account")'
        );
        $event->execute([
            'subscription_id' => $subscriptionId,
            'previous_status' => (string) $subscription['status'],
            'actor_user_id' => $userId,
        ]);

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}