<?php
declare(strict_types=1);

require_once __DIR__ . '/room_access.php';

function buscar_salas(
    PDO $pdo,
    int $usuarioId,
    string $termo,
    string $assunto,
    string $materia,
    string $acesso,
    ?int $salaId = null,
    ?int $limit = null,
    int $offset = 0,
    ?int &$total = null
): array {
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

    $whereSql = implode(' AND ', $where);
    if ($limit !== null) {
        if ($limit < 1 || $limit > 50 || $offset < 0) {
            throw new InvalidArgumentException('Paginação inválida.');
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM salas s WHERE ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    }

    $sql = 'SELECT s.id, s.nome, s.criador_id, s.institution_id, s.categoria, s.area_conhecimento, s.prof, '
        . '(SELECT COUNT(*) FROM salas_usuarios su_count WHERE su_count.sala_id = s.id AND su_count.status = "ativo") AS alunos, '
        . 's.materia, s.icone, s.privacidade, s.descricao, s.criado_em '
        . 'FROM salas s WHERE ' . $whereSql . ' ORDER BY s.criado_em DESC, s.id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT :room_limit OFFSET :room_offset';
    }
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit !== null) {
        $stmt->bindValue(':room_limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':room_offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}