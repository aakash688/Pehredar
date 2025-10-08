<?php
require_once '../../vendor/autoload.php';
require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}

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

// Get Authorization header
$authHeader = null;
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    $allHeaders = array_change_key_case($allHeaders, CASE_LOWER);
    if (isset($allHeaders['authorization'])) {
        $authHeader = $allHeaders['authorization'];
    }
}

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header not received.']);
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
    $userId = $decoded->data->userId;

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->fcm_token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. fcm_token is required.']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE clients_users SET fcm_token = ? WHERE id = ?");
    $stmt->execute([$data->fcm_token, $userId]);

    http_response_code(200);
    echo json_encode(['message' => 'FCM token updated successfully.']);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
} 