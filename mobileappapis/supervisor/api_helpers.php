<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Global JSON error handling for Supervisor APIs
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error', 'code' => 'SERVER_ERROR']);
    @error_log('[SUPERVISOR_API][EXCEPTION] ' . $e->getMessage());
});

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error', 'code' => 'SERVER_ERROR']);
    @error_log('[SUPERVISOR_API][ERROR] ' . $message . ' at ' . $file . ':' . $line);
    return true;
});

// Server timezone and target app timezone
if (!defined('SUP_SERVER_TZ')) {
	// Server is UTC+2 as per deployment note
	define('SUP_SERVER_TZ', '+02:00');
}
if (!defined('SUP_APP_TZ')) {
	define('SUP_APP_TZ', 'Asia/Kolkata');
}

/**
 * Convert a database datetime string from server TZ to app TZ (IST)
 * Returns 'Y-m-d H:i:s' string or original if conversion fails.
 */
function sup_convert_to_app_tz($datetimeStr, $fromTz = SUP_SERVER_TZ, $toTz = SUP_APP_TZ) {
	if (empty($datetimeStr)) { return $datetimeStr; }
	try {
		$srcTz = new DateTimeZone($fromTz);
		$dstTz = new DateTimeZone($toTz);
		$dt = new DateTime($datetimeStr, $srcTz);
		$dt->setTimezone($dstTz);
		return $dt->format('Y-m-d H:i:s');
	} catch (Throwable $e) {
		return $datetimeStr;
	}
}

function sup_send_json_response($data, $statusCode = 200) {
	http_response_code($statusCode);
	header('Content-Type: application/json');
	echo json_encode($data);
	exit;
}

function sup_send_error_response($message, $statusCode = 400) {
	sup_send_json_response(['success' => false, 'message' => $message], $statusCode);
}

function sup_get_authenticated_user() {
	$config = require __DIR__ . '/../../config.php';

	$authHeader = null;
	if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
		$authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
	}
	if (!$authHeader && function_exists('getallheaders')) {
		$headers = array_change_key_case(getallheaders(), CASE_LOWER);
		$authHeader = $headers['authorization'] ?? null;
	}
	if (!$authHeader) {
		sup_send_error_response('Authorization header not found.', 401);
	}

	list($jwt) = sscanf($authHeader, 'Bearer %s');
	if (!$jwt) {
		sup_send_error_response('Malformed authorization header.', 401);
	}

	try {
		// allow minor clock skew
		if (property_exists('Firebase\\JWT\\JWT', 'leeway')) { \Firebase\JWT\JWT::$leeway = 120; }
		$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
		$user = $decoded->data;

		// Blacklist check (ignore if table is missing)
		require_once __DIR__ . '/../../helpers/database.php';
		$pdo = get_db_connection();
		try {
			$bl = $pdo->prepare('SELECT 1 FROM jwt_blacklist WHERE jti = ? AND expires_at > NOW()');
			$bl->execute([hash('sha256', $jwt)]);
			if ($bl->fetch()) {
				sup_send_error_response('Token is revoked.', 401);
			}
		} catch (Throwable $t) {
			@error_log('[SUPERVISOR_API][JWT_BLACKLIST_SKIP] ' . $t->getMessage());
		}
		if (!isset($user->id) || !isset($user->user_type)) {
			sup_send_error_response('Invalid token payload.', 401);
		}
		// Only allow supervisors and related roles
		$allowed = ['Supervisor', 'Site Supervisor', 'Area Manager', 'Manager'];
		if (!in_array($user->user_type, $allowed)) {
			sup_send_error_response('Forbidden: role not allowed.', 403);
		}
		return $user;
	} catch (Exception $e) {
		sup_send_error_response('Invalid or expired token: ' . $e->getMessage(), 401);
	}
}

function sup_get_db() {
	require_once __DIR__ . '/../../helpers/database.php';
	return get_db_connection();
}


