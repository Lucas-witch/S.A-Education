<?php
declare(strict_types=1);

function smtp_read_response($connection): array {
    $lines = [];
    do {
        $line = fgets($connection, 8192);
        if ($line === false) {
            throw new RuntimeException('Conexão SMTP encerrada antes da resposta.');
        }
        $lines[] = rtrim($line, "\r\n");
        if (count($lines) > 30) {
            throw new RuntimeException('Resposta SMTP excedeu o limite de linhas.');
        }
    } while (isset($line[3]) && $line[3] === '-');

    if (!preg_match('/^(\d{3})[ -]/', $lines[count($lines) - 1], $matches)) {
        throw new RuntimeException('Resposta SMTP inválida.');
    }

    return [(int) $matches[1], implode("\n", $lines)];
}

function smtp_expect($connection, array $expectedCodes): string {
    [$code, $response] = smtp_read_response($connection);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Servidor SMTP recusou a operação (' . $code . ').');
    }
    return $response;
}

function smtp_write_all($connection, string $data): void {
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($connection, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Não foi possível enviar dados ao servidor SMTP.');
        }
        $offset += $written;
    }
}

function smtp_command($connection, string $command, array $expectedCodes): string {
    if (str_contains($command, "\r") || str_contains($command, "\n")) {
        throw new RuntimeException('Comando SMTP inválido.');
    }
    smtp_write_all($connection, $command . "\r\n");
    return smtp_expect($connection, $expectedCodes);
}

function smtp_authenticate($connection, string $ehloResponse, string $username, string $password): void {
    if ($username === '' && $password === '') {
        return;
    }
    if ($username === '' || $password === '') {
        throw new RuntimeException('As credenciais SMTP estão incompletas.');
    }

    $capabilities = strtoupper($ehloResponse);
    if (preg_match('/\bAUTH(?:=|\s)[^\r\n]*\bPLAIN\b/', $capabilities) === 1) {
        $credentials = base64_encode("\0" . $username . "\0" . $password);
        $response = smtp_command($connection, 'AUTH PLAIN ' . $credentials, [235, 334]);
        if (str_starts_with($response, '334')) {
            smtp_command($connection, $credentials, [235]);
        }
        return;
    }

    if (preg_match('/\bAUTH(?:=|\s)[^\r\n]*\bLOGIN\b/', $capabilities) !== 1) {
        throw new RuntimeException('O servidor SMTP não anuncia um método de autenticação compatível.');
    }
    smtp_command($connection, 'AUTH LOGIN', [334]);
    smtp_command($connection, base64_encode($username), [334]);
    smtp_command($connection, base64_encode($password), [235]);
}

function send_smtp_message(string $recipient, string $subject, string $body): void {
    $host = trim((string) (getenv('SMTP_HOST') ?: ''));
    $port = (int) (getenv('SMTP_PORT') ?: 587);
    $encryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));
    $username = (string) (getenv('SMTP_USERNAME') ?: '');
    $password = (string) (getenv('SMTP_PASSWORD') ?: '');
    $from = trim((string) (getenv('SMTP_FROM_EMAIL') ?: ''));
    $fromName = trim((string) (getenv('SMTP_FROM_NAME') ?: 'S.A Education'));

    if (
        $host === ''
        || !preg_match('/^[A-Za-z0-9.-]+$/', $host)
        || $port < 1
        || $port > 65535
        || !in_array($encryption, ['tls', 'ssl'], true)
        || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
        || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || str_contains($fromName, "\r")
        || str_contains($fromName, "\n")
    ) {
        throw new RuntimeException('Configuração SMTP incompleta ou inválida.');
    }

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
        ],
    ]);
    $connection = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errorCode,
        $errorMessage,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($connection === false) {
        throw new RuntimeException('Não foi possível conectar ao servidor SMTP (' . $errorCode . ').');
    }

    stream_set_timeout($connection, 10);
    try {
        smtp_expect($connection, [220]);
        $helo = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (gethostname() ?: 'localhost')) ?: 'localhost';
        $ehloResponse = smtp_command($connection, 'EHLO ' . $helo, [250]);

        if ($encryption === 'tls') {
            smtp_command($connection, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Não foi possível estabelecer TLS com o servidor SMTP.');
            }
            $ehloResponse = smtp_command($connection, 'EHLO ' . $helo, [250]);
        }
        smtp_authenticate($connection, $ehloResponse, $username, $password);

        smtp_command($connection, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($connection, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtp_command($connection, 'DATA', [354]);

        $safeSubject = mb_encode_mimeheader(str_replace(["\r", "\n"], '', $subject), 'UTF-8', 'B', "\r\n");
        $safeFromName = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n");
        $messageIdHost = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (gethostname() ?: 'localhost')) ?: 'localhost';
        $headers = [
            'From: ' . $safeFromName . ' <' . $from . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $safeSubject,
            'Date: ' . gmdate(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $messageIdHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $encodedBody = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
        $data = preg_replace('/(?m)^\./', '..', $data);
        smtp_write_all($connection, $data . ".\r\n");
        smtp_expect($connection, [250]);
        smtp_command($connection, 'QUIT', [221]);
    } finally {
        fclose($connection);
    }
}
