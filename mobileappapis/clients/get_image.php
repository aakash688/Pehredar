<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to output a transparent 1x1 PNG as fallback
function outputTransparentPng() {
    header('Content-Type: image/png');
    // This is a valid, transparent 1x1 PNG
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    exit;
}

// Validate path parameter
if (!isset($_GET['path']) || empty($_GET['path'])) {
    error_log("get_image.php: No path parameter provided");
    outputTransparentPng();
}

$path = $_GET['path'];

// Security check - prevent directory traversal
if (strpos($path, '..') !== false) {
    error_log("get_image.php: Directory traversal attempt: $path");
    outputTransparentPng();
}

// Normalize path - remove any URL or protocol prefixes
if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
    // Extract path from URL
    $urlParts = parse_url($path);
    if (isset($urlParts['path'])) {
        $path = $urlParts['path'];
    }
}

// Define the server's document root
$documentRoot = $_SERVER['DOCUMENT_ROOT'];

// For Windows paths that might come through, normalize them
$path = str_replace('\\', '/', $path);

// Remove any drive letter prefix (like C:/)
$path = preg_replace('/^[A-Za-z]:\//i', '', $path);

// If the path starts with /project/Gaurd, remove the leading slash
if (strpos($path, '/project/Gaurd') === 0) {
    $path = substr($path, 1);
}

// Construct the full server path
$fullPath = $documentRoot . '/' . $path;

// Debug
error_log("get_image.php: Attempting to load: $fullPath");

// Check if file exists and is readable
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    error_log("get_image.php: File not found or not readable: $fullPath");
    outputTransparentPng();
}

// Get file info to determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Verify it's an image
if (strpos($mimeType, 'image/') !== 0) {
    error_log("get_image.php: Not an image file: $fullPath ($mimeType)");
    outputTransparentPng();
}

// Set proper content type
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));

// Output the file
readfile($fullPath);
exit; 