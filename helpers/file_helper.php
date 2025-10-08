<?php

function handle_file_uploads($files, $ticketId, $commentId = null) {
    $config = require __DIR__ . '/../config.php';
    $uploaded_paths = [];
    $uploadDir = __DIR__ . '/../uploads/tickets';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Normalize single-file input to array structure
    if (!is_array($files['tmp_name'])) {
        // Convert to array of one
        foreach (['name','type','tmp_name','error','size'] as $key) {
            $files[$key] = [$files[$key]];
        }
    }

    foreach ($files['tmp_name'] as $key => $tmpName) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $uniqueId = uniqid();
            $fileName = "{$ticketId}-" . ($commentId ? "{$commentId}-" : '') . "{$uniqueId}-" . basename($files['name'][$key]);
            $targetPath = "{$uploadDir}/{$fileName}";

            if (move_uploaded_file($tmpName, $targetPath)) {
                // Store relative path; compose full URL in UI/controllers
                $relativePath = "uploads/tickets/{$fileName}";
                $uploaded_paths[] = [
                    'path' => $relativePath,
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key]
                ];
            }
        }
    }

    return $uploaded_paths;
}

function handle_activity_photo_uploads($files, $activityId) {
    $config = require __DIR__ . '/../config.php';
    $uploaded_paths = [];
    $uploadDir = __DIR__ . '/../uploads/activity_photos';
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedMimeTypes = ['image/jpeg', 'image/png'];

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!is_array($files['tmp_name'])) {
        foreach (['name','type','tmp_name','error','size'] as $key) {
            $files[$key] = [$files[$key]];
        }
    }

    foreach ($files['tmp_name'] as $key => $tmpName) {
        if ($files['error'][$key] !== UPLOAD_ERR_OK) {
            // Optional: log error message $files['error'][$key]
            continue; 
        }

        // Validate file size
        if ($files['size'][$key] > $maxFileSize) {
            // Optional: collect and return errors
            continue;
        }

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            continue;
        }

        $uniqueId = uniqid();
        $extension = pathinfo($files['name'][$key], PATHINFO_EXTENSION);
        $fileName = "activity_{$activityId}_{$uniqueId}." . strtolower($extension);
        $targetPath = "{$uploadDir}/{$fileName}";

        if (move_uploaded_file($tmpName, $targetPath)) {
            // Store relative path for scalability; UI composes full URL
            $relativePath = "uploads/activity_photos/{$fileName}";
            $uploaded_paths[] = [
                'path' => $relativePath,
                'name' => $files['name'][$key],
                'type' => $mimeType
            ];
        }
    }

    return $uploaded_paths;
} 