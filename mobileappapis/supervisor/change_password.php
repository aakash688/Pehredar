<?php
// POST /api/supervisor/change-password
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

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['current_password']) || empty($body['new_password'])) {
    sup_send_error_response('current_password and new_password are required.', 400);
}

$cur = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$cur->execute([$user->id]);
$row = $cur->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    sup_send_error_response('User not found', 404);
}

if (!password_verify($body['current_password'], $row['password'])) {
    sup_send_error_response('Incorrect current password.', 401);
}

$hashed = password_hash($body['new_password'], PASSWORD_DEFAULT);
$upd = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
$upd->execute([$hashed, $user->id]);

echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);


