<?php
// Simple seeder for development. Run from CLI: php seed/seed.php
require_once __DIR__ . '/../conexao.php';

$users = [
    ['nome'=>'Ana Clara','email'=>'ana.clara@example.com','username'=>'anaclara','senha'=>'Senha@123','perfil'=>'estudante'],
    ['nome'=>'Lucas','email'=>'lucas@example.com','username'=>'lucas','senha'=>'Senha@123','perfil'=>'professor'],
    ['nome'=>'Marina','email'=>'marina@example.com','username'=>'marina','senha'=>'Senha@123','perfil'=>'estudante'],
];

$salas = [
    ['nome'=>'Matemática Avançada','prof'=>'Prof. Lucas','alunos'=>24,'materia'=>'Matemática','icone'=>'assets/images/room_icons/math.svg','codigo'=>'MATH01'],
    ['nome'=>'História do Brasil','prof'=>'Profa. Marina','alunos'=>18,'materia'=>'História','icone'=>'assets/images/room_icons/history.svg','codigo'=>'HIST01'],
    ['nome'=>'Física Moderna','prof'=>'Prof. Rafael','alunos'=>32,'materia'=>'Física','icone'=>'assets/images/room_icons/physics.svg','codigo'=>'PHYS01'],
];

try {
    $pdo->beginTransaction();

    $uStmt = $pdo->prepare('INSERT INTO usuarios (nome_completo,email,username,senha,perfil) VALUES (:nome,:email,:username,:senha,:perfil)');
    foreach ($users as $u) {
        $uStmt->execute([
            'nome' => $u['nome'],
            'email' => $u['email'],
            'username' => $u['username'],
            'senha' => password_hash($u['senha'], PASSWORD_DEFAULT),
            'perfil' => $u['perfil']
        ]);
    }

    $sStmt = $pdo->prepare('INSERT INTO salas (nome,prof,alunos,materia,icone,codigo) VALUES (:nome,:prof,:alunos,:materia,:icone,:codigo)');
    foreach ($salas as $s) {
        $sStmt->execute([
            'nome'=>$s['nome'],'prof'=>$s['prof'],'alunos'=>$s['alunos'],'materia'=>$s['materia'],'icone'=>$s['icone'],'codigo'=>$s['codigo']
        ]);
    }

    $pdo->commit();
    echo "Seed completed successfully.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Seed failed: " . $e->getMessage() . "\n";
}

?>