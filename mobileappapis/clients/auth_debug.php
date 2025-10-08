<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log all headers for debugging
$headers = getallheaders();
$headerLog = [];
foreach ($headers as $key => $value) {
    $headerLog[$key] = $value;
}

// Check for Authorization header
$authHeader = null;
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
} elseif (isset($headers['authorization'])) {
    $authHeader = $headers['authorization'];
}

// Extract JWT token if present
$token = null;
if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
}

// Prepare response
$response = [
    'success' => true,
    'message' => 'Authentication debug information',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'headers_received' => $headerLog,
    'auth_header_found' => $authHeader !== null,
    'token_extracted' => $token,
    'server_variables' => [
        'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'Not set',
    ],
];

// Output as JSON
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT); 