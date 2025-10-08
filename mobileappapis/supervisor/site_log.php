<?php
// POST /api/supervisor/site-log
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/rate_limit.php';

$user = sup_get_authenticated_user();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rl_check('qr:' . $user->id . ':' . $ip, 20, 60);
$pdo = sup_get_db();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['qr_code'])) {
	sup_send_error_response('qr_code is required', 400);
}

$locationId = isset($body['location_id']) ? (int)$body['location_id'] : 0;
$qrCode = trim($body['qr_code']);
$action = isset($body['action']) ? strtolower(trim($body['action'])) : null;
$lat = isset($body['latitude']) ? (float)$body['latitude'] : null;
$lng = isset($body['longitude']) ? (float)$body['longitude'] : null;

if ($action !== null && !in_array($action, ['checkin', 'checkout'])) {
	sup_send_error_response('action must be checkin or checkout');
}

// Resolve location by QR code if location_id not provided
if ($locationId <= 0) {
	$resolved = null;
	// Attempt to parse JSON QR payload
	$json = json_decode($qrCode, true);
	if (is_array($json)) {
		if (!empty($json['id']) && ctype_digit((string)$json['id'])) {
			$qrResolve = $pdo->prepare("SELECT id, qr_code, latitude, longitude FROM society_onboarding_data WHERE id = ?");
			$qrResolve->execute([(int)$json['id']]);
			$resolved = $qrResolve->fetch(PDO::FETCH_ASSOC);
		} elseif (!empty($json['qrCodeId'])) {
			$qrResolve = $pdo->prepare("SELECT id, qr_code, latitude, longitude FROM society_onboarding_data WHERE qr_code = ?");
			$qrResolve->execute([$json['qrCodeId']]);
			$resolved = $qrResolve->fetch(PDO::FETCH_ASSOC);
		}
	}
	// Fallback: treat qr_code as stored value
	if (!$resolved) {
		$qrResolve = $pdo->prepare("SELECT id, qr_code, latitude, longitude FROM society_onboarding_data WHERE qr_code = ?");
		$qrResolve->execute([$qrCode]);
		$resolved = $qrResolve->fetch(PDO::FETCH_ASSOC);
	}
	if (!$resolved) {
		sup_send_error_response('QR code not recognized', 400);
	}
	$locationId = (int)$resolved['id'];
}

// Validate assignment
$assign = $pdo->prepare("SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?");
$assign->execute([$user->id, $locationId]);
if (!$assign->fetch()) {
	sup_send_error_response('You are not assigned to this location', 403);
}

// Validate QR authenticity and fetch site coords
$qrStmt = $pdo->prepare("SELECT id, qr_code, latitude, longitude FROM society_onboarding_data WHERE id = ?");
$qrStmt->execute([$locationId]);
$loc = $qrStmt->fetch(PDO::FETCH_ASSOC);
if (!$loc) {
	sup_send_error_response('Location not found for QR', 400);
}
// If qr is JSON, ensure either id matches or qrCodeId matches stored code when present
$qrJson = json_decode($qrCode, true);
if (is_array($qrJson) && !empty($qrJson['qrCodeId'])) {
	if (!hash_equals((string)$loc['qr_code'], (string)$qrJson['qrCodeId'])) {
		sup_send_error_response('QR does not match this location', 400);
	}
}
// If qr is plain string and not JSON, ensure it matches stored code when the column is populated
if (!is_array($qrJson) && !empty($loc['qr_code']) && !hash_equals((string)$loc['qr_code'], (string)$qrCode)) {
	sup_send_error_response('Invalid QR code for this location', 400);
}

// Enforce GPS presence and 100m geofence
if ($lat === null || $lng === null) {
	sup_send_error_response('GPS coordinates are required for QR scan', 400);
}

// Haversine distance calculation in meters
$toRadians = function($deg) { return $deg * (pi() / 180); };
$earthRadius = 6371000; // meters
$dLat = $toRadians($lat - (float)$loc['latitude']);
$dLng = $toRadians($lng - (float)$loc['longitude']);
$a = sin($dLat/2) * sin($dLat/2) + cos($toRadians((float)$loc['latitude'])) * cos($toRadians($lat)) * sin($dLng/2) * sin($dLng/2);
$c = 2 * atan2(sqrt($a), sqrt(1-$a));
$distanceMeters = $earthRadius * $c;
if ($distanceMeters > 100) {
	sup_send_error_response('You are not at the site location (beyond 100m)', 400);
}

// Enforce single active session per supervisor
$active = $pdo->prepare("SELECT id, location_id FROM supervisor_site_logs WHERE supervisor_id = ? AND active_session = 1 ORDER BY id DESC LIMIT 1");
$active->execute([$user->id]);
$activeRow = $active->fetch(PDO::FETCH_ASSOC);

// Auto-decide action if not provided
if ($action === null) {
	$action = $activeRow ? 'checkout' : 'checkin';
}

if ($action === 'checkin') {
	if ($activeRow) {
		sup_send_error_response('Already checked in at another location. Please checkout first.', 409);
	}
	$pdo->beginTransaction();
	try {
		$ins = $pdo->prepare("INSERT INTO supervisor_site_logs (supervisor_id, location_id, action, latitude, longitude, active_session) VALUES (?,?,?,?,?,1)");
		$ins->execute([$user->id, $locationId, 'checkin', $lat, $lng]);

		// Create visit row
		$visitIns = $pdo->prepare("INSERT INTO supervisor_site_visits (supervisor_id, location_id, checkin_at, checkin_latitude, checkin_longitude) VALUES (?,?,?,?,?)");
		$visitIns->execute([$user->id, $locationId, date('Y-m-d H:i:s'), $lat, $lng]);

		$pdo->commit();
		sup_send_json_response(['success' => true, 'message' => 'Checked in successfully', 'location_id' => $locationId]);
	} catch (Exception $e) {
		$pdo->rollBack();
		sup_send_error_response('Check-in failed: ' . $e->getMessage(), 500);
	}
}

// checkout flow
if (!$activeRow) {
	sup_send_error_response('No active session to checkout from', 409);
}
if ((int)$activeRow['location_id'] !== $locationId) {
	sup_send_error_response('You must checkout from the same location you checked in to', 409);
}

$pdo->beginTransaction();
try {
	$ins = $pdo->prepare("INSERT INTO supervisor_site_logs (supervisor_id, location_id, action, latitude, longitude, active_session) VALUES (?,?,?,?,?,0)");
	$ins->execute([$user->id, $locationId, 'checkout', $lat, $lng]);
	$upd = $pdo->prepare("UPDATE supervisor_site_logs SET active_session = 0 WHERE id = ?");
	$upd->execute([$activeRow['id']]);

	// Update visit row
	$visitSel = $pdo->prepare("SELECT id, checkin_at FROM supervisor_site_visits WHERE supervisor_id = ? AND location_id = ? AND checkout_at IS NULL ORDER BY id DESC LIMIT 1");
	$visitSel->execute([$user->id, $locationId]);
	$visit = $visitSel->fetch(PDO::FETCH_ASSOC);
	if ($visit) {
		$checkoutAt = date('Y-m-d H:i:s');
		$duration = max(0, round((strtotime($checkoutAt) - strtotime($visit['checkin_at'])) / 60));
		$visitUpd = $pdo->prepare("UPDATE supervisor_site_visits SET checkout_at = ?, duration_minutes = ?, checkout_latitude = ?, checkout_longitude = ? WHERE id = ?");
		$visitUpd->execute([$checkoutAt, $duration, $lat, $lng, $visit['id']]);
	}

	$pdo->commit();
	sup_send_json_response(['success' => true, 'message' => 'Checked out successfully', 'location_id' => $locationId]);
} catch (Exception $e) {
	$pdo->rollBack();
	sup_send_error_response('Checkout failed: ' . $e->getMessage(), 500);
}


