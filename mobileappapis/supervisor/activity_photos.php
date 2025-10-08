<?php
// GET /api/supervisor/activity-photos?activity_id=ID
// POST /api/supervisor/activity-photos (multipart: activity_id, photos[] or photo)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $activityId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    if (!$activityId) { sup_send_error_response('activity_id is required', 400); }
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    // ensure access
    $chk = $pdo->prepare('SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?');
    $chk->execute([$user->id, (int)$act['society_id']]);
    if (!$chk->fetch()) { sup_send_error_response('Forbidden', 403); }
    $p = $pdo->prepare('SELECT id, image_url FROM activity_photos WHERE activity_id = ? ORDER BY created_at DESC');
    $p->execute([$activityId]);
    $rows = $p->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'photos' => $rows]);
    exit;
}

// POST upload
$activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
if (!$activityId) { sup_send_error_response('activity_id is required', 400); }
$locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
$locStmt->execute([$activityId]);
$act = $locStmt->fetch(PDO::FETCH_ASSOC);
if (!$act) { sup_send_error_response('Activity not found', 404); }
$chk = $pdo->prepare('SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?');
$chk->execute([$user->id, (int)$act['society_id']]);
if (!$chk->fetch()) { sup_send_error_response('Forbidden', 403); }

$saved = [];
$absDir = realpath(__DIR__ . '/../../uploads');
if ($absDir === false) { @mkdir(__DIR__ . '/../../uploads', 0755, true); $absDir = realpath(__DIR__ . '/../../uploads'); }
// Put activity images under uploads/activities
$absDir = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'activities' . DIRECTORY_SEPARATOR;
if (!is_dir($absDir)) { @mkdir($absDir, 0755, true); }
if (!is_dir($absDir)) { @mkdir($absDir, 0755, true); }

function handle_one_upload($idxKey, $activityId, $userId, $absDir, $pdo, &$saved) {
    $file = $_FILES[$idxKey];
    $isArray = is_array($file['name']);
    $count = $isArray ? count($file['name']) : 1;
    for ($i = 0; $i < $count; $i++) {
        $err = $isArray ? $file['error'][$i] : $file['error'];
        if ($err !== UPLOAD_ERR_OK) { continue; }
        $tmp = $isArray ? $file['tmp_name'][$i] : $file['tmp_name'];
        $name = $isArray ? $file['name'][$i] : $file['name'];
        // Be permissive: infer extension from name; avoid strict mime checks that fail on some Windows setups
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!$ext || strlen($ext) > 5) { $ext = 'jpg'; }
        $filename = 'activity_' . $activityId . '_photo_' . uniqid('', true) . '.' . $ext;
        if (move_uploaded_file($tmp, $absDir . $filename)) {
            $rel = 'uploads/activities/' . $filename;
            $ins = $pdo->prepare('INSERT INTO activity_photos (activity_id, uploaded_by_user_id, image_url) VALUES (?,?,?)');
            $ins->execute([$activityId, $userId, $rel]);
            $saved[] = $rel;
        }
    }
}

if (isset($_FILES['photos'])) {
    handle_one_upload('photos', $activityId, $user->id, $absDir, $pdo, $saved);
}
if (isset($_FILES['photo'])) {
    handle_one_upload('photo', $activityId, $user->id, $absDir, $pdo, $saved);
}

echo json_encode(['success' => true, 'photos' => $saved]);


