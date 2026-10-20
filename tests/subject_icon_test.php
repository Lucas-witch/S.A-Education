<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$subjects = [
    'Biologia',
    'Matemática Avançada',
    'História do Brasil',
    'Geografia',
    'Química Orgânica',
    'Física Moderna',
    'Redação',
    'Sociologia',
    'Filosofia',
    'Português',
    'Línguas estrangeiras',
];

foreach ($subjects as $subject) {
    $icon = subject_icon_for($subject);
    if (!is_file(__DIR__ . '/../' . $icon)) {
        throw new RuntimeException('Ícone ausente para a matéria: ' . $subject);
    }
}

echo "OK: matérias mapeadas para ícones existentes.\n";
