<?php
// POST /api/supervisor/attendance/submit
// Body: { records: [{ employee_id, date, status, location_id?, group_id?, client_id? }, ...] }
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['records']) || !is_array($body['records'])) {
	sup_send_error_response('records array is required');
}

$pdo->beginTransaction();
try {
    // Ensure the employee belongs to the supervisor's team
    $teamCheck = $pdo->prepare("SELECT 1 FROM team_members tm WHERE tm.user_id = ? AND tm.team_id IN (SELECT team_id FROM team_members WHERE user_id = ? AND role = 'Supervisor')");

    $insert = $pdo->prepare("INSERT INTO attendance_records (employee_id, marked_by, date, status, submitted, submitted_at, location_id, group_id, client_id) VALUES (?,?,?,?,1,NOW(),?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), submitted = 1, submitted_at = NOW(), location_id = VALUES(location_id), group_id = VALUES(group_id), client_id = VALUES(client_id)");
	foreach ($body['records'] as $rec) {
		$emp = (int)($rec['employee_id'] ?? 0);
		$date = $rec['date'] ?? null;
		$status = $rec['status'] ?? null;
		if (!$emp || !$date || !$status) {
			throw new Exception('Each record requires employee_id, date, and status.');
		}
        // Team membership enforcement
        $teamCheck->execute([$emp, $user->id]);
        if (!$teamCheck->fetch()) {
            throw new Exception('You can only mark attendance for your own team members.');
        }
		$insert->execute([$emp, $user->id, $date, $status, $rec['location_id'] ?? null, $rec['group_id'] ?? null, $rec['client_id'] ?? null]);
	}
	$pdo->commit();
	sup_send_json_response(['success' => true, 'message' => 'Attendance submitted and locked.']);
} catch (Exception $e) {
	$pdo->rollBack();
	sup_send_error_response('Failed to submit attendance: ' . $e->getMessage(), 500);
}


