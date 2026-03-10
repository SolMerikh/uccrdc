<?php

require_once __DIR__ . '/helpers.php';

function mail_config(): array
{
    $cfg = app_config();
    return $cfg['mail'] ?? [
        'driver' => 'log',
        'from_email' => 'no-reply@localhost',
        'from_name' => 'UCC – RDC ISSN Portal',
        'log_path' => __DIR__ . '/../storage/mail.log',
        'admin_notify_to' => '',
    ];
}

function send_email(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    $mc = mail_config();
    $driver = $mc['driver'] ?? 'log';

    $fromEmail = (string)($mc['from_email'] ?? '');
    $fromName = (string)($mc['from_name'] ?? 'UCC – RDC ISSN Portal');

    if ($driver === 'log') {
        $path = (string)($mc['log_path'] ?? (__DIR__ . '/../storage/mail.log'));
        ensure_dir(dirname($path));
        $entry = [
            'ts' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ];
        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        return true;
    }

    if ($driver === 'smtp') {
        $smtp = $mc['smtp'] ?? [];
        // For SMTP providers like Gmail, the From should typically match the authenticated mailbox.
        if (trim($fromEmail) === '' && !empty($smtp['username'])) {
            $fromEmail = (string)$smtp['username'];
        }
        if (trim($fromEmail) === '') {
            $fromEmail = 'no-reply@localhost';
        }
        return smtp_send_email(
            $smtp,
            $fromEmail,
            $fromName,
            $to,
            $subject,
            $htmlBody,
            $textBody
        );
    }

    // Basic PHP mail() fallback.
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . sprintf('%s <%s>', mb_encode_mimeheader($fromName, 'UTF-8'), $fromEmail);

    return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

function smtp_send_email(
    array $smtp,
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $htmlBody,
    ?string $textBody
): bool {
    $host = (string)($smtp['host'] ?? 'localhost');
    $port = (int)($smtp['port'] ?? 25);
    $encryption = strtolower((string)($smtp['encryption'] ?? 'none'));
    $username = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');
    $timeout = (int)($smtp['timeout'] ?? 20);

    $remote = $host;
    if ($encryption === 'ssl') {
        $remote = 'ssl://' . $host;
    }

    $fp = @fsockopen($remote, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        error_log('SMTP Connection Failed - Host: ' . $host . ':' . $port . ' Error: ' . $errstr . ' (errno: ' . $errno . ')');
        return false;
    }
    stream_set_timeout($fp, $timeout);

    try {
        smtp_expect($fp, 220);

        $client = php_uname('n') ?: 'localhost';
        smtp_cmd($fp, 'EHLO ' . $client);
        $ehlo = smtp_read_multiline($fp);
        if (!smtp_multiline_has_code($ehlo, 250)) {
            smtp_cmd($fp, 'HELO ' . $client);
            smtp_expect($fp, 250);
        }

        if ($encryption === 'tls') {
            smtp_cmd($fp, 'STARTTLS');
            smtp_expect($fp, 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Failed to enable TLS.');
            }
            smtp_cmd($fp, 'EHLO ' . $client);
            smtp_expect($fp, 250);
        }

        if ($username !== '') {
            // AUTH LOGIN
            smtp_cmd($fp, 'AUTH LOGIN');
            smtp_expect($fp, 334);
            smtp_cmd($fp, base64_encode($username));
            smtp_expect($fp, 334);
            smtp_cmd($fp, base64_encode($password));
            smtp_expect($fp, 235);
        }

        smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>');
        smtp_expect($fp, 250);

        smtp_cmd($fp, 'RCPT TO:<' . $to . '>');
        $rcpt = smtp_read_line($fp);
        if (!preg_match('/^(250|251)\b/', $rcpt)) {
            throw new RuntimeException('RCPT failed: ' . $rcpt);
        }

        smtp_cmd($fp, 'DATA');
        smtp_expect($fp, 354);

        $raw = build_rfc822_message($fromEmail, $fromName, $to, $subject, $htmlBody, $textBody);
        smtp_data($fp, $raw);
        smtp_expect($fp, 250);

        smtp_cmd($fp, 'QUIT');
        // ignore QUIT response
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP Error: ' . $e->getMessage());
        try {
            @smtp_cmd($fp, 'QUIT');
        } catch (Throwable $ignored) {
        }
        @fclose($fp);
        return false;
    }
}

function build_rfc822_message(
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $htmlBody,
    ?string $textBody
): string {
    $eol = "\r\n";
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
    $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . sprintf('%s <%s>', $encodedFromName, $fromEmail);
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $encodedSubject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (php_uname('n') ?: 'localhost') . '>';

    $text = $textBody;
    if ($text === null || trim($text) === '') {
        // Very simple fallback: strip tags
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $boundary = 'bnd_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $parts = [];
    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: text/plain; charset=UTF-8';
    $parts[] = 'Content-Transfer-Encoding: base64';
    $parts[] = '';
    $parts[] = chunk_split(base64_encode($text));

    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: text/html; charset=UTF-8';
    $parts[] = 'Content-Transfer-Encoding: base64';
    $parts[] = '';
    $parts[] = chunk_split(base64_encode($htmlBody));

    $parts[] = '--' . $boundary . '--';
    $parts[] = '';

    return implode($eol, $headers) . $eol . $eol . implode($eol, $parts);
}

function smtp_cmd($fp, string $cmd): void
{
    fwrite($fp, $cmd . "\r\n");
}

function smtp_read_line($fp): string
{
    $line = fgets($fp, 515);
    if ($line === false) {
        throw new RuntimeException('SMTP read failed.');
    }
    return rtrim($line, "\r\n");
}

function smtp_read_multiline($fp): array
{
    $lines = [];
    while (true) {
        $line = smtp_read_line($fp);
        $lines[] = $line;
        // Multi-line responses have a hyphen after the code (e.g., 250-)
        if (!preg_match('/^\d{3}-/', $line)) {
            break;
        }
    }
    return $lines;
}

function smtp_multiline_has_code(array $lines, int $code): bool
{
    foreach ($lines as $line) {
        if (preg_match('/^' . preg_quote((string)$code, '/') . '\b/', $line)) {
            return true;
        }
    }
    return false;
}

function smtp_expect($fp, int $code): void
{
    $line = smtp_read_line($fp);
    if (!preg_match('/^' . preg_quote((string)$code, '/') . '\b/', $line)) {
        // Eat any remaining multiline lines for clarity
        if (preg_match('/^\d{3}-/', $line)) {
            while (preg_match('/^\d{3}-/', $line)) {
                $line = smtp_read_line($fp);
            }
        }
        throw new RuntimeException('SMTP unexpected response: ' . $line);
    }
}

function smtp_data($fp, string $data): void
{
    // Dot-stuffing and CRLF normalization
    $data = str_replace("\r\n", "\n", $data);
    $data = str_replace("\r", "\n", $data);
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
        fwrite($fp, $line . "\r\n");
    }
    fwrite($fp, ".\r\n");
}

function send_email_many(array $recipients, string $subject, string $htmlBody, ?string $textBody = null): int
{
    $count = 0;
    foreach ($recipients as $to) {
        $to = trim((string)$to);
        if ($to === '') {
            continue;
        }
        if (send_email($to, $subject, $htmlBody, $textBody)) {
            $count++;
        }
    }
    return $count;
}

function format_email_wrapper(string $title, string $htmlContent): string
{
    $titleEsc = htmlspecialchars($title);
    return "<!doctype html><html><head><meta charset=\"utf-8\"></head><body style=\"font-family:Arial,Helvetica,sans-serif; line-height:1.5\">" .
        "<div style=\"max-width:720px;margin:0 auto;padding:16px\">" .
        "<h2 style=\"margin:0 0 12px\">{$titleEsc}</h2>" .
        "<div style=\"border:1px solid #e5e7eb;border-radius:10px;padding:16px\">{$htmlContent}</div>" .
        "<p style=\"color:#6b7280;font-size:12px;margin-top:12px\">UCC – RDC ISSN Application Portal</p>" .
        "</div></body></html>";
}
