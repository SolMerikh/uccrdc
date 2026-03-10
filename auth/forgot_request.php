<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/mailer.php';

require_post();
csrf_verify_or_die();

$email = sanitize_string($_POST['email'] ?? '');

// Always return a generic success to avoid user enumeration.
$genericMsg = 'If the email exists, a password reset link was sent.';

if ($email === '' || !validate_email($email)) {
    json_ok(['message' => $genericMsg]);
}

try {
    $stmt = db()->prepare('SELECT id, email, first_name, last_name, status FROM authors WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        json_ok(['message' => $genericMsg]);
    }

    // Optional: only allow resets for Active accounts
    if (($user['status'] ?? 'Inactive') !== 'Active') {
        json_ok(['message' => $genericMsg]);
    }

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $ins = db()->prepare('INSERT INTO password_resets (author_id, token_hash, expires_at, request_ip, request_ua) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([
        (int)$user['id'],
        $tokenHash,
        $expiresAt,
        $ip,
        $ua ? mb_substr($ua, 0, 255) : null,
    ]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetUrl = $scheme . '://' . $host . '/uccrdc/auth/reset_password.php?token=' . urlencode($token);

    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if ($name === '') {
        $name = $user['email'];
    }

    $subject = 'Password Reset – UCC – RDC ISSN Portal';
    $html = format_email_wrapper('Password Reset Request',
        '<p>Hello ' . htmlspecialchars($name) . ',</p>' .
        '<p>We received a request to reset your password. Click the button below to set a new password:</p>' .
        '<p style="margin:20px 0"><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px">Reset Password</a></p>' .
        '<p>If you did not request this, you can ignore this email.</p>' .
        '<p style="color:#6b7280;font-size:12px">This link expires in 1 hour.</p>'
    );

    // If email fails, still return generic success.
    @send_email($user['email'], $subject, $html);
} catch (Throwable $e) {
    // Intentionally do not leak details
}

json_ok(['message' => $genericMsg]);
