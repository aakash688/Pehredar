<?php
// POST /api/supervisor/auth/logout
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/api_helpers.php';

// Decode token to get jti (if present) and expiry; if no jti then derive hash of token as jti
$config = require __DIR__ . '/../../config.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('getallheaders') ? (array_change_key_case(getallheaders(), CASE_UPPER)['AUTHORIZATION'] ?? null) : null);
if (!$authHeader) {
	sup_send_error_response('Authorization header not found.', 401);
}
list($jwt) = sscanf($authHeader, 'Bearer %s');
if (!$jwt) {
	sup_send_error_response('Malformed authorization header.', 401);
}

// decode without verifying to read exp (best-effort)
try {
	$parts = explode('.', $jwt);
	$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
	$exp = isset($payload['exp']) ? date('Y-m-d H:i:s', (int)$payload['exp']) : date('Y-m-d H:i:s', time() + 3600);
} catch (Exception $e) {
	$exp = date('Y-m-d H:i:s', time() + 3600);
}

$pdo = sup_get_db();
$stmt = $pdo->prepare('INSERT INTO jwt_blacklist (jti, expires_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)');
$stmt->execute([hash('sha256', $jwt), $exp]);

echo json_encode(['success' => true]);







