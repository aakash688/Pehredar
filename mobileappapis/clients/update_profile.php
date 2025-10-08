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
    $userId = $decoded->data->userId;

    // Get the posted data
    $data = json_decode(file_get_contents("php://input"));

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input.']);
        exit();
    }

    // Fetch current user data
    $stmt = $pdo->prepare("SELECT name, email, phone FROM clients_users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit();
    }
    
    // Build the update query
    $fields = [];
    $params = [];

    if (isset($data->name) && $data->name !== $currentUser['name']) {
        $fields[] = 'name = ?';
        $params[] = $data->name;
    }
    if (isset($data->email) && $data->email !== $currentUser['email']) {
        $fields[] = 'email = ?';
        $params[] = $data->email;
    }
    if (isset($data->phone) && $data->phone !== $currentUser['phone']) {
        $fields[] = 'phone = ?';
        $params[] = $data->phone;
    }
    
    if (empty($fields)) {
        http_response_code(200);
        echo json_encode(['message' => 'No changes to update.']);
        exit();
    }

    $params[] = $userId;
    $sql = "UPDATE clients_users SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated user data to return
    $stmt = $pdo->prepare("SELECT id, name, username, email, phone, is_primary FROM clients_users WHERE id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'message' => 'Profile updated successfully.',
        'user' => $updatedUser
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
} 