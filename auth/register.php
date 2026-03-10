<?php
require_once __DIR__ . '/../app/auth.php';

start_session();

require_post();
csrf_verify_or_die();

$fields = [
    'last_name' => sanitize_string($_POST['last_name'] ?? ''),
    'first_name' => sanitize_string($_POST['first_name'] ?? ''),
    'email' => sanitize_string($_POST['email'] ?? ''),
    'contact_number' => sanitize_string($_POST['contact_number'] ?? ''),
    'course' => sanitize_string($_POST['course'] ?? ''),
    'address' => sanitize_string($_POST['address'] ?? ''),
    'postal_code' => sanitize_string($_POST['postal_code'] ?? ''),
    'region' => sanitize_string($_POST['region'] ?? ''),
];

$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

// Verify email was verified via OTP (session-based)
$verified_email = $_SESSION['_verified_email'] ?? '';
$verified_time = $_SESSION['_verified_email_time'] ?? 0;
$is_email_verified = ($verified_email === $fields['email'] && (time() - $verified_time) < 3600); // 1 hour validity

$errors = [];

// Check email verification first
if (!$is_email_verified) {
    $errors['email'] = 'Please verify your email with OTP first.';
}

foreach (['last_name','first_name','email','contact_number','address','postal_code','region'] as $required) {
    if ($fields[$required] === '') {
        $errors[$required] = ucfirst(str_replace('_',' ', $required)) . ' is required.';
    }
}

if ($fields['email'] !== '' && !validate_email($fields['email'])) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($password === '') {
    $errors['password'] = 'Password is required.';
}
if ($password !== '' && strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}
if ($password !== $confirm) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!isset($_FILES['valid_id'])) {
    $errors['valid_id'] = 'Valid ID image is required.';
}

// Email uniqueness check
if ($fields['email'] !== '') {
    $stmt = db()->prepare('SELECT id FROM authors WHERE email = ? LIMIT 1');
    $stmt->execute([$fields['email']]);
    if ($stmt->fetch()) {
        $errors['email'] = 'Email is already registered.';
    }
}

if ($errors) {
    json_error('Please fix the errors and try again.', 422, $errors);
}

$config = app_config();
$uploads = $config['uploads'];

try {
    $file = $_FILES['valid_id'];

    // Extra validation: ensure it is an image
    if (($file['tmp_name'] ?? '') !== '' && @getimagesize($file['tmp_name']) === false) {
        json_error('Valid ID must be an image file (JPG/PNG).', 422, ['valid_id' => 'Valid ID must be an image file (JPG/PNG).']);
    }

    $validIdPath = move_uploaded_file_validated(
        $file,
        $uploads['valid_id_dir'],
        $uploads['valid_id_extensions'],
        $uploads['max_valid_id_bytes']
    );

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare('INSERT INTO authors
        (last_name, first_name, email, contact_number, course, address, postal_code, region, valid_id_path, password_hash, status, role)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Active", "Author")
    ');

    $stmt->execute([
        $fields['last_name'],
        $fields['first_name'],
        $fields['email'],
        $fields['contact_number'],
        $fields['course'] !== '' ? $fields['course'] : null,
        $fields['address'],
        $fields['postal_code'],
        $fields['region'],
        $validIdPath,
        $hash,
    ]);

    $id = (int)db()->lastInsertId();

// Registration successful (DO NOT login automatically)
json_ok([
    'message' => 'Registration successful. You can now login.'
]);
} catch (Throwable $e) {
    json_error('Registration failed. Please try again.');
}
