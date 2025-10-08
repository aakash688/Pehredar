<?php
// GET /api/supervisor/site-log/history?from=YYYY-MM-DD&to=YYYY-MM-DD&location_id=ID&action=checkin|checkout
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$params = [$user->id];
$where = ["l.supervisor_id = ?"]; // alias l

if (!empty($_GET['from'])) {
	$where[] = 'DATE(l.timestamp) >= ?';
	$params[] = $_GET['from'];
}
if (!empty($_GET['to'])) {
	$where[] = 'DATE(l.timestamp) <= ?';
	$params[] = $_GET['to'];
}
if (!empty($_GET['location_id'])) {
	$where[] = 'l.location_id = ?';
	$params[] = (int)$_GET['location_id'];
}
if (!empty($_GET['action']) && in_array(strtolower($_GET['action']), ['checkin','checkout'])) {
	$where[] = 'l.action = ?';
	$params[] = strtolower($_GET['action']);
}

$sql = "SELECT l.id, l.action, l.timestamp, l.latitude, l.longitude, l.active_session, s.society_name AS location_name, l.location_id
        FROM supervisor_site_logs l
        JOIN society_onboarding_data s ON s.id = l.location_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.timestamp DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Convert timestamps to IST
foreach ($rows as &$r) {
    if (isset($r['timestamp'])) { $r['timestamp'] = sup_convert_to_app_tz($r['timestamp']); }
}
unset($r);

echo json_encode(['success' => true, 'logs' => $rows]);


