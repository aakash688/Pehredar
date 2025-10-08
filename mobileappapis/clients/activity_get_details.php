<?php
// mobileappapis/clients/activity_get_details.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';

// DB Connection
$config = require __DIR__ . '/../../config.php';
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['dbname']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    send_error_response('Database connection failed: ' . $e->getMessage(), 500);
}

// Authenticate the user
$user_data = get_authenticated_user_data();
$user_society_id = $user_data->society->id;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Invalid request method.', 405);
}

// Validation: Check for activity_id in the URL query string
if (empty($_GET['activity_id'])) {
    send_error_response('Activity ID is required.');
}

$activityId = $_GET['activity_id'];

try {
    // Fetch Activity Details
    $query = "SELECT a.*, u.first_name, u.surname 
              FROM activities a
              JOIN users u ON a.created_by = u.id
              WHERE a.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch();

    if (!$activity) {
        send_error_response('Activity not found.', 404);
    }
    
    // Permission Check
    if ($activity['society_id'] != $user_society_id) {
        send_error_response('You do not have permission to view this activity.', 403);
    }

    // --- Parse Tags ---
    $tags_raw = $activity['tags'] ?? '';
    $tags_array = json_decode($tags_raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tags_array)) {
        // It's JSON from Tagify
        $activity['tags'] = array_column($tags_array, 'value');
    } else {
        // It's a plain comma-separated string or empty
        $activity['tags'] = !empty($tags_raw) ? array_map('trim', explode(',', $tags_raw)) : [];
    }

    // Fetch approved photos for the activity
    $stmtPhotos = $pdo->prepare("SELECT id, image_url, description, created_at FROM activity_photos WHERE activity_id = ? AND is_approved = 1 ORDER BY created_at DESC");
    $stmtPhotos->execute([$activityId]);
    $activity['photos'] = $stmtPhotos->fetchAll();

    send_json_response(['success' => true, 'activity' => $activity]);

} catch (Exception $e) {
    send_error_response('Server Error: ' . $e->getMessage(), 500);
} 