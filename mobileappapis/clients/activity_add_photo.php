<?php
// mobileappapis/clients/activity_add_photo.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']};charset=utf8mb4";
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
$user_id = $user_data->id;
$user_society_id = $user_data->society->id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error_response('Invalid request method.', 405);
}

// Validation
if (empty($_POST['activity_id'])) {
    send_error_response('Activity ID is required.');
}
if (empty($_FILES['photos'])) {
    send_error_response('At least one photo is required.');
}

$activityId = $_POST['activity_id'];
$description = $_POST['description'] ?? null;

try {
    // Permission & Status Check
    $stmt = $pdo->prepare("SELECT society_id, status FROM activities WHERE id = ?");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch();

    if (!$activity) {
        send_error_response('Activity not found.', 404);
    }
    if ($activity['society_id'] != $user_society_id) {
        send_error_response('You do not have permission to upload photos to this activity.', 403);
    }
    if ($activity['status'] !== 'Completed') {
        send_error_response('Photos can only be uploaded to completed activities.', 403);
    }
    
    // Handle file uploads
    $uploaded_files = handle_activity_photo_uploads($_FILES['photos'], $activityId);

    if (empty($uploaded_files)) {
        send_error_response('Photo upload failed. Please check file types (JPG, PNG) and size (max 5MB).', 400);
    }

    // Insert into database
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO activity_photos (activity_id, uploaded_by_user_id, image_url, description, is_approved) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    // Assuming moderation is ON by default for client users. is_approved = 0
    $is_approved = 0; 

    foreach ($uploaded_files as $file) {
        $stmt->execute([$activityId, $user_id, $file['path'], $description, $is_approved]);
    }

    $pdo->commit();

    send_json_response(['success' => true, 'message' => 'Photos uploaded successfully. They will be visible after moderation.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_error_response('Server Error: ' . $e->getMessage(), 500);
}

/**
 * Handle activity photo uploads
 * @param array $files $_FILES array
 * @param int $activityId
 * @return array Array of uploaded file info
 */
function handle_activity_photo_uploads($files, $activityId) {
    $uploaded_files = [];
    $upload_dir = __DIR__ . '/../../uploads/activities/' . $activityId . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Handle multiple files
    $file_count = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $file_count; $i++) {
        $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $file_tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $file_error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        
        if ($file_error === UPLOAD_ERR_OK) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $file_tmp);
            finfo_close($file_info);
            
            if (!in_array($mime_type, $allowed_types)) {
                continue; // Skip invalid file types
            }
            
            // Validate file size (5MB max)
            if ($file_size > 5 * 1024 * 1024) {
                continue; // Skip files larger than 5MB
            }
            
            // Generate unique filename
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_name = uniqid() . '_' . time() . '.' . $extension;
            $file_path = $upload_dir . $unique_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                $uploaded_files[] = [
                    'path' => 'uploads/activities/' . $activityId . '/' . $unique_name,
                    'original_name' => $file_name,
                    'size' => $file_size,
                    'type' => $mime_type
                ];
            }
        }
    }
    
    return $uploaded_files;
} 