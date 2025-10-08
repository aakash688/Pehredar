<?php
// POST /api/supervisor/profile-update (JSON for text fields)
// POST /api/supervisor/profile-photo (multipart for photo) in same file by route
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$path = $_SERVER['REQUEST_URI'] ?? '';

// Photo upload route
if (preg_match('#/profile-photo(\.php)?$#', $path)) {
    if (!isset($_FILES['photo'])) {
        sup_send_error_response('photo file is required (multipart/form-data)');
    }
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sup_send_error_response('Upload failed');
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif'])) {
        sup_send_error_response('Invalid image type');
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = 'user_' . $user->id . '_profile_photo_' . uniqid() . '.' . $ext;
    $absDir = __DIR__ . '/../../uploads/';
    if (!is_dir($absDir)) { @mkdir($absDir, 0755, true); }
    $dest = $absDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        sup_send_error_response('Failed to save image');
    }
    $relPath = 'uploads/' . $filename;
    $upd = $pdo->prepare('UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute([$relPath, $user->id]);
    echo json_encode(['success' => true, 'photo' => $relPath]);
    exit;
}

// Profile details update
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { sup_send_error_response('Invalid JSON body'); }

$fields = [];$params=[];
foreach ([
    'first_name','surname','date_of_birth','gender','address','permanent_address',
    'aadhar_number','pan_number','esic_number','uan_number','pf_number',
    'date_of_joining','salary','bank_account_number','ifsc_code','bank_name'
] as $f) {
    if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
}
if (empty($fields)) { sup_send_error_response('No fields to update'); }
$params[] = $user->id;
$sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true]);


