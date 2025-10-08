<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// mobileappapis/clients/tickets_get_details.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';

// --- Database Connection ---
$config = require __DIR__ . '/../../config.php';
try {
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']};charset=utf8mb4";
    // Use optimized connection pool to solve "max connections per hour" issue
require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';

$pdo = get_api_db_connection_safe();
} catch (PDOException $e) {
    send_error_response('Database connection failed.', 500);
}

// Authenticate the user
$user_data = get_authenticated_user_data();
$user_society_id = $user_data->society->id;

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Invalid request method.', 405);
}

// --- Validation ---
if (empty($_GET['id'])) {
    send_error_response("Ticket ID is required.");
}
$ticketId = $_GET['id'];

try {
    // --- Fetch Ticket Details ---
    $query = "SELECT t.*,
                CASE
                    WHEN t.user_type = 'Client' THEN cu.name
                    ELSE TRIM(CONCAT(u.first_name, ' ', u.surname))
                END as creator_name
              FROM tickets t
              LEFT JOIN users u ON t.user_id = u.id AND (t.user_type != 'Client' OR t.user_type IS NULL)
              LEFT JOIN clients_users cu ON t.user_id = cu.id AND t.user_type = 'Client'
              WHERE t.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        send_error_response('Ticket not found.', 404);
    }
    
    // --- Permission Check ---
    if ($ticket['society_id'] != $user_society_id) {
        send_error_response('You do not have permission to view this ticket.', 403);
    }

    // Get attachments for the main ticket
    $stmtAttach = $pdo->prepare("SELECT file_path, file_name FROM ticket_attachments WHERE ticket_id = ? AND comment_id IS NULL");
    $stmtAttach->execute([$ticketId]);
    $ticket['attachments'] = $stmtAttach->fetchAll();

    // Get comments
    $stmtComm = $pdo->prepare("
        SELECT c.*,
            CASE
                WHEN c.user_type = 'Client' THEN cu.name
                ELSE TRIM(CONCAT(u.first_name, ' ', u.surname))
            END as user_name
        FROM ticket_comments c
        LEFT JOIN clients_users cu ON c.user_id = cu.id AND c.user_type = 'Client'
        LEFT JOIN users u ON c.user_id = u.id AND (c.user_type != 'Client' OR c.user_type IS NULL)
        WHERE c.ticket_id = ? ORDER BY c.created_at ASC
    ");
    $stmtComm->execute([$ticketId]);
    $comments = $stmtComm->fetchAll();

    // Get attachments for all comments on this ticket at once
    $comment_ids = array_map(fn($c) => $c['id'], $comments);
    $comment_attachments = [];
    if (!empty($comment_ids)) {
        $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
        $stmtCommAttach = $pdo->prepare("SELECT comment_id, file_path, file_name FROM ticket_attachments WHERE comment_id IN ($placeholders)");
        $stmtCommAttach->execute($comment_ids);
        // Group attachments by their comment_id
        $comment_attachments = $stmtCommAttach->fetchAll(PDO::FETCH_GROUP);
    }

    // Combine comments with their attachments
    $ticket['comments'] = array_map(function($c) use ($comment_attachments) {
        $c['attachments'] = $comment_attachments[$c['id']] ?? [];
        return $c;
    }, $comments);

    send_json_response(['success' => true, 'ticket' => $ticket]);

} catch (Exception $e) {
    send_error_response('Server Error: ' . $e->getMessage(), 500);
} 