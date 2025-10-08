<?php
// helpers/jwt_helper.php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * A central place to get the decoded JWT from the cookie.
 * @return object|null The decoded JWT payload or null if not found/invalid.
 */
function get_decoded_jwt() {
    global $config;
    static $decoded_jwt = null;
    static $was_checked = false;

    if ($was_checked) {
        return $decoded_jwt;
    }

    if (isset($_COOKIE['jwt'])) {
        try {
            $decoded_jwt = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
        } catch (Exception $e) {
            $decoded_jwt = null;
        }
    }
    
    $was_checked = true;
    return $decoded_jwt;
}

/**
 * Checks if the user is currently authenticated.
 * @return bool
 */
function is_authenticated() {
    return get_decoded_jwt() !== null;
}

/**
 * Checks if the authenticated user is an Admin.
 * @return bool
 */
function is_admin() {
    $jwt = get_decoded_jwt();
    return $jwt && isset($jwt->data->role) && $jwt->data->role === 'Admin';
}

/**
 * Gets the full name from the JWT payload data.
 * @param object $data The 'data' object from the JWT payload.
 * @return string
 */
function get_user_full_name($data) {
    return $data->full_name ?? 'Guest';
}

/**
 * Gets the profile photo URL from the JWT payload data.
 * @param object $data The 'data' object from the JWT payload.
 * @return string
 */
function get_user_profile_photo($data) {
    return $data->profile_photo ?? 'https://i.pravatar.cc/100';
}

/**
 * Fetches updated user data from DB to refresh an old token.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $user_role
 * @return array|null
 */
function get_refreshed_user_data(PDO $pdo, $user_id, $user_role) {
    if ($user_role === 'Client') {
        $stmt = $pdo->prepare("SELECT name, profile_photo FROM clients_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user ? ['full_name' => $user['name'], 'profile_photo' => $user['profile_photo']] : null;
    } else {
        $stmt = $pdo->prepare("SELECT first_name, surname, profile_photo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user ? ['full_name' => $user['first_name'] . ' ' . $user['surname'], 'profile_photo' => $user['profile_photo']] : null;
    }
} 