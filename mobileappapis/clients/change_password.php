<?php
// Include comprehensive CORS configuration
require_once __DIR__ . '/../shared/cors_config.php';

// Set CORS headers for mobile app compatibility
setMobileAppCorsHeaders();

require_once '../../vendor/autoload.php';
require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../../config.php';
$dbConfig = $config['db'];
$jwtSecret = $config['jwt']['secret'];

// Use the same optimized API helper as login.php
require_once __DIR__ . '/../shared/optimized_api_helper.php';

try {
    $api = getOptimizedAPI(); // This function is in optimized_api_helper.php
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize API: ' . $e->getMessage()]);
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
    echo json_encode(['error' => 'Authorization header not received. Please log in again.']);
    exit();
}

// Extract JWT token - handle both "Bearer TOKEN" and direct token formats
$jwt = null;
if (strpos($authHeader, 'Bearer ') === 0) {
    // Standard format: "Bearer TOKEN"
    $jwt = substr($authHeader, 7);
} else {
    // Direct token format (for compatibility)
    $jwt = $authHeader;
}

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid authorization format.']);
    exit();
}

try {
    $decoded = JWT::decode($jwt, new Key($jwtSecret, 'HS256'));
    $userId = $decoded->data->id;
    
    error_log("Change Password Debug: Authorization header validated - User ID: " . $userId);
    
} catch (Exception $e) {
    error_log("Change Password Debug: JWT decode error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode(['error' => 'Invalid session. Please log in again.']);
    exit();
}

// Get the request body
$requestBody = file_get_contents("php://input");
$data = json_decode($requestBody, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data received']);
    exit();
}

error_log("Change Password Debug: Received data keys: " . implode(', ', array_keys($data)));

// Get new password from request body
if (!isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'New password is required.']);
    exit();
}

$newPassword = $data['new_password'];

// Password change logic
try {
    // Validate new password
    if (empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'New password cannot be empty.']);
        exit();
    }
    
    if (strlen($newPassword) < 6) {
        http_response_code(422);
        echo json_encode(['error' => 'New password must be at least 6 characters long.']);
        exit();
    }

    // Get user from DB using optimized API
    $user = $api->getClientById($userId);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found. Please log in again.']);
        exit();
    }

    error_log("Change Password Debug: User found in database - ID: " . $user['id']);

    // Check if new password is different from current password
    $new_password_hash = hash('sha256', $newPassword . $user['password_salt']);
    if ($new_password_hash === $user['password_hash']) {
        http_response_code(422);
        echo json_encode(['error' => 'New password must be different from current password.']);
        exit();
    }

    error_log("Change Password Debug: New password validation passed");

    // Generate new password hash
    $new_salt = bin2hex(random_bytes(16));
    $new_password_hash = hash('sha256', $newPassword . $new_salt);

    // Update password using optimized API
    $result = $api->updateClientPassword($userId, $new_password_hash, $new_salt);

    if ($result) {
        error_log("Change Password Debug: Password updated successfully for user ID: " . $userId);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully.',
            'timestamp' => time()
        ]);
    } else {
        error_log("Change Password Debug: Failed to update password in database");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update password. Please try again.']);
    }

} catch (Exception $e) {
    error_log("Change Password Debug: Exception in password change logic: " . $e->getMessage());
    error_log("Change Password Debug: Exception trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred. Please try again later.']);
} 