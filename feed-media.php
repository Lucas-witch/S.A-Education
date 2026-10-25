<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/feed.php';
require_auth();

$userId = (int) ($_SESSION['usuario_id'] ?? 0);
$publicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($userId <= 0 || $publicationId === false || $publicationId === null || $publicationId <= 0) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$publication = feed_get_publication($pdo, (int) $publicationId, $userId, usuario_tem_acesso_premium());
if (
    $publication === null
    || !is_string($publication['media_storage_key'] ?? null)
    || !is_string($publication['media_mime'] ?? null)
    || !is_string($publication['media_sha256'] ?? null)
) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$storageKey = (string) $publication['media_storage_key'];
$extension = strtolower(pathinfo($storageKey, PATHINFO_EXTENSION));
$supported = library_supported_uploads();
if (
    !isset($supported[$extension])
    || $supported[$extension]['mime'] !== (string) $publication['media_mime']
) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$path = library_private_file_path(
    $storageKey,
    (int) ($publication['media_size'] ?? 0),
    (string) $publication['media_sha256']
);
if ($path === null) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$mime = $supported[$extension]['mime'];
$size = (int) filesize($path);
$isStreamable = in_array($supported[$extension]['kind'], ['video', 'image'], true);
$start = 0;
$end = $size - 1;
$status = 200;
if ($supported[$extension]['kind'] === 'video' && isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) $_SERVER['HTTP_RANGE'], $range) !== 1) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    if ($range[1] === '' && $range[2] !== '') {
        $suffixLength = min((int) $range[2], $size);
        $start = $size - $suffixLength;
    } else {
        $start = $range[1] === '' ? 0 : (int) $range[1];
        $end = $range[2] === '' ? $end : min((int) $range[2], $end);
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $status = 206;
}

http_response_code($status);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) ($end - $start + 1));
header('Content-Disposition: ' . ($isStreamable ? 'inline' : 'attachment') . '; filename="feed-' . (int) $publicationId . '.' . $extension . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
if ($supported[$extension]['kind'] === 'video') {
    header('Accept-Ranges: bytes');
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
}
$stream = fopen($path, 'rb');
if ($stream === false) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
if ($start > 0) {
    fseek($stream, $start);
}
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($stream)) {
    $chunk = fread($stream, min(8192, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($stream);
