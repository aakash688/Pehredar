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
// Use optimized connection pool and caching for faster responses
require_once __DIR__ . '/../shared/optimized_api_helper.php';

// Check license status
require_once __DIR__ . '/../../helpers/license_manager.php';
$licenseStatus = getApplicationLicenseStatus();
if (!$licenseStatus['is_active']) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'error_code' => 'LICENSE_' . strtoupper($licenseStatus['status']),
        'details' => $licenseStatus['reason'] ?? 'License is not active'
    ]);
    exit;
}

use Firebase\JWT\JWT;

// Set content type to JSON
header('Content-Type: application/json');

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}

// Get config
$config = require '../../config.php';

// Check if application is installed
require_once '../../helpers/installation_check.php';
$config = checkInstallation($config);

$jwtSecret = $config['jwt']['secret'];

// Initialize optimized API
$api = getOptimizedAPI(); // This function is in optimized_api_helper.php

// Get the posted data
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->identifier) || !isset($data->password)) {
    sendOptimizedError('Invalid input. Identifier and password are required.', 400);
}

$identifier = $data->identifier;
$password = $data->password;

try {
    // Use optimized client authentication with caching
    $client = $api->getClientAuth($identifier);

    if (!$client) {
        sendOptimizedError('Invalid credentials.', 401);
    }

    // Verify password
    if (hash('sha256', $password . $client['password_salt']) === $client['password_hash']) {

        // --- Successful Login ---
        $user_id = $client['id'];
        $society_id = $client['society_id'];

        // Get cached society details
        $society_info = $api->getSocietyDetails($society_id);

        if (!$society_info) {
            sendOptimizedError('User\'s associated society not found.', 500);
        }
        
        $payload = [
            'iat' => time(),
            'jti' => base64_encode(random_bytes(32)),
            'iss' => $_SERVER['SERVER_NAME'],
            'nbf' => time(),
            'exp' => time() + (60 * 60 * 24), // 24-hour expiration
            'data' => [
                'id' => $user_id,
                'username' => $client['username'],
                'email' => $client['email'],
                'role' => 'Client', // Explicitly set role
                'society' => [
                    'id' => $society_info['id'],
                    'name' => $society_info['society_name']
                ]
            ]
        ];

        $jwt = JWT::encode($payload, $jwtSecret, 'HS256');

        // Return the token and user data (EXACT SAME RESPONSE STRUCTURE)
        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful.',
            'token' => $jwt,
            'user' => [
                'id' => $user_id,
                'name' => $client['name'],
                'email' => $client['email'],
                'username' => $client['username'],
                'is_primary' => $client['is_primary'],
            ]
        ]);

    } else {
        sendOptimizedError('Invalid credentials.', 401);
    }

} catch (Exception $e) {
    sendOptimizedError('An error occurred: ' . $e->getMessage(), 500);
} 