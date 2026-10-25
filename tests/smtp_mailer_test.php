<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/smtp_mailer.php';

$stream = fopen('php://memory', 'w+');
fwrite($stream, "250-example.test\r\n250 AUTH LOGIN\r\n");
rewind($stream);
[$code, $response] = smtp_read_response($stream);
if ($code !== 250 || !str_contains($response, '250 AUTH LOGIN')) {
    throw new RuntimeException('Respostas SMTP multilinha devem ser analisadas corretamente.');
}
fclose($stream);

$stream = fopen('php://memory', 'w+');
try {
    smtp_command($stream, "EHLO host\r\nMAIL FROM:<attacker@example.com>", [250]);
    throw new RuntimeException('Comandos SMTP com quebra de linha devem ser rejeitados.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'inválido')) {
        throw $error;
    }
}
fclose($stream);

echo "OK: parser SMTP multilinha e bloqueio de injeção em comandos.\n";
