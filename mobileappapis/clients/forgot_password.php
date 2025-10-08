<?php
require_once '../../vendor/autoload.php';
require_once '../../config.php';

use Firebase\JWT\JWT;

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
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
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

if (!$data || !isset($data->email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Email is required.']);
    exit();
}

$email = $data->email;

try {
    $stmt = $pdo->prepare("SELECT id, email FROM clients_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // We send a success-like message to prevent user enumeration
        http_response_code(200);
        echo json_encode(['message' => 'If a user with that email exists, a password reset link has been sent.']);
        exit();
    }

    // Generate a short-lived JWT for password reset
    $issuedAt   = time();
    $expire     = $issuedAt + 600; // 10 minutes expiration
    $serverName = $_SERVER['SERVER_NAME'];

    $tokenData = [
        'iat'  => $issuedAt,
        'iss'  => $serverName,
        'exp'  => $expire,
        'data' => [
            'userId' => $user['id'],
            'type' => 'password_reset' // Custom claim to identify token purpose
        ]
    ];

    $resetToken = JWT::encode($tokenData, $jwtSecret, 'HS256');

    // In a real application, you would email this token to the user.
    // For this implementation, we will return it in the response.
    // NOTE: This is insecure and for development purposes only.
    http_response_code(200);
    echo json_encode([
        'message' => 'Password reset token generated.',
        'reset_token' => $resetToken
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
} 