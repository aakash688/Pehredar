<?php
// GET /api/supervisor/site-visits?from=YYYY-MM-DD&to=YYYY-MM-DD
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

$params = [$user->id];
$where = ['l.supervisor_id = ?'];
if (!empty($_GET['from'])) { $where[] = 'DATE(l.timestamp) >= ?'; $params[] = $_GET['from']; }
if (!empty($_GET['to'])) { $where[] = 'DATE(l.timestamp) <= ?'; $params[] = $_GET['to']; }

// compute sessions by pairing checkin->next checkout
$sql = "SELECT v.location_id, s.society_name AS location_name,
               v.checkin_at AS `from`, v.checkout_at AS `to`,
               v.duration_minutes AS duration
        FROM supervisor_site_visits v
        JOIN society_onboarding_data s ON s.id = v.location_id
        WHERE v.supervisor_id = ?
          " . (!empty($_GET['from']) ? " AND DATE(v.checkin_at) >= ?" : "") .
            (!empty($_GET['to']) ? " AND DATE(v.checkout_at) <= ?" : "") .
        " ORDER BY v.checkin_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Convert timestamps to IST
foreach ($rows as &$r) {
    if (isset($r['from'])) { $r['from'] = sup_convert_to_app_tz($r['from']); }
    if (isset($r['to'])) { $r['to'] = sup_convert_to_app_tz($r['to']); }
}
unset($r);
echo json_encode(['success' => true, 'visits' => $rows]);


