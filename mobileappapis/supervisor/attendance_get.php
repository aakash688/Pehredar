<?php
// GET /api/supervisor/attendance?from=YYYY-MM-DD&to=YYYY-MM-DD&employee_id=ID
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$params = [];
$where = ['1=1'];

// Only own history if employee_id = self or filter by supervised team
if (!empty($_GET['employee_id'])) {
	$where[] = 'ar.employee_id = ?';
	$params[] = (int)$_GET['employee_id'];
} else {
	$where[] = 'ar.marked_by = ?';
	$params[] = (int)$user->id;
}

if (!empty($_GET['from'])) {
	$where[] = 'ar.date >= ?';
	$params[] = $_GET['from'];
}
if (!empty($_GET['to'])) {
	$where[] = 'ar.date <= ?';
	$params[] = $_GET['to'];
}

$sql = "SELECT ar.id, ar.employee_id, ar.date, ar.status, ar.submitted, ar.submitted_at,
		u.first_name, u.surname
	FROM attendance_records ar
	JOIN users u ON u.id = ar.employee_id
	WHERE " . implode(' AND ', $where) . "
	ORDER BY ar.date DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'attendance' => $rows]);


