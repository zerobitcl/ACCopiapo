<?php
declare(strict_types=1);

/**
 * SMTP AUTH vía Hostinger Mail (SPF/DKIM alineados).
 *
 * @param list<string>|string $to
 */
function smtp_send(array $cfg, array|string $to, string $subject, string $body, string $fromName): bool
{
    $host = $cfg['host'] ?? 'smtp.hostinger.com';
    $port = (int) ($cfg['port'] ?? 465);
    $user = (string) ($cfg['user'] ?? '');
    $pass = (string) ($cfg['pass'] ?? '');
    $from = (string) ($cfg['from'] ?? $user);
    $recipients = array_values(array_unique(array_filter(
        is_array($to) ? $to : [$to],
        static fn($email) => is_string($email) && $email !== ''
    )));

    if ($user === '' || $pass === '' || $from === '' || $recipients === []) {
        return false;
    }

    $errno = 0;
    $errstr = '';
    $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 20);

    $read = static function () use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $expect = static function (string $resp, string $code) use ($socket): bool {
        if (!str_starts_with($resp, $code)) {
            fclose($socket);
            return false;
        }
        return true;
    };

    $write = static function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    if (!$expect($read(), '220')) {
        return false;
    }

    $write('EHLO aireacondicionadocopiapo.cl');
    if (!$expect($read(), '250')) {
        return false;
    }

    $write('AUTH LOGIN');
    if (!$expect($read(), '334')) {
        return false;
    }
    $write(base64_encode($user));
    if (!$expect($read(), '334')) {
        return false;
    }
    $write(base64_encode($pass));
    if (!$expect($read(), '235')) {
        return false;
    }

    $write('MAIL FROM:<' . $from . '>');
    if (!$expect($read(), '250')) {
        return false;
    }

    foreach ($recipients as $rcpt) {
        $write('RCPT TO:<' . $rcpt . '>');
        if (!$expect($read(), '250')) {
            return false;
        }
    }

    $write('DATA');
    if (!$expect($read(), '354')) {
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $safeName = str_replace(["\r", "\n", '"'], '', $fromName);
    $toHeader = implode(', ', array_map(
        static fn(string $email): string => '<' . $email . '>',
        $recipients
    ));

    $headers = [
        'Date: ' . date('r'),
        'From: "' . $safeName . '" <' . $from . '>',
        'To: ' . $toHeader,
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@aireacondicionadocopiapo.cl>',
    ];

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
    $write($payload);
    if (!$expect($read(), '250')) {
        return false;
    }

    $write('QUIT');
    fclose($socket);
    return true;
}
