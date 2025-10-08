<?php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// mobileappapis/guards/reset_password.php

header("Content-Type: application/json; charset=UTF-8");

// CORS headers - Allow from any origin for mobile app compatibility
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';
$config = require '../../config.php';

// The following is a simplified reset password script.
// It assumes the user is already authenticated to be able to change their password,
// or an admin is performing this action.
// A full implementation would require a token-based flow (e.g., email with a reset link).

// Initialize optimized API\n\t$api = getOptimizedGuardAPI();\n\t$pdo = ConnectionPool::getConnection(); // Fallback for complex queries
$data = json_decode(file_get_contents("php://input"));

// We'll need a way to identify the user.
// For this simple case, we'll expect a user_id.
// A more secure version could get the user_id from a valid JWT.
if (!$data || !isset($data->user_id) || !isset($data->new_password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. user_id and new_password are required.']);
    exit;
}

$userId = $data->user_id;
$newPassword = $data->new_password;

// It's good practice to have some validation on the password strength
if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters long.']);
    exit;
}

// Hash the new password
$password_hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password in the database
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

if ($stmt->execute([$password_hash, $userId])) {
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        sendOptimizedGuardResponse(['success' => true, 'message' => 'Password has been reset successfully.']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found or password could not be updated.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while resetting the password.']);
} 