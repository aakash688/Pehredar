<?php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// mobileappapis/guards/profile_photo_update.php

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
header("Access-Control-Allow-Headers: Authorization, Content-Type");

require_once '../../vendor/autoload.php';
require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';
$config = require '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;



try {
	$jwt = getOptimizedBearerToken();
	if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

	if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
		sendOptimizedGuardError('Photo file is required', 400);
	}

	$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
	$type = mime_content_type($_FILES['photo']['tmp_name']);
	if (!isset($allowed[$type])) { sendOptimizedGuardError('Unsupported file type', 400); }

	$ext = $allowed[$type];
	$uploadsDir = realpath(__DIR__ . '/../../uploads');
	if ($uploadsDir === false) { $uploadsDir = __DIR__ . '/../../uploads'; }
	if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0775, true); }

	$filename = sprintf('user_%d_profile_photo_%s.%s', $userId, uniqid(), $ext);
	$destPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
	if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
		sendOptimizedGuardError('Failed to save file', 500);
	}

	$relativePath = 'uploads/' . $filename;
	// Initialize optimized API and persist change
	$api = getOptimizedGuardAPI();
	$pdo = ConnectionPool::getConnection();
	$upd = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
	$upd->execute([$relativePath, $userId]);
	// Invalidate related caches
	if (method_exists($api, 'clearGuardCache')) {
		$api->clearGuardCache($userId);
	}

	$baseUrl = rtrim($config['base_url'] ?? '', '/');
	$absoluteUrl = $baseUrl . '/' . $relativePath;

	sendOptimizedGuardResponse(['success' => true, 'profile_photo_url' => $absoluteUrl]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}


