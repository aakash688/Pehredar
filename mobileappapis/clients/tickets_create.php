<?php
header("Content-Type: application/json; charset=UTF-8");
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
$society_id = $user_data->society->id;
$user_role = $user_data->role; // Get role from token

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error_response('Invalid request method.', 405);
}

// Handle both JSON and form data
$body = [];
$raw_input = ''; // Initialize to avoid undefined variable warning

// Try form data first (more reliable for debugging)
if (!empty($_POST)) {
    $body = $_POST;
} else {
    // Try JSON as fallback
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        $json_body = json_decode($raw_input, true);
        if (is_array($json_body)) {
            $body = $json_body;
        }
    }
}

// --- Validation ---
$title = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$priority = $body['priority'] ?? 'Medium';

if (empty($title)) {
    send_error_response('Missing required field: title');
}

if (empty($description)) {
    send_error_response('Missing required field: description');
}

try {
    $pdo->beginTransaction();

    // Insert the ticket
    $sql = "INSERT INTO tickets (society_id, user_id, user_type, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'Open')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$society_id, $user_id, $user_role, $title, $description, $priority]);
    $ticketId = $pdo->lastInsertId();

    // Log history
    $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type) VALUES (?, ?, 'CREATED')";
    $pdo->prepare($historySql)->execute([$ticketId, $user_id]);
    
    $pdo->commit();
    
    send_json_response([
        'success' => true, 
        'id' => $ticketId
    ], 201);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    send_error_response('Server Error: ' . $e->getMessage(), 500);
} 