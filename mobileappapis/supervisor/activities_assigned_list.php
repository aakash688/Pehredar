<?php
// GET /api/supervisor/activities/assigned
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

$where = [];
$params = [];

if (!empty($_GET['location_id'])) { $where[] = 'a.society_id = ?'; $params[] = (int)$_GET['location_id']; }
if (!empty($_GET['status'])) { $where[] = 'a.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['q'])) {
    $q = '%' . $_GET['q'] . '%';
    $where[] = '(a.title LIKE ? OR a.description LIKE ?)';
    $params[] = $q; $params[] = $q;
}

// Assigned to me and restricted to assigned societies
$where[] = 'a.id IN (SELECT activity_id FROM activity_assignees WHERE user_id = ?)';
$params[] = (int)$user->id;
$where[] = 'a.society_id IN (SELECT site_id FROM supervisor_site_assignments WHERE supervisor_id = ?)';
$params[] = (int)$user->id;

$sql = "SELECT a.id, a.title, a.description, a.scheduled_date, a.status, a.society_id AS location_id,
               s.society_name AS location
        FROM activities a
        JOIN society_onboarding_data s ON s.id = a.society_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.scheduled_date DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'activities' => $rows]);


