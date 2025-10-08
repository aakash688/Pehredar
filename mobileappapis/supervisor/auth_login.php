<?php
// POST /api/supervisor/auth/login
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../helpers/database.php';
$config = require __DIR__ . '/../../config.php';

// Check if application is installed
require_once __DIR__ . '/../../helpers/installation_check.php';
$config = checkInstallation($config);

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

$pdo = get_db_connection();
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['phone']) || empty($data['password'])) {
	http_response_code(400);
	echo json_encode(['error' => 'Phone and password are required.']);
	exit;
}

$stmt = $pdo->prepare("SELECT id, first_name, surname, password, user_type, mobile_access FROM users WHERE mobile_number = ? AND (user_type = 'Supervisor' OR user_type = 'Site Supervisor' OR user_type = 'Area Manager' OR user_type = 'Manager')");
$stmt->execute([$data['phone']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($data['password'], $user['password'])) {
	http_response_code(401);
	echo json_encode(['error' => 'Invalid credentials']);
	exit;
}

if ((int)$user['mobile_access'] !== 1) {
	http_response_code(403);
	echo json_encode(['error' => 'Mobile access is disabled for this account.']);
	exit;
}

$secret_key = $config['jwt']['secret'];
$issuedAt = time();
$expire = $issuedAt + (3600 * 24 * 30);

$token = [
	"iss" => $config['base_url'],
	"aud" => "SUPERVISOR_APP",
	"iat" => $issuedAt,
	"nbf" => $issuedAt,
	"exp" => $expire,
	"data" => [
		"id" => (int)$user['id'],
		"user_type" => $user['user_type']
	]
];

$jwt = JWT::encode($token, $secret_key, 'HS256');

echo json_encode([
	'success' => true,
	'token' => $jwt,
	'expires_in' => $expire,
	'user' => [
		'id' => (int)$user['id'],
		'name' => $user['first_name'] . ' ' . $user['surname'],
		'user_type' => $user['user_type']
	]
]);


