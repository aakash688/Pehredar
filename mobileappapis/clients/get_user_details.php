<?php
// Include comprehensive CORS configuration
require_once __DIR__ . '/../shared/cors_config.php';

// Set CORS headers for mobile app compatibility
setMobileAppCorsHeaders();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../../config.php';
$dbConfig = $config['db'];
$jwtSecret = $config['jwt']['secret'];

$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
try {
    // Use optimized connection pool to solve "max connections per hour" issue
require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';

$pdo = get_api_db_connection_safe();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

// A robust way to get the Authorization header
$authHeader = null;
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    // Make the check case-insensitive
    $allHeaders = array_change_key_case($allHeaders, CASE_LOWER);
    if (isset($allHeaders['authorization'])) {
        $authHeader = $allHeaders['authorization'];
    }
}

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header not received. Check server configuration.']);
    exit();
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['error' => 'JWT not found in Bearer token.']);
    exit();
}

try {
    $decoded = JWT::decode($jwt, new Key($jwtSecret, 'HS256'));
    $userData = $decoded->data;

    // Use the user ID from the token payload to fetch details
    $userId = $userData->id;

    // Get the user's details
    $stmt = $pdo->prepare("SELECT id, society_id, name, phone, email, username, is_primary, created_at FROM clients_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit();
    }

    http_response_code(200);
    echo json_encode($user);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
} 