<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

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

    // The society ID is now directly available in the token payload
    $societyId = $userData->society->id;

    if (!$societyId) {
        http_response_code(404);
        echo json_encode(['error' => 'Society not found for this user in token.']);
        exit;
    }

    // Fetch Client Details using the ID from the token
    $stmt = $pdo->prepare("SELECT * FROM society_onboarding_data WHERE id = :id");
    $stmt->execute([':id' => $societyId]);
    $society = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($society) {
        http_response_code(200);
        echo json_encode($society);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Client Details not found.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
} 