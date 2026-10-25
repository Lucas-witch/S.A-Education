<?php
declare(strict_types=1);

require_once __DIR__ . '/room_access.php';

function buscar_salas(PDO $pdo, int $usuarioId, string $termo, string $assunto, string $materia, string $acesso, ?int $salaId = null): array {
    if (!in_array($acesso, ['', 'publica', 'privada', 'instituicao'], true)) {
        throw new InvalidArgumentException('Filtro de acesso inválido.');
    }

    $where = [];
    $params = [];
    if ($usuarioId > 0 && $usuarioId === configured_admin_user_id()) {
        $where[] = '1 = 1';
    } elseif ($usuarioId > 0) {
        $institutionAccess = institution_content_access_condition(
            'i.id',
            ':room_institution_owner_id',
            ':room_institution_member_id'
        );
        $where[] = '(s.privacidade = "publica"
            OR (s.privacidade = "privada" AND (
                s.criador_id = :room_creator_id
                OR EXISTS (
                    SELECT 1 FROM salas_usuarios su
                    WHERE su.sala_id = s.id AND su.usuario_id = :room_member_id AND su.status = "ativo"
                )
            ))
            OR (s.privacidade = "instituicao" AND EXISTS (
                SELECT 1 FROM instituicoes i
                WHERE i.id = s.institution_id AND ' . $institutionAccess . '
            )))';
        $params[':room_creator_id'] = $usuarioId;
        $params[':room_member_id'] = $usuarioId;
        $params[':room_institution_owner_id'] = $usuarioId;
        $params[':room_institution_member_id'] = $usuarioId;
    } else {
        $where[] = 's.privacidade = "publica"';
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

    $sql = 'SELECT s.id, s.nome, s.criador_id, s.institution_id, s.categoria, s.area_conhecimento, s.prof, '
        . '(SELECT COUNT(*) FROM salas_usuarios su_count WHERE su_count.sala_id = s.id AND su_count.status = "ativo") AS alunos, '
        . 's.materia, s.icone, s.privacidade, s.descricao, s.criado_em '
        . 'FROM salas s WHERE ' . implode(' AND ', $where) . ' ORDER BY s.criado_em DESC, s.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}