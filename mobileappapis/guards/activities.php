<?php
// mobileappapis/guards/activities.php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// GET: List activities visible to a Guard based on roster-assigned locations

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

require_once '../../vendor/autoload.php';
require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../../config.php';

try {
	$jwt = getOptimizedBearerToken();
	if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

	// Initialize optimized API
	$api = getOptimizedGuardAPI();

	// Pagination & filters
	$page = max(1, (int)($_GET['page'] ?? 1));
	$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
	$dateStart = isset($_GET['date_start']) ? trim($_GET['date_start']) : null; // YYYY-MM-DD
	$dateEnd = isset($_GET['date_end']) ? trim($_GET['date_end']) : null; // YYYY-MM-DD
	$status = isset($_GET['status']) ? trim($_GET['status']) : null; // Upcoming/Ongoing/Completed
	$q = isset($_GET['q']) ? trim($_GET['q']) : null;

	// Build filters array
	$filters = [];
	if ($dateStart) $filters['date_start'] = $dateStart;
	if ($dateEnd) $filters['date_end'] = $dateEnd;
	if ($status) $filters['status'] = $status;
	if ($q) $filters['q'] = $q;

	// Use optimized activities method with caching
	$result = $api->getGuardActivities($userId, $page, $limit, $filters);
	$activities = $result['activities'];

	// Get activity photos in batch for performance
	$pdo = ConnectionPool::getConnection();
	$ids = array_map(fn($r) => (int)$r['id'], $activities);
	$photosByActivity = [];
	if (!empty($ids)) {
		$in = implode(',', array_fill(0, count($ids), '?'));
		$qp = $pdo->prepare("SELECT activity_id, image_url FROM activity_photos WHERE activity_id IN ($in) ORDER BY created_at DESC, id DESC");
		$qp->execute($ids);
		while ($pr = $qp->fetch(PDO::FETCH_ASSOC)) {
			$aid = (int)$pr['activity_id'];
			if (!isset($photosByActivity[$aid])) { $photosByActivity[$aid] = []; }
			$photosByActivity[$aid][] = $pr['image_url'];
		}
	}

	$items = [];
	$baseUrl = rtrim($config['base_url'] ?? '', '/');
	foreach ($activities as $r) {
		$aid = (int)$r['id'];
		$imgs = $photosByActivity[$aid] ?? [];
		// Normalize to full URLs using base_url when stored as relative path
		$imgsFull = array_map(function($p) use ($baseUrl) {
			if (!$p) return $p;
			if (stripos($p, 'http://') === 0 || stripos($p, 'https://') === 0) { return $p; }
			return $baseUrl . '/' . ltrim($p, '/');
		}, $imgs);
		
		// Check if assigned to current user
		$assignedToMe = false;
		if (isset($r['assigned_to_me'])) {
			$assignedToMe = (bool)$r['assigned_to_me'];
		} else {
			// Fallback check if not in cached result
			$checkStmt = $pdo->prepare("SELECT 1 FROM activity_assignees WHERE activity_id = ? AND user_id = ? LIMIT 1");
			$checkStmt->execute([$aid, $userId]);
			$assignedToMe = $checkStmt->fetchColumn() !== false;
		}
		
		$items[] = [
			'id' => $aid,
			'title' => $r['title'],
			'description' => $r['description'],
			'scheduled_date' => $r['scheduled_date'],
			'status' => $r['computed_status'] ?? $r['status'],
			'society_id' => (int)$r['society_id'],
			'society_name' => $r['society_name'],
			'assigned_to_me' => $assignedToMe,
			'latest_images' => array_slice($imgsFull, 0, 3),
			'images_count' => count($imgsFull),
		];
	}

	// Send optimized response with compression
	sendOptimizedGuardResponse([
		'success' => true,
		'page' => $page,
		'limit' => $limit,
		'items' => $items,
	]);
} catch (Throwable $e) {
	error_log("Activities API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
	sendOptimizedGuardError('An error occurred while fetching activities', 500);
}


