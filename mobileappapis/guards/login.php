<?php
// mobileappapis/guards/login.php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../vendor/autoload.php';
require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';

use Firebase\JWT\JWT;

$config = require '../../config.php';

// Check if application is installed
require_once '../../helpers/installation_check.php';

// Check license status
require_once '../../helpers/license_manager.php';
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
$config = checkInstallation($config);

// Initialize optimized API
$api = getOptimizedGuardAPI();

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->identifier) || !isset($data->password)) {
    sendOptimizedGuardError('Invalid input. Identifier (email or mobile) and password are required.', 400);
}

$identifier = $data->identifier;
$password = $data->password;

try {
    // SECURITY: Use fresh database lookup for authentication (NO CACHE)
    $pdo = ConnectionPool::getConnection();
    $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email_id' : 'mobile_number';
    
    $stmt = $pdo->prepare("SELECT id, first_name, surname, password, user_type, mobile_number, 
                           email_id, mobile_access 
                    FROM users 
                    WHERE $field = ? AND user_type = 'Guard' 
                    LIMIT 1");
    $stmt->execute([$identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendOptimizedGuardError('User not found. Please check your email/phone number.', 401);
    } else if (!password_verify($password, $user['password'])) {
        sendOptimizedGuardError('Wrong password. Please try again with correct password.', 401);
    } else if ($user['mobile_access'] != 1) {
        sendOptimizedGuardError('Mobile access is disabled for this account.', 403);
    } else {
        // User exists, password is correct, and mobile access is enabled
        $secret_key = $config['jwt']['secret'];
        $issuer_claim = $config['base_url'];
        $audience_claim = "THE_AUDIENCE";
        $issuedat_claim = time();
        $notbefore_claim = $issuedat_claim;
        $expire_claim = $issuedat_claim + (3600 * 24 * 30); // 30 days expiry

        $token = [
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => [
                "id" => $user['id'],
                "user_type" => $user['user_type']
            ]
        ];

        $jwt = JWT::encode($token, $secret_key, 'HS256');

        // Send optimized response with compression
        sendOptimizedGuardResponse([
            'success' => true,
            'message' => 'Successful login.',
            'token' => $jwt,
            'user' => [
                'id' => (int)$user['id'],
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? '')),
                'user_type' => $user['user_type'],
                'email' => $user['email_id'] ?? null,
                'mobile' => $user['mobile_number'] ?? null,
            ]
        ], 200);
    }

} catch (Exception $e) {
    error_log('Guard login error: ' . $e->getMessage());
    sendOptimizedGuardError('An error occurred during login.', 500);
} 