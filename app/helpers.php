<?php

function app_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $config = require __DIR__ . '/config.php';
    return $config;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify_or_die(): void
{
    start_session();
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        json_error('Invalid CSRF token.', 419);
    }
}

function json_ok(array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data);
    exit;
}

function json_error(string $message, int $status = 400, array $errors = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $message, 'errors' => $errors]);
    exit;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

function sanitize_string(?string $value): string
{
    return trim((string)$value);
}

function validate_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function is_pdf_file(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return false;
    }
    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);
    // Some environments report application/octet-stream for PDFs; keep strict-ish.
    return $mime === 'application/pdf' || $mime === 'application/x-pdf';
}

function parse_nullable_decimal(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    // Normalize commas
    $value = str_replace(',', '', $value);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        return null;
    }
    return $value;
}

function parse_nullable_int(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    // Normalize commas (e.g. 1,000)
    $value = str_replace(',', '', $value);

    // Whole numbers only
    if (!preg_match('/^\d+$/', $value)) {
        return null;
    }

    return $value;
}

function safe_filename(string $originalName): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName);
    $name = trim($name, '._-');
    if ($name === '') {
        $name = 'file';
    }
    return $name;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function move_uploaded_file_validated(array $file, string $destDir, array $allowedExts, int $maxBytes): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid upload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('File is too large.');
    }

    $original = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Invalid file type.');
    }

    ensure_dir($destDir);

    $base = pathinfo(safe_filename($original), PATHINFO_FILENAME);
    $unique = $base . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $unique;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to save upload.');
    }

    // Return a web-relative path for storing in DB.
    $projectRoot = realpath(__DIR__ . '/..');
    $realDest = realpath($destPath);
    if (!$projectRoot || !$realDest || strpos($realDest, $projectRoot) !== 0) {
        throw new RuntimeException('Upload path error.');
    }

    $relative = str_replace('\\', '/', substr($realDest, strlen($projectRoot)));
    return $relative === '' ? '/' : $relative;
}
