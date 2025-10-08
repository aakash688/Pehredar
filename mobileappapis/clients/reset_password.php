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

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->reset_token) || !isset($data->new_password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Reset token and new password are required.']);
    exit();
}

$resetToken = $data->reset_token;
$newPassword = $data->new_password;

try {
    // Decode the token
    $decoded = JWT::decode($resetToken, new Key($jwtSecret, 'HS256'));

    // Verify token purpose
    if (!isset($decoded->data->type) || $decoded->data->type !== 'password_reset') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token type.']);
        exit();
    }

    $userId = $decoded->data->userId;

    // Generate new salt and hash for the password
    $salt = bin2hex(random_bytes(16));
    $password_hash = hash('sha256', $newPassword . $salt);

    // Update the password in the database
    $stmt = $pdo->prepare("UPDATE clients_users SET password_hash = ?, password_salt = ? WHERE id = ?");
    $stmt->execute([$password_hash, $salt, $userId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['message' => 'Password has been reset successfully.']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found or password could not be updated.']);
    }

} catch (Exception $e) {
    // This will catch expired tokens, invalid signatures etc.
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token: ' . $e->getMessage()]);
} 