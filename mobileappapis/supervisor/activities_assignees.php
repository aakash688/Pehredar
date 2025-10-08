<?php
// GET /api/supervisor/activities/assignees?location_id=ID
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
if (!$locationId) { sup_send_error_response('location_id is required', 400); }

// Ensure requester is assigned to this location
$chk = $pdo->prepare("SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?");
$chk->execute([$user->id, $locationId]);
if (!$chk->fetch()) { sup_send_error_response('Forbidden for this location', 403); }

$q = $pdo->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type
                    FROM supervisor_site_assignments s
                    JOIN users u ON u.id = s.supervisor_id
                    WHERE s.site_id = ? AND u.user_type IN ('Supervisor','Site Supervisor')");
$q->execute([$locationId]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'assignees' => $rows]);


