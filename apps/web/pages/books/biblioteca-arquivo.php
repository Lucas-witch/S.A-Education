<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/digital_library.php';

$userId = library_authenticated_user_id($pdo);
$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($materialId === false || $materialId === null || $materialId <= 0) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$material = library_find_readable_material($pdo, (int) $materialId, $userId);
if (
    $material === null
    || !is_string($material['storage_key'])
    || !isset($material['file_mime'], $material['file_size'], $material['file_sha256'])
) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$supported = library_supported_uploads();
$extension = strtolower(pathinfo((string) $material['storage_key'], PATHINFO_EXTENSION));
if (
    !isset($supported[$extension])
    || $supported[$extension]['mime'] !== (string) $material['file_mime']
    || !in_array($supported[$extension]['kind'], ['document'], true)
) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$realPath = library_private_file_path(
    (string) $material['storage_key'],
    (int) $material['file_size'],
    (string) $material['file_sha256']
);
if ($realPath === null) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$isPdf = $extension === 'pdf';
header('Content-Type: ' . $supported[$extension]['mime']);
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: ' . ($isPdf ? 'inline' : 'attachment') . '; filename="material-educacional.' . $extension . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
if ($isPdf) {
    header('Content-Security-Policy: default-src \'none\'; sandbox');
}
readfile($realPath);
