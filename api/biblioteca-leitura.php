<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/digital_library.php';

try {
    $userId = library_authenticated_user_id($pdo);
    $materialId = filter_input(
        $_SERVER['REQUEST_METHOD'] === 'GET' ? INPUT_GET : INPUT_POST,
        'material_id',
        FILTER_VALIDATE_INT
    );
    if ($materialId === false || $materialId === null || $materialId <= 0) {
        throw new InvalidArgumentException('Material inválido.');
    }
    $material = library_find_readable_material($pdo, (int) $materialId, $userId);
    if ($material === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Material não encontrado ou sem acesso.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $annotations = $pdo->prepare(
            'SELECT id, annotation_type, anchor_text, anchor_start, anchor_end, note_text, color, created_at, updated_at
             FROM library_annotations
             WHERE user_id = :user_id AND material_id = :material_id
             ORDER BY created_at'
        );
        $annotations->execute(['user_id' => $userId, 'material_id' => (int) $materialId]);
        $progress = $pdo->prepare(
            'SELECT progress_percent, position_anchor, font_size, line_spacing, reading_mode, updated_at
             FROM library_reading_progress
             WHERE user_id = :user_id AND material_id = :material_id LIMIT 1'
        );
        $progress->execute(['user_id' => $userId, 'material_id' => (int) $materialId]);
        echo json_encode([
            'annotations' => $annotations->fetchAll(PDO::FETCH_ASSOC),
            'progress' => $progress->fetch(PDO::FETCH_ASSOC) ?: null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
        exit;
    }
    verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';
    if ($action === 'preferences') {
        $fontSize = $_POST['font_size'] ?? '';
        $lineSpacing = $_POST['line_spacing'] ?? '';
        $readingMode = $_POST['reading_mode'] ?? '';
        if (
            !is_string($fontSize) || !in_array($fontSize, ['small', 'standard', 'large', 'extra-large'], true)
            || !is_string($lineSpacing) || !in_array($lineSpacing, ['normal', 'wide', 'compact'], true)
            || !is_string($readingMode) || !in_array($readingMode, ['light', 'warm', 'dark'], true)
        ) {
            throw new InvalidArgumentException('Preferências de leitura inválidas.');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare(
                'INSERT INTO library_reading_progress
                    (user_id, material_id, font_size, line_spacing, reading_mode)
                 VALUES
                    (:user_id, :material_id, :font_size, :line_spacing, :reading_mode)
                 ON CONFLICT(user_id, material_id) DO UPDATE SET
                    font_size = excluded.font_size,
                    line_spacing = excluded.line_spacing,
                    reading_mode = excluded.reading_mode,
                    updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO library_reading_progress
                    (user_id, material_id, font_size, line_spacing, reading_mode)
                 VALUES
                    (:user_id, :material_id, :font_size, :line_spacing, :reading_mode)
                 ON DUPLICATE KEY UPDATE
                    font_size = VALUES(font_size),
                    line_spacing = VALUES(line_spacing),
                    reading_mode = VALUES(reading_mode),
                    updated_at = CURRENT_TIMESTAMP'
            );
        }
        $statement->execute([
            'user_id' => $userId,
            'material_id' => (int) $materialId,
            'font_size' => $fontSize,
            'line_spacing' => $lineSpacing,
            'reading_mode' => $readingMode,
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'progress') {
        $percent = filter_var($_POST['progress_percent'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($percent === false || $percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Posição de leitura inválida.');
        }
        $anchor = $_POST['position_anchor'] ?? '';
        if (!is_string($anchor) || strlen($anchor) > 255 || ($anchor !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $anchor) !== 1)) {
            throw new InvalidArgumentException('Referência de posição inválida.');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare(
                'INSERT INTO library_reading_progress (user_id, material_id, progress_percent, position_anchor)
                 VALUES (:user_id, :material_id, :progress_percent, :position_anchor)
                 ON CONFLICT(user_id, material_id) DO UPDATE SET
                    progress_percent = excluded.progress_percent,
                    position_anchor = excluded.position_anchor,
                    updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO library_reading_progress (user_id, material_id, progress_percent, position_anchor)
                 VALUES (:user_id, :material_id, :progress_percent, :position_anchor)
                 ON DUPLICATE KEY UPDATE
                    progress_percent = VALUES(progress_percent),
                    position_anchor = VALUES(position_anchor),
                    updated_at = CURRENT_TIMESTAMP'
            );
        }
        $statement->execute([
            'user_id' => $userId,
            'material_id' => (int) $materialId,
            'progress_percent' => $percent,
            'position_anchor' => $anchor !== '' ? $anchor : null,
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'annotation_create') {
        $type = $_POST['annotation_type'] ?? 'note';
        $text = $_POST['note_text'] ?? '';
        $anchorText = $_POST['anchor_text'] ?? '';
        $color = $_POST['color'] ?? 'yellow';
        if (
            !is_string($type)
            || !in_array($type, ['note', 'highlight'], true)
            || !is_string($text)
            || mb_strlen($text, 'UTF-8') > 3000
            || !is_string($anchorText)
            || mb_strlen($anchorText, 'UTF-8') > 1000
            || !is_string($color)
            || !in_array($color, ['yellow', 'green', 'purple'], true)
            || ($type === 'note' && trim($text) === '')
            || ($type === 'highlight' && trim($anchorText) === '')
        ) {
            throw new InvalidArgumentException('Anotação inválida.');
        }
        $start = filter_var($_POST['anchor_start'] ?? null, FILTER_VALIDATE_INT);
        $end = filter_var($_POST['anchor_end'] ?? null, FILTER_VALIDATE_INT);
        if ($type === 'highlight' && ($start === false || $end === false || $start < 0 || $end <= $start || $end > 2000000)) {
            throw new InvalidArgumentException('Trecho destacado inválido.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO library_annotations
                (user_id, material_id, annotation_type, anchor_text, anchor_start, anchor_end, note_text, color)
             VALUES
                (:user_id, :material_id, :annotation_type, :anchor_text, :anchor_start, :anchor_end, :note_text, :color)'
        );
        $insert->execute([
            'user_id' => $userId,
            'material_id' => (int) $materialId,
            'annotation_type' => $type,
            'anchor_text' => $anchorText !== '' ? $anchorText : null,
            'anchor_start' => $type === 'highlight' ? $start : null,
            'anchor_end' => $type === 'highlight' ? $end : null,
            'note_text' => trim($text),
            'color' => $color,
        ]);
        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'annotation_delete') {
        $annotationId = filter_var($_POST['annotation_id'] ?? null, FILTER_VALIDATE_INT);
        if ($annotationId === false || $annotationId <= 0) {
            throw new InvalidArgumentException('Anotação inválida.');
        }
        $delete = $pdo->prepare(
            'DELETE FROM library_annotations
             WHERE id = :id AND material_id = :material_id AND user_id = :user_id'
        );
        $delete->execute([
            'id' => $annotationId,
            'material_id' => (int) $materialId,
            'user_id' => $userId,
        ]);
        if ($delete->rowCount() !== 1) {
            throw new DomainException('Anotação não encontrada para esta conta.');
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação de leitura inválida.');
} catch (PermissionDeniedException | DomainException $error) {
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $error) {
    error_log('[S.A Education][library_reading][PDO] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar a leitura agora.']);
}
