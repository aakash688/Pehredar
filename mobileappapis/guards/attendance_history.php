<?php
// mobileappapis/guards/attendance_history.php
// OPTIMIZED: Uses connection pooling and faster responses

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization');

require_once '../../vendor/autoload.php';
require_once '../../config.php';
require_once '../../helpers/ConnectionPool.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../../config.php';

function getOptimizedBearerToken(): ?string {
	$headers = function_exists('getallheaders') ? getallheaders() : [];
	$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
	if (stripos($auth, 'Bearer ') === 0) { return trim(substr($auth, 7)); }
	return null;
}

function sendOptimizedGuardResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendOptimizedGuardError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

try {
	$jwt = getOptimizedBearerToken();
	if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

	// Get database connection
	$pdo = ConnectionPool::getConnection();

	$page = max(1, (int)($_GET['page'] ?? 1));
	$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
	$offset = ($page - 1) * $limit;

	$filterCode = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : null;
	$filterDate = isset($_GET['date']) ? trim($_GET['date']) : null;
	$filterDateStart = isset($_GET['date_start']) ? trim($_GET['date_start']) : null;
	$filterDateEnd = isset($_GET['date_end']) ? trim($_GET['date_end']) : null;

	// Build WHERE clause
	$where = ['a.user_id = ?'];
	$params = [$userId];

	if ($filterCode) {
		$where[] = 'am.code = ?';
		$params[] = $filterCode;
	}

		if ($filterDate) {
		$where[] = 'a.attendance_date = ?';
		$params[] = $filterDate;
	}

	if ($filterDateStart && $filterDateEnd) {
		$where[] = 'a.attendance_date BETWEEN ? AND ?';
		$params[] = $filterDateStart;
		$params[] = $filterDateEnd;
	}

	$whereClause = implode(' AND ', $where);

	// Get attendance records
	$sql = "SELECT a.*, am.code, am.description as status_description, am.multiplier,
			   a.attendance_date as date_display,
			   a.shift_start as check_in_display,
			   a.shift_end as check_out_display,
			   s.society_name, sh.shift_name
		FROM attendance a
		LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
		LEFT JOIN society_onboarding_data s ON a.society_id = s.id
		LEFT JOIN shift_master sh ON a.shift_id = sh.id
		WHERE $whereClause
		ORDER BY a.attendance_date DESC, a.id DESC
		LIMIT ? OFFSET ?";

	$params[] = $limit;
	$params[] = $offset;

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$items = [];
	foreach ($attendance as $r) {
		$inTime = $r['check_in_display'] ?? null;
		$outTime = $r['check_out_display'] ?? null;
		$durationMin = null;
		if (!empty($inTime) && !empty($outTime)) {
			$durationMin = (int) round((strtotime($outTime) - strtotime($inTime)) / 60);
		}
		$items[] = [
			'id' => (int)$r['id'],
			'location' => $r['society_name'] ?? 'Unknown',
			'check_in' => $inTime,
			'check_out' => $outTime,
			'is_active' => empty($outTime),
			'date' => $r['date_display'],
			'shift_name' => $r['shift_name'] ?? 'Unknown',
			'attendance_code' => $r['code'] ?? 'N/A',
			'duration_min' => $durationMin,
		];
	}

	// Send response
	sendOptimizedGuardResponse(['success' => true, 'page' => $page, 'limit' => $limit, 'items' => $items]);
	
} catch (Throwable $e) {
	error_log("Attendance History API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
	sendOptimizedGuardError('Server error: ' . $e->getMessage(), 500);
}