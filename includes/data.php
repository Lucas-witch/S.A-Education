<?php
// includes/data.php
// Fornece dados para as páginas. Se houver uma conexão PDO ($pdo), busca no DB,
// caso contrário utiliza arrays estáticos como fallback.

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT nome,prof,alunos,materia,icone FROM salas ORDER BY criado_em DESC LIMIT 10');
        $salas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $salas = [];
    }

    try {
        $stmt = $pdo->query('SELECT nome,prof,alunos,icone FROM salas ORDER BY criado_em DESC LIMIT 3');
        $recentes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recentes = [];
    }

    try {
        $stmt = $pdo->query('SELECT u.nome_completo AS nome, COALESCE(r.pontos,0) AS pontos, ROW_NUMBER() OVER (ORDER BY COALESCE(r.pontos,0) DESC) AS pos FROM usuarios u LEFT JOIN ranking r ON u.id = r.usuario_id ORDER BY pontos DESC LIMIT 10');
        $ranking = $stmt->fetchAll();
    } catch (PDOException $e) {
        $ranking = [];
    }

} else {
    $salas = [
        ['nome'=>'Matemática Básica','prof'=>'Prof. Lucas','alunos'=>25,'materia'=>'Matemática','icone'=>'assets/images/room_icons/math.svg'],
        ['nome'=>'Química Orgânica','prof'=>'Profa. Juliana','alunos'=>20,'materia'=>'Química','icone'=>'assets/images/room_icons/chemistry.svg'],
        ['nome'=>'Literatura Brasileira','prof'=>'Prof. Felipe','alunos'=>15,'materia'=>'Linguagens','icone'=>'assets/images/room_icons/literature.svg'],
        ['nome'=>'Biologia Celular','prof'=>'Profa. Camila','alunos'=>30,'materia'=>'Biologia','icone'=>'assets/images/room_icons/biology.svg'],
    ];
    $recentes = [
        ['nome'=>'Matemática Avançada','prof'=>'Prof. Lucas','alunos'=>24,'icone'=>'assets/images/room_icons/math.svg'],
        ['nome'=>'História do Brasil','prof'=>'Profa. Marina','alunos'=>18,'icone'=>'assets/images/room_icons/history.svg'],
        ['nome'=>'Física Moderna','prof'=>'Prof. Rafael','alunos'=>32,'icone'=>'assets/images/room_icons/physics.svg'],
    ];
    $ranking = [
        ['nome'=>'Ana Clara','pontos'=>2450,'pos'=>1,'avatar'=>'assets/images/avatars/default.png'],
        ['nome'=>'Lucas','pontos'=>2100,'pos'=>2,'avatar'=>'assets/images/avatars/default.png'],
        ['nome'=>'Marina','pontos'=>1890,'pos'=>3,'avatar'=>'assets/images/avatars/default.png'],
        ['nome'=>'Rafael','pontos'=>1750,'pos'=>4,'avatar'=>'assets/images/avatars/default.png'],
        ['nome'=>'João','pontos'=>1600,'pos'=>5,'avatar'=>'assets/images/avatars/default.png'],
    ];
}

?>
