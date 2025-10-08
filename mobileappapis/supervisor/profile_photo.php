<?php
// POST /api/supervisor/profile-photo (multipart with field `photo`)
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

if (!isset($_FILES['photo'])) {
    sup_send_error_response('photo file is required (multipart/form-data)', 400);
}

$file = $_FILES['photo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    sup_send_error_response('Upload failed (code ' . $file['error'] . ')', 400);
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ['image/jpeg','image/png','image/gif'])) {
    sup_send_error_response('Invalid image type', 400);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'user_' . $user->id . '_profile_photo_' . uniqid() . '.' . $ext;
$absDir = __DIR__ . '/../../uploads/';
if (!is_dir($absDir)) { @mkdir($absDir, 0755, true); }
$dest = $absDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    sup_send_error_response('Failed to save image', 500);
}

$relPath = 'uploads/' . $filename;
$upd = $pdo->prepare('UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?');
$upd->execute([$relPath, $user->id]);

echo json_encode(['success' => true, 'photo' => $relPath]);


