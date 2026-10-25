<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, perfil TEXT NOT NULL, auth_version INTEGER NOT NULL DEFAULT 0)');
$pdo->exec("INSERT INTO usuarios (id, perfil, auth_version) VALUES (10, 'estudante', 0), (20, 'instituicao', 4)");

set_authenticated_session([
    'id' => 10,
    'perfil' => 'estudante',
    'auth_version' => 0,
]);
if (!authenticated_session_is_current($pdo)) {
    throw new RuntimeException('Uma sessão de estudante com a versão atual deve permanecer válida.');
}

$pdo->exec('UPDATE usuarios SET auth_version = 1 WHERE id = 10');
if (authenticated_session_is_current($pdo)) {
    throw new RuntimeException('Uma sessão antiga deve ser invalidada após a troca de senha.');
}

set_institution_authenticated_session([
    'id' => 20,
    'perfil' => 'instituicao',
    'auth_version' => 4,
]);
if (!institution_session_is_current($pdo) || authenticated_session_is_current($pdo)) {
    throw new RuntimeException('Sessões institucionais devem ser separadas das sessões de estudante/professor.');
}

$pdo->exec("UPDATE usuarios SET perfil = 'professor' WHERE id = 20");
if (institution_session_is_current($pdo)) {
    throw new RuntimeException('A sessão institucional deve falhar após mudança de perfil.');
}

echo "OK: versão de autenticação invalida sessões antigas e mantém isolamento de perfis.\n";
