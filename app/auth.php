<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function login_user(array $userRow): void
{
    start_session();
    // Keep session payload small.
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'email' => $userRow['email'],
        'first_name' => $userRow['first_name'],
        'last_name' => $userRow['last_name'],
        'role' => $userRow['role'],
        'status' => $userRow['status'],
    ];
}

function logout_user(): void
{
    start_session();
    unset($_SESSION['user']);
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /uccrdc/index.php');
        exit;
    }
}

function require_role(array $allowedRoles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function role_home_url(string $role): string
{
    return match ($role) {
        'Admin' => '/uccrdc/admin/dashboard.php',
        'Staff' => '/uccrdc/staff/dashboard.php',
        default => '/uccrdc/author/dashboard.php',
    };
}
