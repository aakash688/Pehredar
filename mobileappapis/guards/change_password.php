<?php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// mobileappapis/guards/change_password.php

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
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../../vendor/autoload.php';
require_once '../../config.php';
require_once '../../helpers/ConnectionPool.php';
$config = require '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// SECURITY: No cache for password operations - extract token manually
function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

try {
	$jwt = getBearerToken();
	if (!$jwt) {
		http_response_code(401);
		echo json_encode(['error' => 'Unauthorized']);
		exit;
	}

	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) {
		http_response_code(403);
		echo json_encode(['error' => 'Forbidden']);
		exit;
	}

	// SECURITY: Use fresh database connection (no cache)
	$pdo = ConnectionPool::getConnection();

	$payload = json_decode(file_get_contents('php://input'), true);
	if (!$payload || empty($payload['current_password']) || empty($payload['new_password'])) {
		http_response_code(400);
		echo json_encode(['error' => 'current_password and new_password are required']);
		exit;
	}

	if (strlen($payload['new_password']) < 6) {
		http_response_code(400);
		echo json_encode(['error' => 'Password must be at least 6 characters']);
		exit;
	}

	// SECURITY: Always get fresh password from database (NO CACHE) for security
	$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND user_type = 'Guard' LIMIT 1");
	$stmt->execute([$userId]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$user) {
		http_response_code(404);
		echo json_encode(['error' => 'User not found']);
		exit;
	}

	if (!password_verify($payload['current_password'], $user['password'])) {
		http_response_code(401);
		echo json_encode(['error' => 'Current password is incorrect']);
		exit;
	}

	// Update password with fresh database write
	$newHash = password_hash($payload['new_password'], PASSWORD_DEFAULT);
	$upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
	$upd->execute([$newHash, $userId]);

	// SECURITY: No cache clearing needed since we don't use cache for auth
	// Password changes are immediately effective

	http_response_code(200);
	echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
} catch (Throwable $e) {
	error_log("Change Password API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
	http_response_code(500);
	echo json_encode(['error' => 'An error occurred. Please try again.']);
}


