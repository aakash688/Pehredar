<?php
/**
 * JSON Helper Functions
 * 
 * This file contains helper functions for working with JSON data
 */

/**
 * Send a JSON response and exit
 * 
 * @param mixed $data The data to send as JSON
 * @param int $status_code HTTP status code
 * @return void
 */
function json_response($data, $status_code = 200) {
    // Set HTTP response code
    http_response_code($status_code);
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Parse JSON input from request body
 * 
 * @param bool $associative Whether to return objects as associative arrays
 * @return mixed Parsed JSON data or NULL on failure
 */
function json_input($associative = true) {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        return json_decode($input, $associative);
    }
    return null;
}

/**
 * Send a standardized JSON response
 * 
 * @param bool $success Whether the operation was successful
 * @param string $message Response message
 * @param mixed $data Optional data to include in response
 * @param int $status_code HTTP status code
 * @return void
 */
function sendJsonResponse($success, $message, $data = null, $status_code = 200) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    json_response($response, $status_code);
} 