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
        throw new InvalidArgumentException('Usuário inválido para registrar atividade.');
    }

    $tipo = strtolower(trim($tipo));
    if (!in_array($tipo, ['exercicio', 'quiz'], true)) {
        throw new InvalidArgumentException('Tipo de atividade inválido para registro de tentativas.');
    }

    if ($atividadeId !== null && $atividadeId <= 0) {
        throw new InvalidArgumentException('Atividade inválida para registro de tentativas.');
    }

    if ($respostasCorretas < 0 || $respostasIncorretas < 0 || $totalRespostas < 0) {
        throw new InvalidArgumentException('As quantidades de respostas não podem ser negativas.');
    }

    $totalCalculado = $respostasCorretas + $respostasIncorretas;
    if ($totalCalculado <= 0 || ($totalRespostas > 0 && $totalRespostas !== $totalCalculado)) {
        throw new InvalidArgumentException('A tentativa precisa conter respostas consistentes.');
    }

    $categoria = trim($categoria);
    if (mb_strlen($categoria) > 80) {
        throw new InvalidArgumentException('Categoria excede o limite permitido.');
    }

    $percentual = round(($respostasCorretas / $totalCalculado) * 100, 2);

    $createdAtExpression = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    $stmt = $pdo->prepare('INSERT INTO tentativas_atividade (usuario_id, tipo, atividade_id, categoria, respostas_corretas, respostas_incorretas, total_respostas, percentual_acerto, concluido, criado_em) VALUES (:usuario_id, :tipo, :atividade_id, :categoria, :respostas_corretas, :respostas_incorretas, :total_respostas, :percentual_acerto, 1, ' . $createdAtExpression . ')');
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':tipo' => $tipo,
        ':atividade_id' => $atividadeId,
        ':categoria' => $categoria !== '' ? $categoria : 'geral',
        ':respostas_corretas' => $respostasCorretas,
        ':respostas_incorretas' => $respostasIncorretas,
        ':total_respostas' => $totalCalculado,
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
        'quizzes_com_tentativa' => 0,
        'exercicios_com_tentativa' => 0,
        'historico_disponivel' => false,
        'emprestimos_devolvidos' => 0,
        'emprestimos_disponiveis' => false,
        'salas_recentes' => [],
    ];

    if ($usuarioId <= 0) {
        return $dados;
    }

    try {
        $validAttempt = 'usuario_id = :usuario_id AND concluido = 1 AND total_respostas > 0 AND respostas_corretas >= 0 AND respostas_incorretas >= 0 AND total_respostas = respostas_corretas + respostas_incorretas';
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(respostas_corretas), 0) AS total_acertos, COALESCE(SUM(respostas_incorretas), 0) AS total_erros, COALESCE(SUM(total_respostas), 0) AS total_respostas, COUNT(*) AS total_tentativas, COALESCE(SUM(CASE WHEN tipo = "quiz" THEN 1 ELSE 0 END), 0) AS quizzes_com_tentativa, COALESCE(SUM(CASE WHEN tipo = "exercicio" THEN 1 ELSE 0 END), 0) AS exercicios_com_tentativa FROM tentativas_atividade WHERE ' . $validAttempt);
        $stmt->execute([':usuario_id' => $usuarioId]);
        $resumo = $stmt->fetch();

        $dados['total_acertos'] = (int) ($resumo['total_acertos'] ?? 0);
        $dados['total_erros'] = (int) ($resumo['total_erros'] ?? 0);
        $dados['total_tentativas'] = (int) ($resumo['total_tentativas'] ?? 0);
        $dados['quizzes_com_tentativa'] = (int) ($resumo['quizzes_com_tentativa'] ?? 0);
        $dados['exercicios_com_tentativa'] = (int) ($resumo['exercicios_com_tentativa'] ?? 0);
        $dados['total_respostas'] = (int) ($resumo['total_respostas'] ?? ($dados['total_acertos'] + $dados['total_erros']));
        $dados['percentual_acerto'] = $dados['total_respostas'] > 0 ? round(($dados['total_acertos'] / $dados['total_respostas']) * 100, 2) : 0.0;
        $dados['historico_disponivel'] = true;

        $periodExpression = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "strftime('%Y-%m', criado_em)"
            : "DATE_FORMAT(criado_em, '%Y-%m')";
        $stmt = $pdo->prepare('SELECT ' . $periodExpression . ' AS periodo, SUM(respostas_corretas) AS acertos, SUM(respostas_incorretas) AS erros FROM tentativas_atividade WHERE ' . $validAttempt . ' GROUP BY ' . $periodExpression . ' ORDER BY periodo DESC LIMIT 7');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $linhas = $stmt->fetchAll();
        $linhas = array_reverse($linhas);

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
        error_log('[S.A Education][dashboard_attempts] ' . $e->getMessage());
        $dados['historico_disponivel'] = false;
        return $dados;
    }

    try {
        $stmt = $pdo->prepare('SELECT s.id, s.nome, s.prof, s.alunos, s.materia, s.icone, s.privacidade, s.descricao FROM salas_usuarios su INNER JOIN salas s ON s.id = su.sala_id WHERE su.usuario_id = :usuario_id AND su.status = "ativo" ORDER BY su.entrou_em DESC LIMIT 3');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $dados['salas_recentes'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dados['salas_recentes'] = [];
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM emprestimos WHERE usuario_id = :usuario_id AND status = "devolvido"');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $dados['emprestimos_devolvidos'] = (int) $stmt->fetchColumn();
        $dados['emprestimos_disponiveis'] = true;
    } catch (PDOException $e) {
        error_log('[S.A Education][dashboard_loans] ' . $e->getMessage());
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

function dashboard_pode_exibir_grafico(array $dados): bool {
    return !empty($dados['historico_disponivel'])
        && (int) ($dados['total_tentativas'] ?? 0) > 0
        && count($dados['labels'] ?? []) >= 2;
}


















