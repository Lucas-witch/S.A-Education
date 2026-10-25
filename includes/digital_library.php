<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/admin_material.php';

const LIBRARY_MAX_UPLOAD_BYTES = 20971520;
const LIBRARY_RICH_TEXT_LIMIT = 1000000;

function library_private_storage_root(): string {
    $configured = trim((string) (getenv('PRIVATE_LIBRARY_STORAGE_PATH') ?: ''));
    if ($configured === '' && app_environment() === 'production') {
        throw new RuntimeException('PRIVATE_LIBRARY_STORAGE_PATH deve apontar para uma pasta fora do diretório público.');
    }
    $root = $configured !== ''
        ? $configured
        : dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'sa-education-private-library';

    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento privado da Biblioteca.');
    }
    $resolvedRoot = realpath($root);
    if ($resolvedRoot === false) {
        throw new RuntimeException('O diretório de arquivos privados não pôde ser validado.');
    }

    $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $resolvedDocumentRoot = $documentRoot !== '' ? realpath($documentRoot) : false;
    if ($resolvedDocumentRoot !== false) {
        $rootPrefix = rtrim(strtolower($resolvedRoot), '\\/') . DIRECTORY_SEPARATOR;
        $documentPrefix = rtrim(strtolower($resolvedDocumentRoot), '\\/') . DIRECTORY_SEPARATOR;
        if (
            str_starts_with(strtolower($resolvedRoot) . DIRECTORY_SEPARATOR, $documentPrefix)
            || str_starts_with(strtolower($resolvedDocumentRoot) . DIRECTORY_SEPARATOR, $rootPrefix)
        ) {
            throw new RuntimeException('O armazenamento de materiais deve ficar fora do diretório público.');
        }
    }

    return $resolvedRoot;
}

function library_supported_uploads(): array {
    return [
        'pdf' => ['mime' => 'application/pdf', 'kind' => 'document'],
        'epub' => ['mime' => 'application/epub+zip', 'kind' => 'document'],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'kind' => 'document'],
        'pptx' => ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'kind' => 'document'],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'kind' => 'document'],
        'odt' => ['mime' => 'application/vnd.oasis.opendocument.text', 'kind' => 'document'],
        'odp' => ['mime' => 'application/vnd.oasis.opendocument.presentation', 'kind' => 'document'],
        'ods' => ['mime' => 'application/vnd.oasis.opendocument.spreadsheet', 'kind' => 'document'],
        'txt' => ['mime' => 'text/plain', 'kind' => 'document'],
        'rtf' => ['mime' => 'application/rtf', 'kind' => 'document'],
        'csv' => ['mime' => 'text/csv', 'kind' => 'document'],
        'jpg' => ['mime' => 'image/jpeg', 'kind' => 'image'],
        'jpeg' => ['mime' => 'image/jpeg', 'kind' => 'image'],
        'png' => ['mime' => 'image/png', 'kind' => 'image'],
        'gif' => ['mime' => 'image/gif', 'kind' => 'image'],
        'webp' => ['mime' => 'image/webp', 'kind' => 'image'],
        'mp4' => ['mime' => 'video/mp4', 'kind' => 'video'],
        'webm' => ['mime' => 'video/webm', 'kind' => 'video'],
    ];
}

function library_upload_error_message(int $error): string {
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite configurado para envio pelo servidor.',
        UPLOAD_ERR_PARTIAL => 'O envio do arquivo foi interrompido. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor não possui uma pasta temporária de envio configurada.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo temporário.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o envio do arquivo.',
        default => 'Não foi possível receber o arquivo enviado.',
    };
}

function library_request_body_exceeds_post_limit(): bool {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $configuredLimit = trim((string) ini_get('post_max_size'));
    if ($contentLength <= 0 || $configuredLimit === '' || $configuredLimit === '0') {
        return false;
    }
    if (!preg_match('/^(\d+)\s*([KMG])?$/i', $configuredLimit, $matches)) {
        return false;
    }
    $limit = (int) $matches[1];
    $unit = strtoupper($matches[2] ?? '');
    $multiplier = match ($unit) {
        'K' => 1024,
        'M' => 1048576,
        'G' => 1073741824,
        default => 1,
    };
    return $contentLength > $limit * $multiplier;
}

function library_inspect_upload_file(string $path, string $originalName, string $purpose = 'document'): array {
    if (!in_array($purpose, ['document', 'feed', 'video'], true)) {
        throw new InvalidArgumentException('Tipo de envio inválido.');
    }
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $supported = library_supported_uploads();
    if (!isset($supported[$extension])) {
        throw new InvalidArgumentException('Formato não aceito. Use PDF, EPUB, DOCX, PPTX, XLSX, ODT, ODP, ODS, TXT, RTF, CSV, imagens JPG/PNG/GIF/WEBP ou vídeos MP4/WEBM no Feed.');
    }
    $uploadType = $supported[$extension];
    if (
        ($purpose === 'document' && $uploadType['kind'] !== 'document')
        || ($purpose === 'video' && $uploadType['kind'] !== 'video')
    ) {
        throw new InvalidArgumentException($purpose === 'video'
            ? 'A aula em vídeo deve estar no formato MP4 ou WEBM.'
            : 'A Biblioteca aceita documentos; publique imagens e vídeos pelo Feed.');
    }
    $size = filesize($path);
    if ($size === false || $size <= 0 || $size > LIBRARY_MAX_UPLOAD_BYTES) {
        throw new InvalidArgumentException('O arquivo deve ter no máximo 20 MB.');
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new InvalidArgumentException('Não foi possível verificar o arquivo enviado.');
    }
    $signature = fread($stream, 16);
    fclose($stream);
    $mime = $uploadType['mime'];

    if ($extension === 'pdf' && !str_starts_with($signature, '%PDF-')) {
        throw new InvalidArgumentException('O arquivo enviado não corresponde a um PDF válido.');
    }
    if (in_array($extension, ['epub', 'docx', 'pptx', 'xlsx', 'odt', 'odp', 'ods'], true)) {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão PHP ZIP é necessária para validar arquivos EPUB, Office e OpenDocument.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > 2000) {
            throw new InvalidArgumentException('O arquivo compactado está inválido ou possui estrutura não permitida.');
        }
        try {
            $uncompressedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (!is_array($entry) || !isset($entry['size']) || $entry['size'] < 0) {
                    throw new InvalidArgumentException('A estrutura interna do arquivo está inválida.');
                }
                $uncompressedBytes += (int) $entry['size'];
                if ($uncompressedBytes > 104857600) {
                    throw new InvalidArgumentException('O conteúdo descompactado excede o limite de segurança.');
                }
            }
            $requiredEntries = match ($extension) {
                'epub' => ['mimetype'],
                'docx' => ['[Content_Types].xml', 'word/document.xml'],
                'pptx' => ['[Content_Types].xml', 'ppt/presentation.xml'],
                'xlsx' => ['[Content_Types].xml', 'xl/workbook.xml'],
                'odt' => ['mimetype', 'content.xml'],
                'odp' => ['mimetype', 'content.xml'],
                'ods' => ['mimetype', 'content.xml'],
            };
            foreach ($requiredEntries as $entry) {
                if ($zip->locateName($entry) === false) {
                    throw new InvalidArgumentException('O arquivo não corresponde ao formato informado.');
                }
            }
            if ($extension === 'epub' && $zip->getFromName('mimetype') !== 'application/epub+zip') {
                throw new InvalidArgumentException('O arquivo não corresponde a um EPUB válido.');
            }
            if (in_array($extension, ['odt', 'odp', 'ods'], true)) {
                $expectedMime = [
                    'odt' => 'application/vnd.oasis.opendocument.text',
                    'odp' => 'application/vnd.oasis.opendocument.presentation',
                    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                ][$extension];
                if ($zip->getFromName('mimetype') !== $expectedMime) {
                    throw new InvalidArgumentException('O arquivo não corresponde ao formato OpenDocument informado.');
                }
            }
        } finally {
            $zip->close();
        }
    } elseif ($extension === 'rtf') {
        if (!str_starts_with($signature, '{\\rtf')) {
            throw new InvalidArgumentException('O arquivo não corresponde a um RTF válido.');
        }
    } elseif (in_array($extension, ['txt', 'csv'], true)) {
        $content = file_get_contents($path);
        if (!is_string($content) || str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidArgumentException('O arquivo de texto deve ser UTF-8 válido e sem conteúdo binário.');
        }
        if ($extension === 'csv') {
            $mime = 'text/csv';
        }
    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $image = @getimagesize($path);
        if (!is_array($image) || (string) ($image['mime'] ?? '') !== $mime) {
            throw new InvalidArgumentException('O arquivo de imagem não corresponde ao formato informado.');
        }
    } elseif ($extension === 'mp4') {
        if (substr($signature, 4, 4) !== 'ftyp') {
            throw new InvalidArgumentException('O arquivo não corresponde a um vídeo MP4 válido.');
        }
    } elseif ($extension === 'webm' && substr($signature, 0, 4) !== "\x1A\x45\xDF\xA3") {
        throw new InvalidArgumentException('O arquivo não corresponde a um vídeo WEBM válido.');
    }

    if ($purpose === 'feed' && !in_array($uploadType['kind'], ['document', 'image', 'video'], true)) {
        throw new InvalidArgumentException('Este formato não pode ser anexado ao Feed.');
    }
    return ['extension' => $extension, 'mime' => $mime, 'size' => (int) $size, 'kind' => $uploadType['kind']];
}

function library_store_private_upload(array $upload, string $purpose = 'document'): array {
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['storage_key' => null, 'file_mime' => null, 'file_size' => null, 'file_sha256' => null, 'absolute_path' => null];
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(library_upload_error_message($error));
    }
    $temporaryPath = $upload['tmp_name'] ?? null;
    $originalName = $upload['name'] ?? null;
    if (
        !is_string($temporaryPath)
        || !is_uploaded_file($temporaryPath)
        || !is_string($originalName)
    ) {
        throw new InvalidArgumentException('Não foi possível validar o arquivo enviado.');
    }
    $file = library_inspect_upload_file($temporaryPath, $originalName, $purpose);
    $key = bin2hex(random_bytes(32)) . '.' . $file['extension'];
    $destination = library_private_storage_root() . DIRECTORY_SEPARATOR . $key;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Não foi possível armazenar o arquivo na área privada.');
    }
    if (!chmod($destination, 0600)) {
        unlink($destination);
        throw new RuntimeException('Não foi possível proteger o arquivo armazenado.');
    }
    return [
        'storage_key' => $key,
        'file_mime' => $file['mime'],
        'file_size' => $file['size'],
        'file_sha256' => hash_file('sha256', $destination),
        'absolute_path' => $destination,
    ];
}

function library_private_file_path(string $storageKey, int $expectedSize, string $expectedHash): ?string {
    $supported = library_supported_uploads();
    if (
        preg_match('/^[a-f0-9]{64}\.([a-z0-9]+)$/', $storageKey, $matches) !== 1
        || !isset($supported[strtolower($matches[1])])
        || $expectedSize <= 0
        || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
    ) {
        return null;
    }
    $storageRoot = realpath(library_private_storage_root());
    if ($storageRoot === false) {
        return null;
    }
    $realPath = realpath($storageRoot . DIRECTORY_SEPARATOR . $storageKey);
    $actualHash = $realPath !== false && is_file($realPath) ? hash_file('sha256', $realPath) : false;
    if (
        $realPath === false
        || !str_starts_with(strtolower($realPath), rtrim(strtolower($storageRoot), '\\/') . DIRECTORY_SEPARATOR)
        || !is_file($realPath)
        || (int) filesize($realPath) !== $expectedSize
        || !is_string($actualHash)
        || !hash_equals($expectedHash, $actualHash)
    ) {
        return null;
    }
    return $realPath;
}

function library_sanitize_rich_text(string $html): string {
    if (strlen($html) > LIBRARY_RICH_TEXT_LIMIT) {
        throw new InvalidArgumentException('O texto excede o tamanho máximo permitido.');
    }
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('A extensão PHP DOM é necessária para higienizar textos da Biblioteca.');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="library-content-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        if (!$loaded) {
            throw new InvalidArgumentException('Não foi possível processar o conteúdo do material.');
        }
        $root = $document->getElementById('library-content-root');
        if (!$root instanceof DOMElement) {
            throw new InvalidArgumentException('Não foi possível processar o conteúdo do material.');
        }

        $allowedTags = [
            'a', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3',
            'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup',
            'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul',
        ];
        $sanitize = static function (DOMNode $parent) use (&$sanitize, $allowedTags): void {
            $children = [];
            foreach ($parent->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                if ($child instanceof DOMText) {
                    continue;
                }
                if (!$child instanceof DOMElement) {
                    $parent->removeChild($child);
                    continue;
                }

                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
                    $parent->removeChild($child);
                    continue;
                }
                if (!in_array($tag, $allowedTags, true)) {
                    $sanitize($child);
                    while ($child->firstChild !== null) {
                        $parent->insertBefore($child->firstChild, $child);
                    }
                    $parent->removeChild($child);
                    continue;
                }

                $keptAttributes = [];
                if ($tag === 'a' && $child->hasAttribute('href')) {
                    $href = trim($child->getAttribute('href'));
                    $parts = parse_url($href);
                    if (
                        is_array($parts)
                        && isset($parts['scheme'], $parts['host'])
                        && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
                        && !isset($parts['user'])
                        && !isset($parts['pass'])
                        && !preg_match('/[\x00-\x20]/', $href)
                    ) {
                        $keptAttributes['href'] = $href;
                        $keptAttributes['rel'] = 'noopener noreferrer nofollow';
                    }
                    if ($child->hasAttribute('title')) {
                        $keptAttributes['title'] = mb_substr($child->getAttribute('title'), 0, 250, 'UTF-8');
                    }
                } elseif ($tag === 'img' && $child->hasAttribute('src')) {
                    $source = trim($child->getAttribute('src'));
                    $parts = parse_url($source);
                    if (
                        is_array($parts)
                        && isset($parts['scheme'], $parts['host'])
                        && strtolower((string) $parts['scheme']) === 'https'
                        && !isset($parts['user'])
                        && !isset($parts['pass'])
                        && !preg_match('/[\x00-\x20]/', $source)
                    ) {
                        $keptAttributes['src'] = $source;
                        $keptAttributes['alt'] = mb_substr($child->getAttribute('alt'), 0, 300, 'UTF-8');
                        $keptAttributes['loading'] = 'lazy';
                        $keptAttributes['referrerpolicy'] = 'no-referrer';
                    } else {
                        $parent->removeChild($child);
                        continue;
                    }
                } elseif (in_array($tag, ['td', 'th'], true)) {
                    foreach (['colspan', 'rowspan'] as $attribute) {
                        $value = $child->getAttribute($attribute);
                        if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) {
                            $keptAttributes[$attribute] = $value;
                        }
                    }
                }
                if (in_array($tag, ['blockquote', 'div', 'h1', 'h2', 'h3', 'p'], true) && $child->hasAttribute('style')) {
                    if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $child->getAttribute('style'), $alignment)) {
                        $keptAttributes['style'] = 'text-align:' . strtolower($alignment[1]);
                    }
                }
                if ($tag === 'span' && $child->hasAttribute('style')) {
                    $styles = [];
                    $safeColor = '(?:#[0-9a-f]{3}(?:[0-9a-f]{3})?|rgb\(\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*\))';
                    if (preg_match('/(?:^|;)\s*color\s*:\s*(' . $safeColor . ')\s*(?:;|$)/i', $child->getAttribute('style'), $color)) {
                        $styles[] = 'color:' . strtolower($color[1]);
                    }
                    if (preg_match('/(?:^|;)\s*background-color\s*:\s*(' . $safeColor . ')\s*(?:;|$)/i', $child->getAttribute('style'), $background)) {
                        $styles[] = 'background-color:' . strtolower($background[1]);
                    }
                    if (preg_match('/(?:^|;)\s*font-size\s*:\s*(1[2-9]|2[0-4])px\s*(?:;|$)/i', $child->getAttribute('style'), $fontSize)) {
                        $styles[] = 'font-size:' . $fontSize[1] . 'px';
                    }
                    if ($styles !== []) {
                        $keptAttributes['style'] = implode(';', $styles);
                    }
                }

                while ($child->attributes->length > 0) {
                    $child->removeAttributeNode($child->attributes->item(0));
                }
                foreach ($keptAttributes as $name => $value) {
                    $child->setAttribute($name, $value);
                }
                $sanitize($child);
            }
        };
        $sanitize($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return trim($result);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function normalize_library_submission(array $input, bool $submitting): array {
    $read = static function (string $name, int $maximum, bool $required = false) use ($input): string {
        $value = $input[$name] ?? '';
        if (!is_string($value) || mb_strlen($value, 'UTF-8') > $maximum) {
            throw new InvalidArgumentException('Revise os dados bibliográficos enviados.');
        }
        $value = trim($value);
        if ($required && $value === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios do material.');
        }
        return $value;
    };

    $title = $read('titulo', 180, $submitting);
    $type = $read('tipo', 20, $submitting);
    $typeMap = ['livro', 'apostila', 'pdf', 'artigo', 'material'];
    if ($type !== '' && !in_array($type, $typeMap, true)) {
        throw new InvalidArgumentException('Categoria de material inválida.');
    }
    $rightsStatus = $read('direitos_status', 32);
    $rightsOptions = ['unknown', 'original', 'licensed', 'public_domain', 'institution_authorized'];
    if ($rightsStatus === '') {
        $rightsStatus = 'unknown';
    }
    if (!in_array($rightsStatus, $rightsOptions, true)) {
        throw new InvalidArgumentException('Declaração de direitos inválida.');
    }
    $declared = ($input['direitos_declarados'] ?? '') === '1';
    $evidence = $read('direitos_evidencia', 2000);
    if ($submitting && !$declared) {
        throw new InvalidArgumentException('Confirme a declaração de autoria ou de autorização de distribuição.');
    }
    if ($submitting && $rightsStatus === 'unknown') {
        throw new InvalidArgumentException('Informe a situação dos direitos antes de enviar para revisão.');
    }
    if ($submitting && $rightsStatus !== 'original' && $evidence === '') {
        throw new InvalidArgumentException('Informe a fonte ou evidência da licença/autorização para revisão humana.');
    }
    $source = $read('fonte_origem', 500);
    if ($submitting && $source === '') {
        throw new InvalidArgumentException('Informe a fonte e referência bibliográfica do material.');
    }

    return [
        'titulo' => $title,
        'tipo' => $type,
        'autor' => $read('autor', 500),
        'materia' => $read('materia', 80),
        'area_conhecimento' => $read('area_conhecimento', 120),
        'etapa_ensino' => $read('etapa_ensino', 80),
        'idioma' => $read('idioma', 40),
        'categoria_material' => $read('categoria_material', 80),
        'palavras_chave' => $read('palavras_chave', 1000),
        'descricao' => $read('descricao', 5000),
        'capa_url' => normalize_admin_material_url($read('capa_url', 255)),
        'texto_html' => library_sanitize_rich_text($read('texto_html', LIBRARY_RICH_TEXT_LIMIT)),
        'referencia_bibliografica' => $read('referencia_bibliografica', 3000),
        'fonte_origem' => $source,
        'direitos_status' => $rightsStatus,
        'direitos_evidencia' => $evidence,
        'direitos_declarados' => $declared ? 1 : 0,
    ];
}

function library_store_pdf_upload(array $upload): array {
    return library_store_private_upload($upload, 'document');
}

function save_library_submission(
    PDO $pdo,
    int $userId,
    ?int $institutionId,
    array $input,
    array $upload,
    bool $submitting
): int {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Conta autenticada inválida.');
    }
    $fields = normalize_library_submission($input, $submitting);
    $storedFile = library_store_pdf_upload($upload);
    $fields['autor'] = $fields['autor'] !== '' ? $fields['autor'] : null;
    $fields['materia'] = $fields['materia'] !== '' ? $fields['materia'] : null;
    $fields['area_conhecimento'] = $fields['area_conhecimento'] !== '' ? $fields['area_conhecimento'] : null;
    $fields['etapa_ensino'] = $fields['etapa_ensino'] !== '' ? $fields['etapa_ensino'] : null;
    $fields['idioma'] = $fields['idioma'] !== '' ? $fields['idioma'] : null;
    $fields['categoria_material'] = $fields['categoria_material'] !== '' ? $fields['categoria_material'] : null;
    $fields['palavras_chave'] = $fields['palavras_chave'] !== '' ? $fields['palavras_chave'] : null;
    $fields['descricao'] = $fields['descricao'] !== '' ? $fields['descricao'] : null;
    $fields['capa_url'] = $fields['capa_url'] !== '' ? $fields['capa_url'] : null;
    $fields['referencia_bibliografica'] = $fields['referencia_bibliografica'] !== '' ? $fields['referencia_bibliografica'] : null;
    $fields['fonte_origem'] = $fields['fonte_origem'] !== '' ? $fields['fonte_origem'] : null;
    $fields['direitos_evidencia'] = $fields['direitos_evidencia'] !== '' ? $fields['direitos_evidencia'] : null;
    $fields['revisao_status'] = $submitting ? 'pending_review' : 'draft';
    $availabilityStatus = $submitting ? 'em_revisao' : 'indisponivel';
    $draftId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (array_key_exists('id', $input) && $input['id'] !== '' && ($draftId === false || $draftId === null || $draftId <= 0)) {
        throw new InvalidArgumentException('Identificador de rascunho inválido.');
    }
    $id = $draftId !== false && $draftId !== null && $draftId > 0 ? (int) $draftId : null;
    $previousStorageKey = null;
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        if ($id !== null) {
            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $existingQuery = $pdo->prepare(
                'SELECT id, storage_key FROM biblioteca
                 WHERE id = :id AND owner_user_id = :owner_user_id
                   AND ((instituicao_id = :institution_id) OR (instituicao_id IS NULL AND :institution_null IS NULL))
                   AND revisao_status IN ("draft", "rejected") LIMIT 1' . $lockClause
            );
            $existingQuery->execute([
                'id' => $id,
                'owner_user_id' => $userId,
                'institution_id' => $institutionId,
                'institution_null' => $institutionId,
            ]);
            $existing = $existingQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) {
                throw new DomainException('Esse rascunho não existe ou não pertence à sua conta.');
            }
            $previousStorageKey = $existing['storage_key'] !== null ? (string) $existing['storage_key'] : null;
            $storageKey = $storedFile['storage_key'] ?? $existing['storage_key'];
            if ($submitting && $fields['texto_html'] === '' && $storageKey === null) {
                throw new InvalidArgumentException('Inclua um texto ou PDF antes de enviar o material para revisão.');
            }
            $sql = 'UPDATE biblioteca SET
                titulo=:titulo, autor=:autor, tipo=:tipo, materia=:materia, descricao=:descricao,
                capa_url=:capa_url, disponibilidade="leitura_online", status=:status,
                area_conhecimento=:area_conhecimento, etapa_ensino=:etapa_ensino, idioma=:idioma,
                categoria_material=:categoria_material, palavras_chave=:palavras_chave, texto_html=:texto_html,
                referencia_bibliografica=:referencia_bibliografica, fonte_origem=:fonte_origem,
                direitos_status=:direitos_status, direitos_evidencia=:direitos_evidencia,
                direitos_declarados=:direitos_declarados, revisao_status=:revisao_status,
                revisao_motivo=NULL, storage_key=:storage_key, file_mime=:file_mime,
                file_size=:file_size, file_sha256=:file_sha256
                WHERE id=:id';
            $fields += [
                'status' => $availabilityStatus,
                'storage_key' => $storageKey,
                'file_mime' => $storedFile['file_mime'] ?? ($existing['storage_key'] !== null ? 'application/pdf' : null),
                'file_size' => $storedFile['file_size'] ?? null,
                'file_sha256' => $storedFile['file_sha256'] ?? null,
                'id' => $id,
            ];
            if ($storedFile['storage_key'] === null && $existing['storage_key'] !== null) {
                $fileMeta = $pdo->prepare('SELECT file_mime, file_size, file_sha256 FROM biblioteca WHERE id = :id');
                $fileMeta->execute(['id' => $id]);
                $metadata = $fileMeta->fetch(PDO::FETCH_ASSOC);
                $fields['file_mime'] = $metadata['file_mime'];
                $fields['file_size'] = $metadata['file_size'];
                $fields['file_sha256'] = $metadata['file_sha256'];
            }
            $pdo->prepare($sql)->execute($fields);
        } else {
            if ($submitting && $fields['texto_html'] === '' && $storedFile['storage_key'] === null) {
                throw new InvalidArgumentException('Inclua um texto ou PDF antes de enviar o material para revisão.');
            }
            $fields += [
                'capa_url' => $fields['capa_url'],
                'disponibilidade' => 'leitura_online',
                'status' => $availabilityStatus,
                'instituicao_id' => $institutionId,
                'owner_user_id' => $userId,
                'storage_key' => $storedFile['storage_key'],
                'file_mime' => $storedFile['file_mime'],
                'file_size' => $storedFile['file_size'],
                'file_sha256' => $storedFile['file_sha256'],
            ];
            $insert = $pdo->prepare(
                'INSERT INTO biblioteca
                    (titulo, autor, tipo, materia, descricao, capa_url, disponibilidade, status,
                     instituicao_id, owner_user_id, area_conhecimento, etapa_ensino, idioma,
                     categoria_material, palavras_chave, texto_html, referencia_bibliografica,
                     fonte_origem, direitos_status, direitos_evidencia, direitos_declarados,
                     revisao_status, storage_key, file_mime, file_size, file_sha256)
                 VALUES
                    (:titulo, :autor, :tipo, :materia, :descricao, :capa_url, :disponibilidade, :status,
                     :instituicao_id, :owner_user_id, :area_conhecimento, :etapa_ensino, :idioma,
                     :categoria_material, :palavras_chave, :texto_html, :referencia_bibliografica,
                     :fonte_origem, :direitos_status, :direitos_evidencia, :direitos_declarados,
                     :revisao_status, :storage_key, :file_mime, :file_size, :file_sha256)'
            );
            $insert->execute($fields);
            $id = (int) $pdo->lastInsertId();
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        if (
            $storedFile['storage_key'] !== null
            && $previousStorageKey !== null
            && $previousStorageKey !== $storedFile['storage_key']
        ) {
            $oldPath = library_private_storage_root() . DIRECTORY_SEPARATOR . $previousStorageKey;
            if (is_file($oldPath) && !unlink($oldPath)) {
                error_log('[S.A Education][library_storage] Não foi possível remover o arquivo substituído.');
            }
        }
        return $id;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_string($storedFile['absolute_path']) && is_file($storedFile['absolute_path'])) {
            unlink($storedFile['absolute_path']);
        }
        throw $error;
    }
}

function library_find_readable_material(PDO $pdo, int $materialId, int $userId): ?array {
    if ($materialId <= 0 || $userId <= 0) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT b.*, u.nome_completo AS owner_name,
                (b.owner_user_id = :owner_user_id) AS is_owner
         FROM biblioteca b
         LEFT JOIN usuarios u ON u.id = b.owner_user_id
         WHERE b.id = :material_id LIMIT 1'
    );
    $statement->execute(['owner_user_id' => $userId, 'material_id' => $materialId]);
    $material = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($material)) {
        return null;
    }
    $isOwner = (int) $material['is_owner'] === 1;
    $isAdmin = is_admin_user();
    $hasRights = (int) $material['direitos_declarados'] === 1
        && (string) $material['direitos_status'] !== 'unknown';
    $isPublished = (string) $material['revisao_status'] === 'approved'
        && (string) $material['status'] === 'ativo';
    $isPublic = $material['instituicao_id'] === null && $isPublished && $hasRights;
    $isInstitutionMember = false;
    if ($material['instituicao_id'] !== null && $isPublished && $hasRights) {
        $condition = institution_content_access_condition('b.instituicao_id', ':reader_institution_owner', ':reader_institution_member')
            . ' AND ' . institution_learning_resource_access_condition(
                'library_material',
                'b.id',
                'b.instituicao_id',
                ':reader_resource_owner',
                ':reader_resource_member'
            );
        $access = $pdo->prepare(
            'SELECT ' . $condition . ' FROM biblioteca b WHERE b.id = :reader_material_id'
        );
        $access->execute([
            'reader_institution_owner' => $userId,
            'reader_institution_member' => $userId,
            'reader_resource_owner' => $userId,
            'reader_resource_member' => $userId,
            'reader_material_id' => $materialId,
        ]);
        $isInstitutionMember = (bool) $access->fetchColumn();
    }
    $isDigitalBorrower = false;
    if ($isPublished && $hasRights) {
        $loanAccess = $pdo->prepare(
            'SELECT 1
             FROM library_loans l
             INNER JOIN library_copies c ON c.id = l.copy_id
             WHERE l.user_id = :user_id
               AND c.material_id = :material_id
               AND c.format = "digital"
               AND c.distribution_authorized = 1
               AND c.authorization_evidence IS NOT NULL
               AND c.authorization_evidence <> ""
               AND l.status = "active"
               AND l.due_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $loanAccess->execute(['user_id' => $userId, 'material_id' => $materialId]);
        $isDigitalBorrower = $loanAccess->fetchColumn() !== false;
    }
    if (!$isOwner && !$isAdmin && !$isPublic && !$isInstitutionMember && !$isDigitalBorrower) {
        return null;
    }

    $material['owner_name'] = (string) ($material['owner_name'] ?? '');
    $material['is_owner'] = $isOwner;
    return $material;
}

function library_record_access_event(PDO $pdo, int $userId, int $materialId, string $event): void {
    if (!in_array($event, ['open', 'exercise_export'], true)) {
        throw new InvalidArgumentException('Evento de leitura inválido.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO library_access_events (user_id, material_id, event_type)
         VALUES (:user_id, :material_id, :event_type)'
    );
    $statement->execute(['user_id' => $userId, 'material_id' => $materialId, 'event_type' => $event]);
}

function library_authenticated_user_id(PDO $pdo): int {
    if (isset($_SESSION['instituicao_usuario_id'])) {
        require_institution_auth();
        return (int) $_SESSION['instituicao_usuario_id'];
    }
    require_auth();
    return (int) $_SESSION['usuario_id'];
}
