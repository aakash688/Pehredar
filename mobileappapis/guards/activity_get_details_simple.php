<?php
// Simple activity details API for debugging
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

require_once '../../vendor/autoload.php';
require_once '../../helpers/ConnectionPool.php';
require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Simple bearer token function
function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

try {
    // Authentication
    $jwt = getBearerToken();
    if (!$jwt) { 
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $config = require '../../config.php';
    $decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
    $userId = (int)($decoded->data->id ?? 0);
    $userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
    
    if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { 
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Invalid request method']);
        exit;
    }

    // Validate activity_id parameter
    if (empty($_GET['activity_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Activity ID is required']);
        exit;
    }

    $activityId = (int)$_GET['activity_id'];
    if ($activityId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid activity ID']);
        exit;
    }

    $pdo = ConnectionPool::getConnection();

    // Check if user has access to this activity (through roster assignment)
    $accessCheckSql = "
        SELECT COUNT(DISTINCT a.id) as access_count
        FROM activities a
        LEFT JOIN roster r ON r.society_id = a.society_id 
        WHERE a.id = ? AND r.guard_id = ?
    ";
    $accessStmt = $pdo->prepare($accessCheckSql);
    $accessStmt->execute([$activityId, $userId]);
    $accessResult = $accessStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$accessResult || (int)$accessResult['access_count'] === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Activity not found or access denied']);
        exit;
    }

    // Fetch basic activity details
    $activitySql = "
        SELECT 
            a.id,
            a.title,
            a.description,
            a.scheduled_date,
            a.status,
            a.tags,
            a.created_at,
            a.updated_at,
            a.society_id,
            s.society_name,
            s.address as society_address
        FROM activities a
        LEFT JOIN society_onboarding_data s ON s.id = a.society_id
        WHERE a.id = ?
        LIMIT 1
    ";
    
    $activityStmt = $pdo->prepare($activitySql);
    $activityStmt->execute([$activityId]);
    $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        http_response_code(404);
        echo json_encode(['error' => 'Activity not found']);
        exit;
    }

    // Parse tags from JSON or CSV format
    $tagsRaw = $activity['tags'] ?? '';
    $tagsArray = [];
    
    if (!empty($tagsRaw)) {
        // Try to decode as JSON first (Tagify format)
        $jsonTags = json_decode($tagsRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonTags)) {
            $tagsArray = array_column($jsonTags, 'value');
        } else {
            // Fallback: treat as comma-separated string
            $tagsArray = array_map('trim', explode(',', $tagsRaw));
        }
    }

    // Parse scheduled date for date/time separation
    $scheduledDate = $activity['scheduled_date'] ?? '';
    $activityDate = '';
    $activityTime = '';
    
    if (!empty($scheduledDate)) {
        try {
            $dt = new DateTime($scheduledDate);
            $activityDate = $dt->format('Y-m-d');
            $activityTime = $dt->format('H:i');
        } catch (Exception $e) {
            // If parsing fails, keep original
            $activityDate = $scheduledDate;
            $activityTime = '00:00';
        }
    }

    $response = [
        'id' => (string)$activity['id'],
        'title' => $activity['title'] ?? 'No Title',
        'description' => $activity['description'] ?? '',
        'date' => $activityDate,
        'time' => $activityTime,
        'scheduled_date' => $scheduledDate,
        'status' => $activity['status'] ?? 'pending',
        'venue' => $activity['society_name'] ?? 'Unknown Location',
        'location' => $activity['society_name'] ?? 'Unknown Location',
        'society_id' => (int)$activity['society_id'],
        'society_name' => $activity['society_name'] ?? '',
        'society_address' => $activity['society_address'] ?? '',
        'created_at' => $activity['created_at'] ?? '',
        'updated_at' => $activity['updated_at'] ?? '',
        'organizer' => 'Security Team',
        'tags' => $tagsArray,
        'photos' => [], // Empty for now
        'assignees' => [], // Empty for now
        'assigned_to_me' => false,
        'imagesCount' => 0,
        'images_count' => 0,
        'latestImages' => [],
        'latest_images' => []
    ];

    // Send response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'activity' => $response
    ]);

} catch (Throwable $e) {
    // Log detailed error for debugging
    error_log("Activity Details Simple API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Send clean error response
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while fetching activity details', 'debug' => $e->getMessage()]);
}
?>
