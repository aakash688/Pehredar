<?php
// mobileappapis/guards/profile_get.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../../vendor/autoload.php';
require_once '../../helpers/database.php';
$config = require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_bearer_token(): ?string {
	$headers = function_exists('getallheaders') ? getallheaders() : [];
	$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
	if (stripos($auth, 'Bearer ') === 0) {
		return trim(substr($auth, 7));
	}
	return null;
}

try {
	$jwt = get_bearer_token();
	if (!$jwt) {
		http_response_code(401);
		echo json_encode(['error' => 'Unauthorized: Missing Bearer token']);
		exit;
	}

	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;

	if ($userId <= 0 || !in_array($userType, ['Guard', 'guard'], true)) {
		http_response_code(403);
		echo json_encode(['error' => 'Forbidden: Guard role required']);
		exit;
	}

	$pdo = get_db_connection();

	$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'Guard' LIMIT 1");
	$stmt->execute([$userId]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$user) {
		http_response_code(404);
		echo json_encode(['error' => 'User not found']);
		exit;
	}

	// Remove sensitive fields
	unset($user['password']);

	$baseUrl = rtrim($config['base_url'] ?? '', '/');
	$makeUrl = function ($path) use ($baseUrl) {
		if (empty($path)) { return null; }
		if (filter_var($path, FILTER_VALIDATE_URL)) { return $path; }
		return $baseUrl . '/' . ltrim($path, '/');
	};

	// Normalize fields and build URLs
	$profile = [
		'id' => (int)$user['id'],
		'first_name' => $user['first_name'] ?? null,
		'surname' => $user['surname'] ?? null,
		'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? '')),
		'user_type' => $user['user_type'] ?? null,
		'gender' => $user['gender'] ?? null,
		'date_of_birth' => $user['date_of_birth'] ?? null,
		'date_of_joining' => $user['date_of_joining'] ?? null,
		'mobile_number' => $user['mobile_number'] ?? null,
		'email_id' => $user['email_id'] ?? null,
		'address' => $user['address'] ?? null,
		'permanent_address' => $user['permanent_address'] ?? null,
		'salary' => isset($user['salary']) ? (float)$user['salary'] : null,
		'bank_account_number' => $user['bank_account_number'] ?? null,
		'ifsc_code' => $user['ifsc_code'] ?? null,
		'bank_name' => $user['bank_name'] ?? null,
		'profile_photo_url' => $makeUrl($user['profile_photo'] ?? null),
		'documents' => [
			'aadhar_card' => $makeUrl($user['aadhar_card_scan'] ?? null),
			'pan_card' => $makeUrl($user['pan_card_scan'] ?? null),
			'bank_passbook' => $makeUrl($user['bank_passbook_scan'] ?? null),
			'police_verification' => $makeUrl($user['police_verification_document'] ?? null),
			'ration_card' => $makeUrl($user['ration_card_scan'] ?? null),
			'light_bill' => $makeUrl($user['light_bill_scan'] ?? null),
			'voter_id' => $makeUrl($user['voter_id_scan'] ?? null),
			'passport' => $makeUrl($user['passport_scan'] ?? null),
		],
	];

	// Remove null document entries
	$profile['documents'] = array_filter($profile['documents']);

	echo json_encode(['success' => true, 'user' => $profile]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}


