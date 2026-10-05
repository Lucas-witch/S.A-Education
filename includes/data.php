<?php
// includes/data.php
// Centraliza os dados vindos do banco e mantém fallback apenas em caso de indisponibilidade.

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../conexao.php';
}

$salas = [];
$recentes = [];
$ranking = [];
$bibliotecaRecente = [];
$publicacoesRecentes = [];
$estatisticas = [
    'salas' => 0,
    'biblioteca' => 0,
    'usuarios' => 0,
    'instituicoes' => 0,
    'publicacoes' => 0,
];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT id, nome, prof, alunos, materia, icone, privacidade, descricao FROM salas ORDER BY criado_em DESC LIMIT 10');
        $salas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $salas = [];
    }

    try {
        $stmt = $pdo->query('SELECT id, nome, prof, alunos, materia, icone FROM salas ORDER BY criado_em DESC LIMIT 3');
        $recentes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recentes = [];
    }

    try {
        $stmt = $pdo->query('SELECT u.nome_completo AS nome, COALESCE(r.pontos, 0) AS pontos, ROW_NUMBER() OVER (ORDER BY COALESCE(r.pontos, 0) DESC) AS pos FROM usuarios u LEFT JOIN ranking r ON u.id = r.usuario_id ORDER BY pontos DESC LIMIT 10');
        $ranking = $stmt->fetchAll();
    } catch (PDOException $e) {
        $ranking = [];
    }

    try {
        $stmt = $pdo->query('SELECT id, titulo, autor, tipo, disponibilidade, status FROM biblioteca ORDER BY criado_em DESC LIMIT 3');
        $bibliotecaRecente = $stmt->fetchAll();
    } catch (PDOException $e) {
        $bibliotecaRecente = [];
    }

    try {
        $stmt = $pdo->query('SELECT id, titulo, tipo, status, publicado_em FROM publicacoes ORDER BY publicado_em DESC LIMIT 3');
        $publicacoesRecentes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $publicacoesRecentes = [];
    }

    try {
        $estatisticas['salas'] = (int) $pdo->query('SELECT COUNT(*) FROM salas')->fetchColumn();
        $estatisticas['biblioteca'] = (int) $pdo->query('SELECT COUNT(*) FROM biblioteca')->fetchColumn();
        $estatisticas['usuarios'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $estatisticas['instituicoes'] = (int) $pdo->query('SELECT COUNT(*) FROM instituicoes')->fetchColumn();
        $estatisticas['publicacoes'] = (int) $pdo->query('SELECT COUNT(*) FROM publicacoes')->fetchColumn();
    } catch (PDOException $e) {
        $estatisticas = [
            'salas' => 0,
            'biblioteca' => 0,
            'usuarios' => 0,
            'instituicoes' => 0,
            'publicacoes' => 0,
        ];
    }
}

if (empty($salas) && empty($recentes) && empty($ranking) && empty($bibliotecaRecente) && empty($publicacoesRecentes)) {
    $salas = [
        ['id' => 1, 'nome' => 'Matemática Básica', 'prof' => 'Prof. Lucas', 'alunos' => 25, 'materia' => 'Matemática', 'icone' => 'assets/images/room_icons/math.svg', 'privacidade' => 'publica', 'descricao' => 'Sala de revisão e práticas.'],
        ['id' => 2, 'nome' => 'Química Orgânica', 'prof' => 'Profa. Juliana', 'alunos' => 20, 'materia' => 'Química', 'icone' => 'assets/images/room_icons/chemistry.svg', 'privacidade' => 'publica', 'descricao' => 'Reações e fundamentos.'],
        ['id' => 3, 'nome' => 'Literatura Brasileira', 'prof' => 'Prof. Felipe', 'alunos' => 15, 'materia' => 'Linguagens', 'icone' => 'assets/images/room_icons/literature.svg', 'privacidade' => 'publica', 'descricao' => 'Leitura e análise crítica.'],
    ];
    $recentes = [
        ['id' => 1, 'nome' => 'Matemática Avançada', 'prof' => 'Prof. Lucas', 'alunos' => 24, 'icone' => 'assets/images/room_icons/math.svg'],
        ['id' => 2, 'nome' => 'História do Brasil', 'prof' => 'Profa. Marina', 'alunos' => 18, 'icone' => 'assets/images/room_icons/history.svg'],
        ['id' => 3, 'nome' => 'Física Moderna', 'prof' => 'Prof. Rafael', 'alunos' => 32, 'icone' => 'assets/images/room_icons/physics.svg'],
    ];
    $ranking = [
        ['nome' => 'Ana Clara', 'pontos' => 2450, 'pos' => 1],
        ['nome' => 'Lucas', 'pontos' => 2100, 'pos' => 2],
        ['nome' => 'Marina', 'pontos' => 1890, 'pos' => 3],
    ];
    $bibliotecaRecente = [
        ['titulo' => 'Matemática para Concursos', 'autor' => 'Lucas N.', 'tipo' => 'livro', 'disponibilidade' => 'gratis'],
        ['titulo' => 'Redação em Foco', 'autor' => 'Marina L.', 'tipo' => 'pdf', 'disponibilidade' => 'gratis'],
        ['titulo' => 'História do Brasil em Fases', 'autor' => 'Pedro A.', 'tipo' => 'apostila', 'disponibilidade' => 'leitura_online'],
    ];
    $publicacoesRecentes = [
        ['titulo' => 'Checklist de revisão', 'tipo' => 'material', 'status' => 'publicado'],
        ['titulo' => 'Aula de funções', 'tipo' => 'video_aula', 'status' => 'publicado'],
        ['titulo' => 'Dicas para provas', 'tipo' => 'artigo', 'status' => 'publicado'],
    ];
    $estatisticas = [
        'salas' => count($salas),
        'biblioteca' => count($bibliotecaRecente),
        'usuarios' => 0,
        'instituicoes' => 0,
        'publicacoes' => count($publicacoesRecentes),
    ];
}

