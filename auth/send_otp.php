<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/mailer.php';

require_post();
csrf_verify_or_die();

$email = sanitize_string($_POST['email'] ?? '');

if (!$email || !validate_email($email)) {
    json_error('Please enter a valid email address.', 400, ['email' => 'Invalid email address']);
}

// Check if email is already registered
$stmt = db()->prepare('SELECT id FROM authors WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_error('This email is already registered.', 400, ['email' => 'Email already in use']);
}

try {
    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + 10 * 60); // Expires in 10 minutes

    // Clear any existing unverified codes for this email
    db()->prepare('DELETE FROM email_verifications WHERE email = ? AND verified_at IS NULL')
        ->execute([$email]);

    // Store the OTP
    $stmt = db()->prepare('
        INSERT INTO email_verifications (email, otp_code, otp_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$email, $otp, $otp_hash, $expires_at]);

    // Send OTP via email
    $subject = 'Your OTP for UCC – RDC ISSN Portal Registration';
    $htmlBody = <<<HTML
<html>
<body style="font-family: Arial, sans-serif; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Email Verification</h2>
        <p>Your One-Time Password (OTP) for registration is:</p>
        <div style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0;">
            <h1 style="letter-spacing: 3px; color: #0f6b3a; font-size: 32px; margin: 0;">$otp</h1>
        </div>
        <p>This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
        <p>If you didn't request this, you can safely ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        <p style="color: #999; font-size: 12px;">UCC – RDC ISSN Portal</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Your OTP for registration: $otp\n\nThis OTP is valid for 10 minutes.";

    $sent = send_email($email, $subject, $htmlBody, $textBody);

    if (!$sent) {
        $error_msg = 'Failed to send verification email to: ' . $email;
        error_log($error_msg);
        
        // Log to a separate file for better visibility
        $log_file = __DIR__ . '/../storage/otp_errors.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $error_msg . PHP_EOL, FILE_APPEND);
        
        json_error('Failed to send verification email. Please try again. Check that SMTP is configured correctly.', 500);
    }

    json_ok([
        'message' => 'OTP has been sent to your email address.',
        'email' => $email,
        'expires_in' => 600, // 10 minutes in seconds
    ]);

} catch (Throwable $e) {
    error_log('OTP generation error: ' . $e->getMessage());
    json_error('Failed to send verification email.', 500);
}
