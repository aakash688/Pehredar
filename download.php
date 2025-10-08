<?php
// Secure file download handler

// Optional: Include config and other necessary files
require_once 'config.php';

// Validate file parameter
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('File not specified.');
}

$relativePath = $_GET['file'];
$fullPath = __DIR__ . '/' . $relativePath;

// Validate file path to prevent access outside uploads
$uploadsDir = realpath(__DIR__ . '/uploads');
$resolvedPath = realpath($fullPath);

// Strict path validation to ensure file is within uploads directory
if (!$resolvedPath || strpos($resolvedPath, $uploadsDir) !== 0) {
    http_response_code(403);
    die('File not found or access denied.');
}

// Check if file exists
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('File not found.');
}

$fileName = basename($fullPath);
$fileSize = filesize($fullPath);
$fileType = mime_content_type($fullPath);

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Clear output buffer
ob_clean();
flush();

// Output file contents
readfile($fullPath);
exit; 