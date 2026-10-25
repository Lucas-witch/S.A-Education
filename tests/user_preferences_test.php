<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user_preferences.php';

function assert_user_preferences(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$defaults = USER_PREFERENCE_DEFAULTS;
assert_user_preferences(
    normalize_user_preferences([], $defaults) === $defaults,
    'Preferências não enviadas devem preservar os valores atuais.'
);

$updated = normalize_user_preferences([
    'theme' => 'auto',
    'font_size' => 'extra-large',
    'high_contrast' => true,
    'reduce_motion' => true,
    'underline_links' => true,
    'line_spacing' => 'relaxed',
], $defaults);
assert_user_preferences(
    $updated === [
        'theme' => 'auto',
        'font_size' => 'extra-large',
        'high_contrast' => true,
        'reduce_motion' => true,
        'underline_links' => true,
        'line_spacing' => 'relaxed',
    ],
    'Todos os valores permitidos devem ser aceitos e normalizados.'
);

$partial = normalize_user_preferences(['theme' => 'dark'], $updated);
assert_user_preferences(
    is_array($partial) && $partial['theme'] === 'dark' && $partial['font_size'] === 'extra-large',
    'Atualizações parciais devem preservar as demais preferências atuais.'
);

assert_user_preferences(
    normalize_user_preferences(['theme' => 'brand-purple'], $defaults) === null,
    'Valores de tema fora da lista permitida devem ser rejeitados.'
);
assert_user_preferences(
    normalize_user_preferences(['font_size' => 'giant'], $defaults) === null,
    'Escalas de fonte fora da lista permitida devem ser rejeitadas.'
);
assert_user_preferences(
    normalize_user_preferences(['high_contrast' => 'true'], $defaults) === null,
    'Flags de acessibilidade devem exigir booleanos reais.'
);
assert_user_preferences(
    normalize_user_preferences(['is_admin' => true], $defaults) === null,
    'Campos de permissão não devem ser aceitos como preferências.'
);

echo "OK: valores permitidos, atualizações parciais e rejeição de chaves ou valores inválidos.\n";
