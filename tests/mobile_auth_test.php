<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/mobile_auth.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY,
        nome_completo TEXT,
        email TEXT,
        username TEXT,
        perfil TEXT NOT NULL,
        perfil_imagem TEXT,
        auth_version INTEGER NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE mobile_auth_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        account_type TEXT NOT NULL,
        auth_version INTEGER NOT NULL,
        device_name TEXT,
        created_at TEXT NOT NULL,
        last_used_at TEXT NOT NULL,
        revoked_at TEXT
    )'
);
$pdo->exec(
    'CREATE TABLE mobile_auth_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        token_type TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        consumed_at TEXT,
        revoked_at TEXT,
        created_at TEXT NOT NULL
    )'
);
$pdo->exec(
    "INSERT INTO usuarios
       (id, nome_completo, email, username, perfil, perfil_imagem, auth_version)
     VALUES
       (1, 'Pessoa de teste', 'test@example.invalid', 'teste', 'estudante', '', 0),
       (2, 'Instituição de teste', 'school@example.invalid', 'escola', 'instituicao', '', 0)"
);

function assert_mobile_auth(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pair = mobile_issue_token_pair($pdo, 1, 'user', 0, 'emulator');
assert_mobile_auth(strlen($pair['access_token']) === 64, 'O access token deve ser aleatório e opaco.');
assert_mobile_auth(strlen($pair['refresh_token']) === 64, 'O refresh token deve ser aleatório e opaco.');
assert_mobile_auth(
    (int) $pdo->query('SELECT COUNT(*) FROM mobile_auth_tokens')->fetchColumn() === 2,
    'O banco deve guardar hashes, nunca os tokens originais.'
);
assert_mobile_auth(
    (int) $pdo->query('SELECT COUNT(*) FROM mobile_auth_tokens WHERE token_hash IN ('
        . $pdo->quote(mobile_token_hash($pair['access_token'])) . ', '
        . $pdo->quote(mobile_token_hash($pair['refresh_token'])) . ')')->fetchColumn() === 2,
    'Os hashes dos dois tokens devem ser persistidos.'
);
assert_mobile_auth(
    (int) mobile_find_access_context($pdo, $pair['access_token'])['user_id'] === 1,
    'Um access token válido deve identificar somente seu usuário.'
);

$rotated = mobile_rotate_refresh_token($pdo, $pair['refresh_token']);
assert_mobile_auth(is_array($rotated) && isset($rotated['refresh_token']), 'O refresh válido deve emitir um novo par.');
assert_mobile_auth(mobile_rotate_refresh_token($pdo, $pair['refresh_token'])['reuse_detected'] === true, 'O reuso do refresh antigo deve ser detectado.');
assert_mobile_auth(mobile_find_access_context($pdo, $rotated['access_token']) === null, 'O reuso deve revogar toda a sessão, inclusive tokens recentes.');

$secondPair = mobile_issue_token_pair($pdo, 1, 'user', 0);
$pdo->exec('UPDATE usuarios SET auth_version = 1 WHERE id = 1');
assert_mobile_auth(mobile_find_access_context($pdo, $secondPair['access_token']) === null, 'A troca de auth_version deve invalidar access tokens.');
$secondRefresh = mobile_rotate_refresh_token($pdo, $secondPair['refresh_token']);
assert_mobile_auth($secondRefresh === null, 'A troca de auth_version deve invalidar refresh tokens.');

$thirdPair = mobile_issue_token_pair($pdo, 1, 'user', 1);
mobile_revoke_refresh_token($pdo, $thirdPair['refresh_token']);
assert_mobile_auth(mobile_find_access_context($pdo, $thirdPair['access_token']) === null, 'Logout deve revogar a sessão e seus access tokens.');

$institution = mobile_issue_token_pair($pdo, 2, 'institution', 0);
assert_mobile_auth(
    (int) mobile_find_access_context($pdo, $institution['access_token'])['user_id'] === 2,
    'Uma conta institucional deve usar o tipo de conta institucional.'
);

$expired = mobile_issue_token_pair($pdo, 1, 'user', 1);
$expiryUpdate = $pdo->prepare(
    'UPDATE mobile_auth_tokens SET expires_at = :expires_at WHERE token_hash = :token_hash'
);
$expiryUpdate->execute([
    'expires_at' => gmdate('Y-m-d H:i:s', time() - 60),
    'token_hash' => mobile_token_hash($expired['access_token']),
]);
assert_mobile_auth(mobile_find_access_context($pdo, $expired['access_token']) === null, 'Access tokens expirados devem ser rejeitados.');
$refreshExpiry = $pdo->prepare(
    'UPDATE mobile_auth_tokens SET expires_at = :expires_at WHERE token_hash = :token_hash'
);
$refreshExpiry->execute([
    'expires_at' => gmdate('Y-m-d H:i:s', time() - 60),
    'token_hash' => mobile_token_hash($expired['refresh_token']),
]);
assert_mobile_auth(mobile_rotate_refresh_token($pdo, $expired['refresh_token']) === null, 'Refresh tokens expirados devem ser rejeitados.');

echo "OK: tokens opacos, rotação, detecção de reuso, revogação e invalidação por versão.\n";
