<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_question_subject_badge(array $subject, string $variant = 'compact'): void {
    if (!in_array($variant, ['compact', 'tile'], true)) {
        throw new InvalidArgumentException('Variação de selo de matéria inválida.');
    }

    $subjectId = (int) ($subject['id'] ?? $subject['subject_id'] ?? 0);
    $name = trim((string) ($subject['name'] ?? $subject['subject_name'] ?? ''));
    if ($subjectId <= 0 || $name === '') {
        error_log('[S.A Education][question_catalog_subject] Matéria ausente ou inválida ao renderizar selo.');
        $name = 'Matéria não informada';
    }

    $imagePath = (string) ($subject['image_path'] ?? $subject['subject_image_path'] ?? '');
    $validImagePath = str_starts_with($imagePath, 'assets/images/icons/')
        && !str_contains($imagePath, '..')
        && preg_match('/^[A-Za-z0-9_() .\/-]+\.png$/', $imagePath) === 1;
    $imageExists = $validImagePath
        && is_file(
            dirname(__DIR__)
            . DIRECTORY_SEPARATOR . 'apps'
            . DIRECTORY_SEPARATOR . 'web'
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath)
        );
    if ($imagePath !== '' && !$imageExists) {
        error_log('[S.A Education][question_catalog_asset] Imagem de matéria ausente ou inválida: ' . $imagePath);
    }

    $initials = mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
    ?>
    <span class="question-subject-badge question-subject-badge-<?= esc($variant) ?>">
      <?php if ($imageExists): ?>
        <img src="<?= esc($imagePath) ?>" alt="" loading="lazy" decoding="async">
      <?php else: ?>
        <span class="question-subject-badge-fallback" aria-hidden="true"><?= esc($initials) ?></span>
      <?php endif; ?>
      <span><?= esc($name) ?></span>
    </span>
    <?php
}
