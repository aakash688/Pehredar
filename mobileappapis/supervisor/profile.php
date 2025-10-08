<?php
// GET /api/supervisor/profile
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$stmt = $pdo->prepare("SELECT id, first_name, surname, mobile_number, email_id, profile_photo,
date_of_birth, gender, address, permanent_address,
aadhar_number, pan_number, esic_number, uan_number, pf_number,
date_of_joining, user_type, salary,
bank_account_number, ifsc_code, bank_name,
created_at
FROM users WHERE id = ?");
$stmt->execute([$user->id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

$locStmt = $pdo->prepare("SELECT s.id, s.society_name as name, s.latitude, s.longitude, s.qr_code FROM supervisor_site_assignments a JOIN society_onboarding_data s ON s.id = a.site_id WHERE a.supervisor_id = ?");
$locStmt->execute([$user->id]);
$locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
	'success' => true,
	'profile' => [
		'id' => (int)$u['id'],
		'name' => $u['first_name'] . ' ' . $u['surname'],
		'phone' => $u['mobile_number'],
		'email' => $u['email_id'],
		'photo' => $u['profile_photo'],
		'date_of_birth' => $u['date_of_birth'],
		'gender' => $u['gender'],
		'address' => $u['address'],
		'permanent_address' => $u['permanent_address'],
		'aadhar_number' => $u['aadhar_number'],
		'pan_number' => $u['pan_number'],
		'esic_number' => $u['esic_number'],
		'uan_number' => $u['uan_number'],
		'pf_number' => $u['pf_number'],
		'date_of_joining' => $u['date_of_joining'],
		'user_type' => $u['user_type'],
		'salary' => $u['salary'],
		'bank_account_number' => $u['bank_account_number'],
		'ifsc_code' => $u['ifsc_code'],
		'bank_name' => $u['bank_name'],
		'created_at' => $u['created_at'],
		'assigned_locations' => $locations
	]
]);


