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

function registrar_tentativa_atividade(PDO $pdo, int $usuarioId, string $tipo, ?int $atividadeId, string $categoria, int $respostasCorretas, int $respostasIncorretas, int $totalRespostas = 0): void {
    if ($usuarioId <= 0) {
        return;
    }

    $tipo = strtolower(trim($tipo));
    if (!in_array($tipo, ['exercicio', 'quiz'], true)) {
        throw new InvalidArgumentException('Tipo de atividade inválido para registro de tentativas.');
    }

    $totalRespostas = max(0, $totalRespostas > 0 ? $totalRespostas : $respostasCorretas + $respostasIncorretas);
    $acertos = max(0, $respostasCorretas);
    $erros = max(0, $respostasIncorretas);
    $percentual = $totalRespostas > 0 ? round(($acertos / $totalRespostas) * 100, 2) : 0.0;

    $stmt = $pdo->prepare('INSERT INTO tentativas_atividade (usuario_id, tipo, atividade_id, categoria, respostas_corretas, respostas_incorretas, total_respostas, percentual_acerto, concluido, criado_em) VALUES (:usuario_id, :tipo, :atividade_id, :categoria, :respostas_corretas, :respostas_incorretas, :total_respostas, :percentual_acerto, 1, NOW())');
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':tipo' => $tipo,
        ':atividade_id' => $atividadeId,
        ':categoria' => $categoria !== '' ? $categoria : 'geral',
        ':respostas_corretas' => $acertos,
        ':respostas_incorretas' => $erros,
        ':total_respostas' => $totalRespostas,
        ':percentual_acerto' => $percentual,
    ]);
}

function obter_dados_dashboard_usuario(PDO $pdo, int $usuarioId): array {
    $dados = [
        'acertos' => [],
        'erros' => [],
        'labels' => [],
        'total_acertos' => 0,
        'total_erros' => 0,
        'total_tentativas' => 0,
        'total_respostas' => 0,
        'percentual_acerto' => 0.0,
        'salas_recentes' => [],
    ];

    if ($usuarioId <= 0) {
        return $dados;
    }

    try {
        $stmt = $pdo->prepare('SELECT SUM(respostas_corretas) AS total_acertos, SUM(respostas_incorretas) AS total_erros, SUM(total_respostas) AS total_respostas, COUNT(*) AS total_tentativas FROM tentativas_atividade WHERE usuario_id = :usuario_id AND concluido = 1');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $resumo = $stmt->fetch();

        $dados['total_acertos'] = (int) ($resumo['total_acertos'] ?? 0);
        $dados['total_erros'] = (int) ($resumo['total_erros'] ?? 0);
        $dados['total_tentativas'] = (int) ($resumo['total_tentativas'] ?? 0);
        $dados['total_respostas'] = (int) ($resumo['total_respostas'] ?? ($dados['total_acertos'] + $dados['total_erros']));
        $dados['percentual_acerto'] = $dados['total_respostas'] > 0 ? round(($dados['total_acertos'] / $dados['total_respostas']) * 100, 2) : 0.0;

        $stmt = $pdo->prepare('SELECT DATE_FORMAT(criado_em, "%Y-%m") AS periodo, SUM(respostas_corretas) AS acertos, SUM(respostas_incorretas) AS erros FROM tentativas_atividade WHERE usuario_id = :usuario_id AND concluido = 1 GROUP BY DATE_FORMAT(criado_em, "%Y-%m") ORDER BY periodo ASC LIMIT 7');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $linhas = $stmt->fetchAll();

        foreach ($linhas as $linha) {
            $periodo = (string) ($linha['periodo'] ?? '');
            if ($periodo === '') {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat('Y-m', $periodo);
            $dados['labels'][] = $date ? $date->format('M') : $periodo;
            $dados['acertos'][] = (int) ($linha['acertos'] ?? 0);
            $dados['erros'][] = (int) ($linha['erros'] ?? 0);
        }
    } catch (PDOException $e) {
        return $dados;
    }

    try {
        $stmt = $pdo->prepare('SELECT s.id, s.nome, s.prof, s.alunos, s.materia, s.icone, s.privacidade, s.descricao FROM salas_usuarios su INNER JOIN salas s ON s.id = su.sala_id WHERE su.usuario_id = :usuario_id AND su.status = "ativo" ORDER BY su.entrou_em DESC LIMIT 3');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $dados['salas_recentes'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dados['salas_recentes'] = [];
    }

    return $dados;
}

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
        ['id' => 1, 'nome' => 'Matemática Básica', 'prof' => 'Prof. Lucas', 'alunos' => 25, 'materia' => 'Matemática', 'icone' => 'assets/images/icons/matematica-icon.png', 'privacidade' => 'publica', 'descricao' => 'Sala de revisão e práticas.'],
        ['id' => 2, 'nome' => 'Química Orgânica', 'prof' => 'Profa. Juliana', 'alunos' => 20, 'materia' => 'Química', 'icone' => 'assets/images/icons/química.png', 'privacidade' => 'publica', 'descricao' => 'Reações e fundamentos.'],
        ['id' => 3, 'nome' => 'Literatura Brasileira', 'prof' => 'Prof. Felipe', 'alunos' => 15, 'materia' => 'Linguagens', 'icone' => 'assets/images/icons/portugues.png', 'privacidade' => 'publica', 'descricao' => 'Leitura e análise crítica.'],
    ];
    $recentes = [
        ['id' => 1, 'nome' => 'Matemática Avançada', 'prof' => 'Prof. Lucas', 'alunos' => 24, 'icone' => 'assets/images/icons/matematica-icon.png'],
        ['id' => 2, 'nome' => 'História do Brasil', 'prof' => 'Profa. Marina', 'alunos' => 18, 'icone' => 'assets/images/icons/historia.png'],
        ['id' => 3, 'nome' => 'Física Moderna', 'prof' => 'Prof. Rafael', 'alunos' => 32, 'icone' => 'assets/images/icons/user-icon.png'],
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

