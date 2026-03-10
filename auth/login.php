<?php
require_once __DIR__ . '/../app/auth.php';

require_post();
csrf_verify_or_die();

$email = sanitize_string($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('Email and password are required.');
}
if (!validate_email($email)) {
    json_error('Please enter a valid email address.');
}

$stmt = db()->prepare('SELECT * FROM authors WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid email or password.');
}

if (($user['status'] ?? 'Inactive') !== 'Active') {
    json_error('Account is inactive. Please contact RDC.');
}

login_user($user);
json_ok(['redirect' => role_home_url($user['role'])]);
