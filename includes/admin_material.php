<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

function normalize_admin_material(array $input): array {
    $read = static function (string $name, int $maxLength, bool $required = false) use ($input): string {
        $value = $input[$name] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException('Revise os dados do material.');
        }
        $value = trim($value);
        if (mb_strlen($value, 'UTF-8') > $maxLength || ($required && $value === '')) {
            throw new InvalidArgumentException('Revise os dados do material.');
        }
        return $value;
    };

    $title = $read('titulo', 180, true);
    $author = $read('autor', 150);
    $type = $read('tipo', 20, true);
    $subject = $read('materia', 80);
    $description = $read('descricao', 5000);
    $coverUrl = normalize_admin_material_url($read('capa_url', 255));
    $resourceUrl = normalize_admin_material_url($read('arquivo_url', 255));
    $availability = $read('disponibilidade', 20, true);

    if (!in_array($type, ['livro', 'apostila', 'pdf', 'artigo', 'material'], true)) {
        throw new InvalidArgumentException('Tipo de material inválido.');
    }
    if (!in_array($availability, ['gratis', 'leitura_online', 'emprestimo'], true)) {
        throw new InvalidArgumentException('Disponibilidade inválida.');
    }
    if ($resourceUrl === '') {
        throw new InvalidArgumentException('Informe o link do arquivo ou da leitura online.');
    }

    return [
        'titulo' => $title,
        'autor' => $author !== '' ? $author : null,
        'tipo' => $type,
        'materia' => $subject !== '' ? $subject : null,
        'descricao' => $description !== '' ? $description : null,
        'capa_url' => $coverUrl !== '' ? $coverUrl : null,
        'arquivo_url' => $resourceUrl,
        'disponibilidade' => $availability,
    ];
}

function create_admin_material(PDO $pdo, array $input, ?int $ownerUserId = null): int {
    $material = normalize_admin_material($input);
    $statement = $pdo->prepare(
        'INSERT INTO biblioteca (titulo, autor, tipo, materia, descricao, capa_url, arquivo_url, disponibilidade, status, owner_user_id)
         VALUES (:titulo, :autor, :tipo, :materia, :descricao, :capa_url, :arquivo_url, :disponibilidade, "ativo", :owner_user_id)'
    );
    $statement->execute($material + ['owner_user_id' => $ownerUserId]);

    return (int) $pdo->lastInsertId();
}

function create_teacher_material(PDO $pdo, int $userId, array $input): int {
    return with_teacher_monthly_quota(
        $pdo,
        $userId,
        'material',
        true,
        static fn (?int $bankId, bool $publish, array $permissions): int => create_admin_material($pdo, $input, $userId)
    );
}

function normalize_admin_material_url(string $value): string {
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x00-\x20]/', $value)) {
        throw new InvalidArgumentException('Link de material inválido.');
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        throw new InvalidArgumentException('Link de material inválido.');
    }
    foreach (explode('/', (string) ($parts['path'] ?? '')) as $segment) {
        $decodedSegment = rawurldecode($segment);
        if ($decodedSegment === '..' || $decodedSegment === '.') {
            throw new InvalidArgumentException('Caminho de material inválido.');
        }
    }
    if (isset($parts['scheme'])) {
        if (
            !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('Links de material devem usar HTTP ou HTTPS.');
        }
        return $value;
    }
    if (isset($parts['host']) || str_starts_with($value, '/') || str_contains($value, '\\')) {
        throw new InvalidArgumentException('Caminho de material inválido.');
    }
    return $value;
}
