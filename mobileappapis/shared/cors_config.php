<?php
/**
 * Universal CORS Configuration for Mobile Apps
 * This file provides comprehensive CORS headers for all API endpoints
 * Specifically designed to work with local XAMPP and development servers
 */

function setCorsHeaders() {
    // Handle preflight OPTIONS request first
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        // Set comprehensive CORS headers for preflight
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-Type, Accept, Origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // 24 hours
        header("Content-Length: 0");
        header("Content-Type: text/plain");
        http_response_code(200);
        exit(0);
    }

    // Set main CORS headers for actual requests
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-Type, Accept, Origin");
    header("Access-Control-Allow-Credentials: true");
    
    // Additional headers for mobile app compatibility
    header("Access-Control-Expose-Headers: Authorization, Content-Length, X-Kuma-Revision");
    
    // Set JSON content type
    header("Content-Type: application/json; charset=UTF-8");
    
    // Prevent caching of API responses (important for authentication)
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

/**
 * Alternative CORS setup for environments with specific origin requirements
 */
function setCorsHeadersWithOrigin() {
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-Type, Accept, Origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");
        http_response_code(200);
        exit(0);
    }

    // Set main CORS headers
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-Type, Accept, Origin");
    header("Content-Type: application/json; charset=UTF-8");
}

/**
 * Debug function to log CORS information
 */
function debugCorsHeaders() {
    error_log("=== CORS DEBUG INFO ===");
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Origin Header: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Not set'));
    error_log("Access-Control-Request-Method: " . ($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'Not set'));
    error_log("Access-Control-Request-Headers: " . ($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Not set'));
    error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set'));
    error_log("======================");
}

/**
 * Check if request is from mobile app
 */
function isMobileAppRequest() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (
        strpos($userAgent, 'Dart') !== false ||
        strpos($userAgent, 'Flutter') !== false ||
        strpos($userAgent, 'okhttp') !== false ||
        isset($_SERVER['HTTP_X_REQUEST_TYPE'])
    );
}

/**
 * Enhanced CORS setup specifically for mobile apps
 */
function setMobileAppCorsHeaders() {
    // Always allow all origins for mobile apps
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-Type, Accept, Origin, User-Agent");
    header("Access-Control-Allow-Credentials: false"); // Set to false when using wildcard origin
    header("Access-Control-Max-Age: 86400");
    
    // Handle OPTIONS preflight
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
    
    header("Content-Type: application/json; charset=UTF-8");
}
?>


















