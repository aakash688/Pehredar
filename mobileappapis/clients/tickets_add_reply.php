<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';

// --- Database Connection ---
$pdo = get_api_db_connection_safe();

// Authenticate the user
$user_data = get_authenticated_user_data();
$user_id = $user_data->id;
$user_society_id = $user_data->society->id;
$user_role = $user_data->role;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error_response('Invalid request method.', 405);
}

// --- Validation ---
if (empty($_POST['ticket_id']) || empty($_POST['comment'])) {
    send_error_response('Ticket ID and comment are required.');
}
$ticketId = $_POST['ticket_id'];
$comment_text = $_POST['comment'];

try {
    // --- Permission Check ---
    $stmt = $pdo->prepare("SELECT society_id FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket_society_id = $stmt->fetchColumn();

    if (!$ticket_society_id) {
        send_error_response('Ticket not found.', 404);
    }
    if ($ticket_society_id != $user_society_id) {
        send_error_response('You do not have permission to reply to this ticket.', 403);
    }
    
    // --- Add the comment ---
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO ticket_comments (ticket_id, user_id, user_type, comment) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticketId, $user_id, $user_role, $comment_text]);
    $commentId = $pdo->lastInsertId();

    // Log history
    $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type) VALUES (?, ?, 'COMMENT_ADDED')";
    $pdo->prepare($historySql)->execute([$ticketId, $user_id]);
    
    // Also update the ticket's `updated_at` timestamp to bring it to the top of lists
    $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticketId]);

    $pdo->commit();

    send_json_response([
        'success' => true, 
        'message' => 'Reply added successfully.',
        'comment_id' => $commentId
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    send_error_response('Server Error: ' . $e->getMessage(), 500);
} 