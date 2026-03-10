<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Return municipalities for a given region as JSON
$region = sanitize_string($_GET['region'] ?? '');

if (!$region) {
    json_error('Region parameter is required.', 400);
}

try {
    $stmt = db()->prepare('
        SELECT id, name, postal_code 
        FROM municipalities 
        WHERE region = ? AND is_active = 1 
        ORDER BY name ASC
    ');
    $stmt->execute([$region]);
    $municipalities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    json_ok(['data' => $municipalities]);
} catch (Throwable $e) {
    // Debug: log the actual error
    error_log('Municipality fetch error: ' . $e->getMessage());
    json_error('Failed to fetch municipalities: ' . $e->getMessage(), 500);
}
