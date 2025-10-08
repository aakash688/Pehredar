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

    // Fetch comprehensive activity details including creator info
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
            a.created_by,
            s.society_name,
            s.address as society_address,
            u.first_name as creator_first_name,
            u.surname as creator_surname,
            u.email_id as creator_email
        FROM activities a
        LEFT JOIN society_onboarding_data s ON s.id = a.society_id
        LEFT JOIN users u ON a.created_by = u.id
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

    // Get activity photos
    $photosSql = "
        SELECT id, image_url, description, created_at 
        FROM activity_photos 
        WHERE activity_id = ? AND is_approved = 1 
        ORDER BY created_at DESC
        LIMIT 20
    ";
    $photosStmt = $pdo->prepare($photosSql);
    $photosStmt->execute([$activityId]);
    $photos = $photosStmt->fetchAll(PDO::FETCH_ASSOC);
    // Normalize photo URLs to full paths using base_url when stored as relative paths
    $cfg = require '../../config.php';
    $baseUrl = rtrim($cfg['base_url'] ?? '', '/');
    $formattedPhotos = array_map(function($photo) use ($baseUrl) {
        $p = $photo['image_url'] ?? '';
        if ($p && !(stripos($p, 'http://') === 0 || stripos($p, 'https://') === 0)) {
            $p = $baseUrl . '/' . ltrim($p, '/');
        }
        return [
            'id' => (int)($photo['id'] ?? 0),
            'photoUrl' => $p,
            'imageUrl' => $p,
            'description' => $photo['description'] ?? '',
            'created_at' => $photo['created_at'] ?? ''
        ];
    }, $photos);

    // Get assignees (other guards/staff assigned to this activity)
    $assigneesSql = "
        SELECT 
            u.id,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) AS name,
            u.user_type,
            u.email_id
        FROM activity_assignees aa 
        JOIN users u ON u.id = aa.user_id 
        WHERE aa.activity_id = ? 
        ORDER BY u.first_name ASC, u.surname ASC
        LIMIT 50
    ";
    $assigneesStmt = $pdo->prepare($assigneesSql);
    $assigneesStmt->execute([$activityId]);
    $assignees = $assigneesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if current user is assigned to this activity
    $assignedToMeSql = "
        SELECT COUNT(*) as count
        FROM activity_assignees 
        WHERE activity_id = ? AND user_id = ?
        LIMIT 1
    ";
    $assignedToMeStmt = $pdo->prepare($assignedToMeSql);
    $assignedToMeStmt->execute([$activityId, $userId]);
    $assignedToMeResult = $assignedToMeStmt->fetch(PDO::FETCH_ASSOC);
    $assignedToMe = (int)$assignedToMeResult['count'] > 0;

    // Format the organizer name
    $organizerName = trim(($activity['creator_first_name'] ?? '') . ' ' . ($activity['creator_surname'] ?? ''));
    if (empty($organizerName)) {
        $organizerName = 'Security Team';
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
        'organizer' => $organizerName,
        'creator_email' => $activity['creator_email'] ?? '',
        'tags' => $tagsArray,
        'photos' => $formattedPhotos,
        'assignees' => $assignees,
        'assigned_to_me' => $assignedToMe,
        'imagesCount' => count($formattedPhotos),
        'images_count' => count($formattedPhotos), // Alternative key
        'latestImages' => array_slice(array_column($formattedPhotos, 'photoUrl'), 0, 3),
        'latest_images' => array_slice(array_column($formattedPhotos, 'imageUrl'), 0, 3) // Alternative key
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
