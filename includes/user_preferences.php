<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const USER_PREFERENCE_DEFAULTS = [
    'theme' => 'light',
    'font_size' => 'standard',
    'high_contrast' => false,
    'reduce_motion' => false,
    'underline_links' => false,
    'line_spacing' => 'normal',
];

function require_settings_account(): array {
    if (isset($_SESSION['usuario_id'])) {
        require_auth();
        return [
            'user_id' => (int) $_SESSION['usuario_id'],
            'institution_account' => false,
        ];
    }

    if (isset($_SESSION['instituicao_usuario_id'])) {
        require_institution_auth();
        return [
            'user_id' => (int) $_SESSION['instituicao_usuario_id'],
            'institution_account' => true,
        ];
    }

    header('Location: login.php');
    exit;
}

function load_user_preferences(PDO $pdo, int $userId): array {
    $statement = $pdo->prepare(
        'SELECT theme, font_size, high_contrast, reduce_motion, underline_links, line_spacing
         FROM user_preferences
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $preferences = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($preferences)) {
        return USER_PREFERENCE_DEFAULTS;
    }

    return [
        'theme' => (string) $preferences['theme'],
        'font_size' => (string) $preferences['font_size'],
        'high_contrast' => (bool) $preferences['high_contrast'],
        'reduce_motion' => (bool) $preferences['reduce_motion'],
        'underline_links' => (bool) $preferences['underline_links'],
        'line_spacing' => (string) $preferences['line_spacing'],
    ];
}

function normalize_user_preferences(array $input, array $current): ?array {
    $allowedKeys = array_keys(USER_PREFERENCE_DEFAULTS);
    if (array_diff(array_keys($input), $allowedKeys) !== []) {
        return null;
    }

    $preferences = array_merge(USER_PREFERENCE_DEFAULTS, $current);
    foreach ($input as $key => $value) {
        if ($key === 'theme' && in_array($value, ['light', 'dark', 'auto'], true)) {
            $preferences[$key] = $value;
        } elseif ($key === 'font_size' && in_array($value, ['small', 'standard', 'large', 'extra-large'], true)) {
            $preferences[$key] = $value;
        } elseif ($key === 'line_spacing' && in_array($value, ['normal', 'relaxed'], true)) {
            $preferences[$key] = $value;
        } elseif (
            in_array($key, ['high_contrast', 'reduce_motion', 'underline_links'], true)
            && is_bool($value)
        ) {
            $preferences[$key] = $value;
        } else {
            return null;
        }
    }

    return $preferences;
}

function save_user_preferences(PDO $pdo, int $userId, array $preferences): void {
    $statement = $pdo->prepare(
        'INSERT INTO user_preferences
            (user_id, theme, font_size, high_contrast, reduce_motion, underline_links, line_spacing)
         VALUES
            (:user_id, :theme, :font_size, :high_contrast, :reduce_motion, :underline_links, :line_spacing)
         ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            font_size = VALUES(font_size),
            high_contrast = VALUES(high_contrast),
            reduce_motion = VALUES(reduce_motion),
            underline_links = VALUES(underline_links),
            line_spacing = VALUES(line_spacing)'
    );
    $statement->execute([
        'user_id' => $userId,
        'theme' => $preferences['theme'],
        'font_size' => $preferences['font_size'],
        'high_contrast' => $preferences['high_contrast'] ? 1 : 0,
        'reduce_motion' => $preferences['reduce_motion'] ? 1 : 0,
        'underline_links' => $preferences['underline_links'] ? 1 : 0,
        'line_spacing' => $preferences['line_spacing'],
    ]);
}
