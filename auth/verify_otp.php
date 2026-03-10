<?php
require_once __DIR__ . '/../app/auth.php';

start_session();

require_post();
csrf_verify_or_die();

$email = sanitize_string($_POST['email'] ?? '');
$otp = sanitize_string($_POST['otp'] ?? '');

if (!$email || !validate_email($email)) {
    json_error('Invalid email address.', 400, ['email' => 'Invalid email']);
}

if (!$otp || strlen($otp) !== 6 || !ctype_digit($otp)) {
    json_error('OTP must be 6 digits.', 400, ['otp' => 'Invalid OTP format']);
}

try {
    // Find the most recent OTP for this email
    $stmt = db()->prepare('
        SELECT id, otp_code, otp_hash, verified_at, expires_at, attempts, max_attempts
        FROM email_verifications
        WHERE email = ?
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$email]);
    $verification = $stmt->fetch();

    if (!$verification) {
        json_error('No verification request found for this email.', 400, ['email' => 'No OTP sent']);
    }

    // Check if already verified
    if ($verification['verified_at']) {
        json_error('This email has already been verified.', 400, ['email' => 'Already verified']);
    }

    // Check if expired
    if (strtotime($verification['expires_at']) < time()) {
        json_error('OTP has expired. Request a new one.', 400, ['otp' => 'OTP expired']);
    }

    // Check attempts
    if ($verification['attempts'] >= $verification['max_attempts']) {
        json_error('Too many failed attempts. Request a new OTP.', 429, ['otp' => 'Too many attempts']);
    }

    // Verify OTP
    if (!password_verify($otp, $verification['otp_hash'])) {
        // Increment attempts
        db()->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?')
            ->execute([$verification['id']]);
        
        $remaining = $verification['max_attempts'] - $verification['attempts'] - 1;
        if ($remaining > 0) {
            json_error("Invalid OTP. $remaining attempts remaining.", 400, ['otp' => 'Invalid OTP']);
        } else {
            json_error('Too many failed attempts. Request a new OTP.', 429, ['otp' => 'Too many attempts']);
        }
    }

    // Mark as verified
    $verified_at = date('Y-m-d H:i:s');
    db()->prepare('UPDATE email_verifications SET verified_at = ? WHERE id = ?')
        ->execute([$verified_at, $verification['id']]);

    // Generate a temporary verification token for the registration step
    $verification_token = bin2hex(random_bytes(32));
    $token_hash = password_hash($verification_token, PASSWORD_DEFAULT);
    
    // Store it temporarily (we'll just use the email + timestamp to keep it simple)
    $_SESSION['_verified_email'] = $email;
    $_SESSION['_verified_email_time'] = time();

    json_ok([
        'message' => 'Email verified successfully.',
        'email' => $email,
        'verification_token' => $verification_token,
    ]);

} catch (Throwable $e) {
    error_log('OTP verification error: ' . $e->getMessage());
    json_error('Verification failed.', 500);
}
