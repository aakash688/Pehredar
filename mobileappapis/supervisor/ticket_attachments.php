<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    exit;
}

require_once __DIR__ . '/api_helpers.php';
$user = sup_get_authenticated_user();
$pdo = sup_get_db();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sup_send_error_response('Method not allowed', 405);
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
if ($ticketId <= 0) { sup_send_error_response('ticket_id required', 400); }

// Ensure ticket belongs to a location assigned to the user
$locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
$locStmt->execute([$ticketId]);
$t = $locStmt->fetch(PDO::FETCH_ASSOC);
if (!$t) { sup_send_error_response('Ticket not found', 404); }
$locationId = (int)$t['society_id'];
$check = $pdo->prepare('SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?');
$check->execute([$user->id, $locationId]);
if (!$check->fetch()) { sup_send_error_response('Location not assigned to supervisor', 403); }

// Prepare upload dir
$baseUploadDir = realpath(__DIR__ . '/../../uploads');
if ($baseUploadDir === false) { @mkdir(__DIR__ . '/../../uploads', 0775, true); $baseUploadDir = realpath(__DIR__ . '/../../uploads'); }
$ticketDir = $baseUploadDir . DIRECTORY_SEPARATOR . 'tickets';
if (!is_dir($ticketDir)) { @mkdir($ticketDir, 0775, true); }

function save_one_file($file, $ticketId, $userId, $pdo, $commentId = 0) {
    $ext = pathinfo($file['name'] ?? 'file', PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $name = 'ticket_' . $ticketId . ($commentId ? ('_c' . $commentId) : '') . '_' . uniqid('', true) . ($safeExt ? ('.' . strtolower($safeExt)) : '');
    $destAbs = realpath(__DIR__ . '/../../uploads/tickets');
    if ($destAbs === false) { @mkdir(__DIR__ . '/../../uploads/tickets', 0775, true); $destAbs = realpath(__DIR__ . '/../../uploads/tickets'); }
    $destPath = $destAbs . DIRECTORY_SEPARATOR . $name;
    if (!is_uploaded_file($file['tmp_name'])) { return null; }
    if (!move_uploaded_file($file['tmp_name'], $destPath)) { return null; }
    $rel = 'uploads/tickets/' . $name;
    // Insert DB record if table exists; try multiple schemas to match web app
    try {
        $fileType = strtolower($safeExt ?: (mime_content_type($destPath) ?: 'image/jpeg'));
        // 1) Web schema from schema/ticket_attachments.php
        try {
            $ins = $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, comment_id, file_path, file_name, file_type, uploaded_at) VALUES (?,?,?,?,?,NOW())');
            $ins->execute([$ticketId, $commentId ?: null, $rel, $file['name'] ?? $name, $fileType]);
        } catch (Throwable $e1) {
            // 2) Alternative with ticket_comment_id and uploaded_by/created_at
            try {
                $ins = $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, ticket_comment_id, file_path, file_name, uploaded_by, created_at) VALUES (?,?,?,?,?,NOW())');
                $ins->execute([$ticketId, $commentId ?: null, $rel, $file['name'] ?? $name, $userId]);
            } catch (Throwable $e2) {
                // 3) Minimal columns
                try {
                    $ins = $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, file_path, file_name, uploaded_at) VALUES (?,?,?,NOW())');
                    $ins->execute([$ticketId, $rel, $file['name'] ?? $name]);
                } catch (Throwable $e3) {
                    // 4) Legacy without timestamps
                    $ins = $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, file_path, file_name) VALUES (?,?,?)');
                    $ins->execute([$ticketId, $rel, $file['name'] ?? $name]);
                }
            }
        }
        $id = (int)$pdo->lastInsertId();
        return ['id' => $id, 'file_path' => $rel, 'file_name' => ($file['name'] ?? null), 'comment_id' => ($commentId ?: null)];
    } catch (Throwable $e) {
        @error_log('[TICKET_ATTACH][DB_SKIP] ' . $e->getMessage());
        return ['id' => 0, 'file_path' => $rel, 'file_name' => ($file['name'] ?? null), 'comment_id' => ($commentId ?: null)];
    }
}

$saved = [];
@error_log('[TICKET_ATTACH][START] TICKET=' . $ticketId . ' FILE_KEYS=' . implode(',', array_keys($_FILES ?? [])));
// Support multiple keys: attachments[], files[], attachment, photo, file
if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $count = count($_FILES['attachments']['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name' => $_FILES['attachments']['name'][$i],
            'type' => $_FILES['attachments']['type'][$i],
            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
            'error' => $_FILES['attachments']['error'][$i],
            'size' => $_FILES['attachments']['size'][$i],
        ];
        $one = save_one_file($file, $ticketId, $user->id, $pdo, $commentId);
        if ($one) { $saved[] = $one; }
    }
} elseif (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
    $count = count($_FILES['files']['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name' => $_FILES['files']['name'][$i],
            'type' => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error' => $_FILES['files']['error'][$i],
            'size' => $_FILES['files']['size'][$i],
        ];
        $one = save_one_file($file, $ticketId, $user->id, $pdo, $commentId);
        if ($one) { $saved[] = $one; }
    }
} elseif (!empty($_FILES['attachment'])) {
    $one = save_one_file($_FILES['attachment'], $ticketId, $user->id, $pdo, $commentId);
    if ($one) { $saved[] = $one; }
} elseif (!empty($_FILES['photo'])) {
    $one = save_one_file($_FILES['photo'], $ticketId, $user->id, $pdo, $commentId);
    if ($one) { $saved[] = $one; }
} elseif (!empty($_FILES['file'])) {
    $one = save_one_file($_FILES['file'], $ticketId, $user->id, $pdo, $commentId);
    if ($one) { $saved[] = $one; }
}

if (empty($saved)) { @error_log('[TICKET_ATTACH][NO_FILES]'); sup_send_error_response('No files uploaded', 400); }

echo json_encode(['success' => true, 'attachments' => $saved]);


