<?php
// GET /api/supervisor/team and GET /api/supervisor/team/{employeeId}
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$empId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if ($empId) {
	$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.surname, u.mobile_number, u.email_id, u.user_type, tm.team_id, tm.location_id FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.role != 'Supervisor' AND u.id = ?");
	$stmt->execute([$empId]);
	$emp = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$emp) {
		sup_send_error_response('Employee not found', 404);
	}
	
	// Get roster assignments for this guard
	$rosterStmt = $pdo->prepare("SELECT r.id, r.society_id, r.shift_id, r.assignment_start_date, r.assignment_end_date,
	                                    sod.society_name, sm.shift_name, sm.start_time, sm.end_time
	                              FROM roster r
	                              JOIN society_onboarding_data sod ON r.society_id = sod.id
	                              JOIN shift_master sm ON r.shift_id = sm.id
	                             WHERE r.guard_id = ?
	                             ORDER BY r.assignment_start_date DESC");
	$rosterStmt->execute([$empId]);
	$roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);
	
	sup_send_json_response(['success' => true, 'employee' => $emp, 'roster' => $roster]);
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$matches = [];
if (preg_match('#/team/(\d+)#', $path, $matches)) {
	$empId = (int)$matches[1];
	$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.surname, u.mobile_number, u.email_id, u.user_type, tm.team_id, tm.location_id FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.role != 'Supervisor' AND u.id = ?");
	$stmt->execute([$empId]);
	$emp = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$emp) {
		sup_send_error_response('Employee not found', 404);
	}
	sup_send_json_response(['success' => true, 'employee' => $emp]);
}

$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.surname, u.mobile_number, u.email_id, u.user_type, tm.team_id, tm.location_id
	FROM team_members tm
	JOIN users u ON u.id = tm.user_id
	WHERE tm.role != 'Supervisor' AND tm.team_id IN (
		SELECT team_id FROM team_members WHERE user_id = ? AND role = 'Supervisor'
	)");
$stmt->execute([$user->id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also include teams list for filters
$teams = [];
try {
    $tstmt = $pdo->prepare("SELECT DISTINCT t.id, t.team_name FROM teams t JOIN team_members tm ON tm.team_id = t.id WHERE tm.user_id = ?");
    $tstmt->execute([$user->id]);
    $teams = $tstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

echo json_encode(['success' => true, 'team' => $rows, 'teams' => $teams]);


