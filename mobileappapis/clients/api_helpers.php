<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function send_error_response($message, $statusCode = 400) {
    send_json_response(['success' => false, 'message' => $message], $statusCode);
}

function get_authenticated_user_data() {
    $config = require __DIR__ . '/../../config.php';

    $authHeader = null;

    // 1. Check the server superglobal (most web servers expose it here).
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    // 2. Fallback to getallheaders() – normalise header keys to be case-insensitive.
    if (!$authHeader && function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        // In lowercase form the key will always be 'authorization'.
        $authHeader = $headers['authorization'] ?? null;
    }

    if (!$authHeader) {
        send_error_response('Authorization header not found.', 401);
    }

    list($jwt) = sscanf($authHeader, 'Bearer %s');

    if (!$jwt) {
        send_error_response('Malformed authorization header.', 401);
    }

    try {
        $decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
        $user_data = $decoded->data;

        // Basic validation of the token payload
        if (!isset($user_data->id) || !isset($user_data->role) || !isset($user_data->society)) {
            send_error_response('Invalid token payload.', 401);
        }
        
        return $user_data;

    } catch (Exception $e) {
        send_error_response('Invalid or expired token: ' . $e->getMessage(), 401);
    }
}
