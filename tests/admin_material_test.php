<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_material.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE biblioteca (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        autor TEXT,
        tipo TEXT NOT NULL,
        materia TEXT,
        descricao TEXT,
        capa_url TEXT,
        arquivo_url TEXT,
        disponibilidade TEXT NOT NULL,
        status TEXT NOT NULL,
        owner_user_id INTEGER
    )'
);

function assert_admin_material(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$material = normalize_admin_material([
    'titulo' => 'Livro de teste',
    'autor' => 'Autora',
    'tipo' => 'livro',
    'materia' => 'História',
    'descricao' => 'Material de teste.',
    'capa_url' => 'https://example.test/capa.jpg',
    'arquivo_url' => 'https://example.test/livro.pdf',
    'disponibilidade' => 'leitura_online',
]);
assert_admin_material($material['titulo'] === 'Livro de teste', 'Título do livro deve ser preservado.');
assert_admin_material($material['arquivo_url'] === 'https://example.test/livro.pdf', 'O link do material deve ser preservado.');
assert_admin_material($material['tipo'] === 'livro' && $material['disponibilidade'] === 'leitura_online', 'Tipo e disponibilidade permitidos devem ser preservados.');
$materialId = create_admin_material($pdo, [
    'titulo' => 'Livro publicado',
    'autor' => 'Autor',
    'tipo' => 'livro',
    'arquivo_url' => 'https://example.test/livro-publicado.pdf',
    'disponibilidade' => 'gratis',
]);
$savedMaterial = $pdo->query('SELECT titulo, tipo, status FROM biblioteca WHERE id = ' . $materialId)->fetch(PDO::FETCH_ASSOC);
assert_admin_material(
    is_array($savedMaterial) && $savedMaterial['titulo'] === 'Livro publicado' && $savedMaterial['tipo'] === 'livro' && $savedMaterial['status'] === 'ativo',
    'O livro validado deve ser publicado como ativo na Biblioteca.'
);

foreach ([
    ['scheme' => 'javascript:alert(1)', 'error' => true],
    ['scheme' => 'https://example.test/../privado.pdf', 'error' => true],
    ['scheme' => '', 'error' => true],
] as $case) {
    try {
        normalize_admin_material([
            'titulo' => 'Livro de teste',
            'tipo' => 'livro',
            'arquivo_url' => $case['scheme'],
            'disponibilidade' => 'gratis',
        ]);
        throw new RuntimeException('Link inválido deveria ser rejeitado.');
    } catch (InvalidArgumentException) {
    }
}

try {
    normalize_admin_material([
        'titulo' => 'Livro de teste',
        'tipo' => 'video_aula',
        'arquivo_url' => 'https://example.test/material',
        'disponibilidade' => 'gratis',
    ]);
    throw new RuntimeException('Tipo fora da lista da Biblioteca deveria ser rejeitado.');
} catch (InvalidArgumentException) {
}

echo "OK: dados e URLs da publicação de materiais administrativos são validados.\n";
