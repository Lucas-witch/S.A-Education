<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil-dashboard.php');
    exit;
}

verify_csrf($_POST['csrf'] ?? '');
require_once __DIR__ . '/conexao.php';

$usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($usuarioId === false || $usuarioId <= 0) {
    http_response_code(401);
    header('Location: login.php');
    exit;
}

$nome = trim((string) ($_POST['nome_completo'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$username = trim((string) ($_POST['username'] ?? ''));
$formValues = [
    'nome_completo' => $nome,
    'email' => $email,
    'username' => $username,
];

$redirectWithError = static function (string $message) use ($formValues): void {
    $_SESSION['profile_edit_form'] = $formValues;
    $_SESSION['profile_edit_flash'] = ['type' => 'error', 'message' => $message];
    header('Location: perfil-dashboard.php#editar-perfil');
    exit;
};

$validationErrors = validate_profile_fields($nome, $email, $username);
if ($validationErrors !== []) {
    $redirectWithError((string) reset($validationErrors));
}

try {
    $currentStmt = $pdo->prepare(
        'SELECT perfil_imagem
         FROM usuarios
         WHERE id = :id
         LIMIT 1'
    );
    $currentStmt->execute(['id' => $usuarioId]);
    $currentUser = $currentStmt->fetch();
} catch (PDOException $exception) {
    error_log('[S.A Education][profile_read][PDO] ' . $exception->getMessage());
    $redirectWithError('Não foi possível carregar seus dados agora. Tente novamente.');
}

if (!$currentUser) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

try {
    $duplicateStmt = $pdo->prepare(
        'SELECT email, username
         FROM usuarios
         WHERE id <> :id
           AND (email = :email OR username = :username)
         LIMIT 1'
    );
    $duplicateStmt->execute([
        'id' => $usuarioId,
        'email' => $email,
        'username' => $username,
    ]);
    $duplicate = $duplicateStmt->fetch();
} catch (PDOException $exception) {
    error_log('[S.A Education][profile_duplicate_check][PDO] ' . $exception->getMessage());
    $redirectWithError('Não foi possível validar seus dados agora. Tente novamente.');
}

if ($duplicate) {
    $message = strcasecmp((string) $duplicate['email'], $email) === 0
        ? 'Este e-mail já está sendo usado por outra conta.'
        : 'Este nome de usuário já está sendo usado por outra conta.';
    $redirectWithError($message);
}

$defaultImage = 'assets/images/icons/user-icon.png';
$oldImage = (string) ($currentUser['perfil_imagem'] ?? $defaultImage);
$newImage = $oldImage !== '' ? $oldImage : $defaultImage;
$newFilePath = null;
$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'perfil';
$removePhoto = ($_POST['remover_foto'] ?? '') === '1';
$photoUpload = $_FILES['foto'] ?? null;
$uploadError = is_array($photoUpload) ? (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

if ($uploadError !== UPLOAD_ERR_NO_FILE && $uploadError !== UPLOAD_ERR_OK) {
    $redirectWithError($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
        ? 'A foto excede o limite permitido de 5 MB.'
        : 'Não foi possível receber a foto. Tente novamente.');
}

if ($uploadError === UPLOAD_ERR_OK) {
    if (!is_array($photoUpload) || !isset($photoUpload['tmp_name']) || !is_uploaded_file((string) $photoUpload['tmp_name'])) {
        $redirectWithError('O arquivo enviado não pôde ser validado.');
    }

    $fileSize = filesize((string) $photoUpload['tmp_name']);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        $redirectWithError('A foto deve ter até 5 MB.');
    }

    if (!extension_loaded('fileinfo') || !function_exists('getimagesize')) {
        error_log('[S.A Education][profile_upload] Fileinfo ou leitor de imagens indisponível.');
        $redirectWithError('O envio de fotos está temporariamente indisponível.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        error_log('[S.A Education][profile_upload] Não foi possível iniciar a validação MIME.');
        $redirectWithError('O envio de fotos está temporariamente indisponível.');
    }
    $mime = finfo_file($finfo, (string) $photoUpload['tmp_name']);
    finfo_close($finfo);
    $imageInfo = @getimagesize((string) $photoUpload['tmp_name']);
    $allowedImages = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mime) || !isset($allowedImages[$mime]) || !is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== $mime) {
        $redirectWithError('Envie uma imagem JPEG, PNG ou WebP válida.');
    }

    if (is_link($uploadDirectory)) {
        error_log('[S.A Education][profile_upload] Diretório de fotos é um link simbólico.');
        $redirectWithError('Não foi possível salvar a foto agora.');
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        error_log('[S.A Education][profile_upload] Não foi possível criar o diretório de fotos.');
        $redirectWithError('Não foi possível salvar a foto agora.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedImages[$mime];
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $photoUpload['tmp_name'], $destination)) {
        error_log('[S.A Education][profile_upload] Não foi possível mover o arquivo validado.');
        $redirectWithError('Não foi possível salvar a foto agora.');
    }

    $newImage = 'uploads/perfil/' . $filename;
    $newFilePath = $destination;
} elseif ($removePhoto) {
    $newImage = $defaultImage;
}

try {
    $updateStmt = $pdo->prepare(
        'UPDATE usuarios
         SET nome_completo = :nome, email = :email, username = :username, perfil_imagem = :perfil_imagem
         WHERE id = :id'
    );
    $updateStmt->execute([
        'nome' => $nome,
        'email' => $email,
        'username' => $username,
        'perfil_imagem' => $newImage,
        'id' => $usuarioId,
    ]);

    sync_authenticated_profile_session([
        'id' => $usuarioId,
        'nome_completo' => $nome,
        'email' => $email,
        'username' => $username,
        'perfil_imagem' => $newImage,
    ]);
} catch (PDOException $exception) {
    if ($newFilePath !== null && is_file($newFilePath)) {
        unlink($newFilePath);
    }
    error_log('[S.A Education][profile_update][PDO] ' . $exception->getMessage());
    $redirectWithError($exception->getCode() === '23000'
        ? 'O e-mail ou nome de usuário já está sendo usado por outra conta.'
        : 'Não foi possível salvar suas alterações agora. Tente novamente.');
}

$normalizedOldImage = str_replace('\\', '/', $oldImage);
if (
    $normalizedOldImage !== $newImage
    && preg_match('#^uploads/perfil/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $normalizedOldImage) === 1
) {
    $oldFilePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedOldImage);
    if (is_file($oldFilePath) && !unlink($oldFilePath)) {
        error_log('[S.A Education][profile_update] Não foi possível remover a foto anterior após a atualização.');
    }
}

unset($_SESSION['profile_edit_form']);
$_SESSION['profile_edit_flash'] = ['type' => 'success', 'message' => 'Seu perfil foi atualizado com sucesso.'];
header('Location: perfil-dashboard.php');
exit;
