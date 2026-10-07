<?php
// Simple seeder for development. Run from CLI: php seed/seed.php
// This project intentionally avoids demo users in the database.
require_once __DIR__ . '/../conexao.php';

$plans = [
    ['nome' => 'free', 'preco' => 0.00, 'descricao' => 'Plano gratuito', 'pode_postar_aulas_ilimitadas' => false, 'pode_criar_salas' => false, 'pode_criar_comunidades_privadas' => false, 'pode_postar_artigos' => true, 'limite_video_aula_free' => 1],
    ['nome' => 'premium', 'preco' => 29.90, 'descricao' => 'Plano premium', 'pode_postar_aulas_ilimitadas' => true, 'pode_criar_salas' => true, 'pode_criar_comunidades_privadas' => true, 'pode_postar_artigos' => true, 'limite_video_aula_free' => null],
];

$users = [];

$salas = [
    ['nome' => 'Matemática Avançada', 'prof' => 'Prof. Lucas', 'alunos' => 24, 'materia' => 'Matemática', 'icone' => 'assets/images/room_icons/math.svg', 'codigo' => 'MATH01'],
    ['nome' => 'História do Brasil', 'prof' => 'Profa. Marina', 'alunos' => 18, 'materia' => 'História', 'icone' => 'assets/images/room_icons/history.svg', 'codigo' => 'HIST01'],
    ['nome' => 'Física Moderna', 'prof' => 'Prof. Rafael', 'alunos' => 32, 'materia' => 'Física', 'icone' => 'assets/images/room_icons/physics.svg', 'codigo' => 'PHYS01'],
];

try {
    $pdo->beginTransaction();

    $planStmt = $pdo->prepare(
        'INSERT INTO planos (nome, preco, descricao, pode_postar_aulas_ilimitadas, pode_criar_salas, pode_criar_comunidades_privadas, pode_postar_artigos, limite_video_aula_free) VALUES (:nome, :preco, :descricao, :pode_postar_aulas_ilimitadas, :pode_criar_salas, :pode_criar_comunidades_privadas, :pode_postar_artigos, :limite_video_aula_free) ON DUPLICATE KEY UPDATE nome = VALUES(nome)'
    );

    foreach ($plans as $plan) {
        $planStmt->execute([
            'nome' => $plan['nome'],
            'preco' => $plan['preco'],
            'descricao' => $plan['descricao'],
            'pode_postar_aulas_ilimitadas' => $plan['pode_postar_aulas_ilimitadas'] ? 1 : 0,
            'pode_criar_salas' => $plan['pode_criar_salas'] ? 1 : 0,
            'pode_criar_comunidades_privadas' => $plan['pode_criar_comunidades_privadas'] ? 1 : 0,
            'pode_postar_artigos' => $plan['pode_postar_artigos'] ? 1 : 0,
            'limite_video_aula_free' => $plan['limite_video_aula_free'],
        ]);
    }

    if ($users !== []) {
        $usuarioStmt = $pdo->prepare(
            'INSERT INTO usuarios (nome_completo, email, username, senha, perfil, plano_id, premium_ativo)
             VALUES (:nome, :email, :username, :senha, :perfil, (SELECT id FROM planos WHERE nome = :plano), :premium_ativo)
             ON DUPLICATE KEY UPDATE nome_completo = VALUES(nome_completo), senha = VALUES(senha), perfil = VALUES(perfil), plano_id = VALUES(plano_id), premium_ativo = VALUES(premium_ativo)'
        );

        foreach ($users as $u) {
            $usuarioStmt->execute([
                'nome' => $u['nome'],
                'email' => $u['email'],
                'username' => $u['username'],
                'senha' => password_hash($u['senha'], PASSWORD_DEFAULT),
                'perfil' => $u['perfil'],
                'plano' => $u['plano'],
                'premium_ativo' => $u['premium_ativo'] ? 1 : 0,
            ]);
        }
    }

    $salaStmt = $pdo->prepare('INSERT INTO salas (nome, prof, alunos, materia, icone, codigo) VALUES (:nome, :prof, :alunos, :materia, :icone, :codigo) ON DUPLICATE KEY UPDATE nome = VALUES(nome)');
    foreach ($salas as $s) {
        $salaStmt->execute([
            'nome' => $s['nome'],
            'prof' => $s['prof'],
            'alunos' => $s['alunos'],
            'materia' => $s['materia'],
            'icone' => $s['icone'],
            'codigo' => $s['codigo'],
        ]);
    }

    $pdo->commit();
    echo "Seed completed successfully.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Seed failed: " . $e->getMessage() . "\n";
}

?>