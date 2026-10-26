<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'up' || count($argv) !== 2) {
    fwrite(STDERR, "Uso: php db/migrate_plans_system.php up\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$tableExists = static function (string $table) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int) $statement->fetchColumn() > 0;
};
$column = static function (string $table, string $name) use ($pdo): ?array {
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $name]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($result) ? $result : null;
};
$indexExists = static function (string $table, string $name) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $statement->execute(['table_name' => $table, 'index_name' => $name]);
    return (int) $statement->fetchColumn() > 0;
};
$foreignKeyExists = static function (string $table, string $name) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = "FOREIGN KEY"'
    );
    $statement->execute(['table_name' => $table, 'constraint_name' => $name]);
    return (int) $statement->fetchColumn() > 0;
};

foreach (['product_plans', 'subscriptions', 'usuarios'] as $requiredTable) {
    if (!$tableExists($requiredTable)) {
        throw new RuntimeException(
            'A tabela ' . $requiredTable . ' não existe. Execute primeiro php db/migrate_permissions.php up.'
        );
    }
}

$planColumns = [
    'display_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER code",
    'description' => 'TEXT DEFAULT NULL AFTER display_name',
    'billing_period' => "ENUM('monthly','annual','one_time') NOT NULL DEFAULT 'monthly' AFTER description",
    'institution_member_limit' => 'SMALLINT UNSIGNED DEFAULT NULL AFTER can_create_courses',
    'institution_class_limit' => 'SMALLINT UNSIGNED DEFAULT NULL AFTER institution_member_limit',
];
foreach ($planColumns as $name => $definition) {
    if ($column('product_plans', $name) === null) {
        $pdo->exec('ALTER TABLE product_plans ADD COLUMN ' . $name . ' ' . $definition);
    }
}

$subscriptionColumns = [
    'provider_code' => 'VARCHAR(80) DEFAULT NULL AFTER payment_reference',
    'provider_transaction_id' => 'VARCHAR(190) DEFAULT NULL AFTER provider_code',
    'canceled_at' => 'DATETIME DEFAULT NULL AFTER expires_at',
];
foreach ($subscriptionColumns as $name => $definition) {
    if ($column('subscriptions', $name) === null) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN ' . $name . ' ' . $definition);
    }
}

$statusColumn = $column('subscriptions', 'status');
if ($statusColumn !== null && strpos((string) $statusColumn['COLUMN_TYPE'], "'suspended'") === false) {
    $pdo->exec(
        "ALTER TABLE subscriptions
         MODIFY COLUMN status ENUM('pending','active','expired','canceled','suspended')
         NOT NULL DEFAULT 'pending'"
    );
}

if (!$indexExists('subscriptions', 'uniq_subscription_provider_transaction')) {
    $pdo->exec(
        'ALTER TABLE subscriptions
         ADD UNIQUE KEY uniq_subscription_provider_transaction (provider_code, provider_transaction_id)'
    );
}

if (!$tableExists('product_plan_benefits')) {
    $pdo->exec(
        "CREATE TABLE product_plan_benefits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_code VARCHAR(80) NOT NULL,
            label VARCHAR(240) NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_product_plan_benefit_position (plan_code, position),
            CONSTRAINT fk_product_plan_benefit_plan
                FOREIGN KEY (plan_code) REFERENCES product_plans (code) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

if (!$tableExists('subscription_events')) {
    $pdo->exec(
        "CREATE TABLE subscription_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            previous_status VARCHAR(20) DEFAULT NULL,
            new_status VARCHAR(20) DEFAULT NULL,
            actor_user_id INT(11) DEFAULT NULL,
            source VARCHAR(40) NOT NULL,
            provider_event_id VARCHAR(190) DEFAULT NULL,
            details JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_subscription_provider_event (source, provider_event_id),
            KEY idx_subscription_event_history (subscription_id, created_at, id),
            KEY idx_subscription_event_actor (actor_user_id, created_at),
            CONSTRAINT fk_subscription_event_subscription
                FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE,
            CONSTRAINT fk_subscription_event_actor
                FOREIGN KEY (actor_user_id) REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

if (!$foreignKeyExists('product_plan_benefits', 'fk_product_plan_benefit_plan')) {
    $pdo->exec(
        'ALTER TABLE product_plan_benefits ADD CONSTRAINT fk_product_plan_benefit_plan
         FOREIGN KEY (plan_code) REFERENCES product_plans (code) ON DELETE CASCADE'
    );
}
if (!$foreignKeyExists('subscription_events', 'fk_subscription_event_subscription')) {
    $pdo->exec(
        'ALTER TABLE subscription_events ADD CONSTRAINT fk_subscription_event_subscription
         FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE'
    );
}
if (!$foreignKeyExists('subscription_events', 'fk_subscription_event_actor')) {
    $pdo->exec(
        'ALTER TABLE subscription_events ADD CONSTRAINT fk_subscription_event_actor
         FOREIGN KEY (actor_user_id) REFERENCES usuarios (id) ON DELETE SET NULL'
    );
}
if (!$indexExists('product_plan_benefits', 'uniq_product_plan_benefit_position')) {
    $pdo->exec(
        'ALTER TABLE product_plan_benefits
         ADD UNIQUE KEY uniq_product_plan_benefit_position (plan_code, position)'
    );
}
if (!$indexExists('subscription_events', 'uniq_subscription_provider_event')) {
    $pdo->exec(
        'ALTER TABLE subscription_events
         ADD UNIQUE KEY uniq_subscription_provider_event (source, provider_event_id)'
    );
}
if (!$indexExists('subscription_events', 'idx_subscription_event_history')) {
    $pdo->exec(
        'ALTER TABLE subscription_events
         ADD KEY idx_subscription_event_history (subscription_id, created_at, id)'
    );
}
if (!$indexExists('subscription_events', 'idx_subscription_event_actor')) {
    $pdo->exec(
        'ALTER TABLE subscription_events
         ADD KEY idx_subscription_event_actor (actor_user_id, created_at)'
    );
}

$pdo->exec(
    "UPDATE product_plans
     SET display_name = CASE code
         WHEN 'student-free' THEN 'Estudante gratuito'
         WHEN 'student-premium' THEN 'Estudante Premium'
         WHEN 'student-premium-low-income' THEN 'Estudante Premium com desconto de renda'
         WHEN 'teacher-free' THEN 'Professor gratuito'
         WHEN 'teacher-premium' THEN 'Professor Premium'
         WHEN 'institution-public-school' THEN 'Escola pública'
         WHEN 'institution-public-university' THEN 'Universidade pública'
         WHEN 'institution-private-school' THEN 'Escola particular'
         WHEN 'institution-private-university' THEN 'Universidade particular'
         ELSE code
     END
     WHERE display_name = ''"
);

$pdo->exec(
    'INSERT INTO subscription_events
        (subscription_id, event_type, previous_status, new_status, source, details)
     SELECT s.id, "subscription_recorded", NULL, s.status, "migration",
            JSON_OBJECT("plan_code", s.plan_code)
     FROM subscriptions s
     WHERE NOT EXISTS (
         SELECT 1 FROM subscription_events e
         WHERE e.subscription_id = s.id AND e.event_type = "subscription_recorded"
     )'
);

echo "Plan catalog and subscription lifecycle schema applied.\n";