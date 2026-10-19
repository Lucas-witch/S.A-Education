<?php
declare(strict_types=1);

function buscar_salas(PDO $pdo, int $usuarioId, string $termo, string $assunto, string $materia, string $acesso, ?int $salaId = null): array {
    if (!in_array($acesso, ['', 'publica', 'privada'], true)) {
        throw new InvalidArgumentException('Filtro de acesso inválido.');
    }

    $where = [];
    $params = [];
    if ($usuarioId > 0) {
        $where[] = '(s.privacidade <> :privada OR EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = s.id AND su.usuario_id = :usuario_id AND su.status = "ativo"))';
        $params[':privada'] = 'privada';
        $params[':usuario_id'] = $usuarioId;
    } else {
        $where[] = 's.privacidade <> :privada';
        $params[':privada'] = 'privada';
    }

    foreach ([
        'termo' => [$termo, ['s.nome', 's.prof', 's.materia', 's.descricao']],
        'assunto' => [$assunto, ['s.nome', 's.materia', 's.descricao']],
        'materia' => [$materia, ['s.materia']],
    ] as $filterName => [$filterValue, $columns]) {
        if ($filterValue === '') {
            continue;
        }

        $conditions = [];
        foreach ($columns as $index => $column) {
            $parameter = ':' . $filterName . '_' . $index;
            $conditions[] = 'LOWER(COALESCE(' . $column . ', "")) LIKE ' . $parameter;
            $params[$parameter] = '%' . mb_strtolower($filterValue) . '%';
        }
        $where[] = '(' . implode(' OR ', $conditions) . ')';
    }

    if ($acesso !== '') {
        $where[] = 's.privacidade = :acesso';
        $params[':acesso'] = $acesso;
    }

    if ($salaId !== null) {
        $where[] = 's.id = :sala_id';
        $params[':sala_id'] = $salaId;
    }

    $sql = 'SELECT s.id, s.nome, s.prof, '
        . '(SELECT COUNT(*) FROM salas_usuarios su_count WHERE su_count.sala_id = s.id AND su_count.status = "ativo") AS alunos, '
        . 's.materia, s.icone, s.privacidade, s.descricao, s.criado_em '
        . 'FROM salas s WHERE ' . implode(' AND ', $where) . ' ORDER BY s.criado_em DESC, s.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}