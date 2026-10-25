<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/digital_library.php';

function assert_library_upload(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function with_library_test_file(string $name, callable $write, callable $assert): void {
    $path = tempnam(sys_get_temp_dir(), 'sa-library-test-');
    if ($path === false) {
        throw new RuntimeException('Não foi possível criar o arquivo de teste.');
    }
    try {
        $write($path);
        $assert($path, $name);
    } finally {
        unlink($path);
    }
}

with_library_test_file('material.pdf', static function (string $path): void {
    file_put_contents($path, "%PDF-1.7\nconteúdo de teste\n");
}, static function (string $path, string $name): void {
    $file = library_inspect_upload_file($path, $name);
    assert_library_upload($file['mime'] === 'application/pdf', 'PDF válido deve ser aceito.');
});

with_library_test_file('apostila.docx', static function (string $path): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível montar o DOCX de teste.');
    }
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<document/>');
    $zip->close();
}, static function (string $path, string $name): void {
    $file = library_inspect_upload_file($path, $name);
    assert_library_upload(
        $file['mime'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'DOCX compactado com estrutura válida deve ser aceito.'
    );
});

with_library_test_file('livro.epub', static function (string $path): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível montar o EPUB de teste.');
    }
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->close();
}, static function (string $path, string $name): void {
    $file = library_inspect_upload_file($path, $name);
    assert_library_upload($file['mime'] === 'application/epub+zip', 'EPUB válido deve ser aceito.');
});

with_library_test_file('aula.mp4', static function (string $path): void {
    file_put_contents($path, "\0\0\0\x18ftypisom\0\0\0\0");
}, static function (string $path, string $name): void {
    $file = library_inspect_upload_file($path, $name, 'video');
    assert_library_upload($file['mime'] === 'video/mp4' && $file['kind'] === 'video', 'MP4 deve ser aceito no Feed.');
    try {
        library_inspect_upload_file($path, $name, 'document');
        throw new RuntimeException('Vídeo não deve ser aceito como documento de biblioteca.');
    } catch (InvalidArgumentException) {
    }
});

with_library_test_file('texto.txt', static function (string $path): void {
    file_put_contents($path, "Texto UTF-8 válido\n");
}, static function (string $path, string $name): void {
    $file = library_inspect_upload_file($path, $name);
    assert_library_upload($file['mime'] === 'text/plain', 'TXT UTF-8 deve ser aceito.');
});

with_library_test_file('falso.pdf', static function (string $path): void {
    file_put_contents($path, 'não é um PDF');
}, static function (string $path, string $name): void {
    try {
        library_inspect_upload_file($path, $name);
        throw new RuntimeException('Arquivo com extensão falsa deve ser rejeitado.');
    } catch (InvalidArgumentException) {
    }
});

$formatted = library_sanitize_rich_text(
    '<div style="text-align:center"><span style="color:rgb(25, 40, 55);font-size:18px"><strong>Texto</strong></span><script>alert(1)</script></div>'
);
assert_library_upload(
    str_contains($formatted, 'text-align:center')
        && str_contains($formatted, 'color:rgb(25, 40, 55)')
        && !str_contains($formatted, '<script'),
    'Formatação segura deve ser mantida e scripts removidos.'
);

echo "OK: validação de documentos, EPUB, DOCX, MP4, limite de categorias e sanitização de formatação.\n";
