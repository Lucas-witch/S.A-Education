<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$subjects = [
    'Português',
    'Matemática',
    'Ciências',
    'História',
    'Geografia',
    'Inglês',
    'Literatura',
    'Redação',
    'Física',
    'Química',
    'Biologia',
    'Filosofia',
    'Sociologia',
];

foreach ($subjects as $subject) {
    $icon = subject_icon_for($subject);
    if (!is_file(__DIR__ . '/../' . $icon)) {
        throw new RuntimeException('Ícone ausente para a matéria: ' . $subject);
    }
}

echo "OK: matérias mapeadas para ícones existentes.\n";
