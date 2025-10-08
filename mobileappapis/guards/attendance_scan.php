<?php
// mobileappapis/guards/attendance_scan.php
// Single endpoint: toggles check-in or check-out based on active session

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../vendor/autoload.php';
require_once '../../helpers/database.php';
require_once '../../helpers/ConnectionPool.php';
$config = require_once '../../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_bearer_token(): ?string {
	$headers = function_exists('getallheaders') ? getallheaders() : [];
	$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
	if (stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
	return null;
}

function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
	$earth = 6371; // km
	$dLat = deg2rad($lat2 - $lat1);
	$dLon = deg2rad($lon2 - $lon1);
	$a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
	$c = 2 * atan2(sqrt($a), sqrt(1-$a));
	return $earth * $c;
}

function has_column(PDO $pdo, string $table, string $column): bool {
	try {
		$st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
		$st->execute([$column]);
		return $st->rowCount() > 0;
	} catch (Throwable $e) { return false; }
}

function audit_log(string $level, array $data): void {
	$line = json_encode(['ts' => date('c'), 'level' => $level] + $data) . PHP_EOL;
	@file_put_contents(__DIR__ . '/../../logs/attendance_scan.log', $line, FILE_APPEND);
}

try {
	$jwt = get_bearer_token();
	if (!$jwt) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

	$pdo = ConnectionPool::getConnection();
	$tz = new DateTimeZone('Asia/Kolkata');
	$input = json_decode(file_get_contents('php://input'), true);
	if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

	$qrCodeId = trim($input['qrCodeId'] ?? '');
	$societyId = (int)($input['client_id'] ?? 0);
	$lat = isset($input['lat']) ? (float)$input['lat'] : null;
	$lng = isset($input['lng']) ? (float)$input['lng'] : null;
	$timestamp = isset($input['timestamp']) ? (int)$input['timestamp'] : time();

	if ($qrCodeId === '' || $societyId <= 0 || $lat === null || $lng === null) {
		http_response_code(400); echo json_encode(['error' => 'qrCodeId, client_id, lat, lng are required']); exit;
	}

	// Validate society and get canonical lat/lng and stored qr_code value
	$stSoc = $pdo->prepare('SELECT id, society_name, latitude, longitude, qr_code FROM society_onboarding_data WHERE id = ? LIMIT 1');
	$stSoc->execute([$societyId]);
	$soc = $stSoc->fetch(PDO::FETCH_ASSOC);
	if (!$soc) { http_response_code(404); echo json_encode(['error' => 'Location not found']); exit; }

    // Distance config & QR expiry tolerance
    $distanceMeters = 500; $toleranceMeters = 100; // defaults
    if (has_column($pdo, 'society_onboarding_data', 'distance_meters')) {
        $stm = $pdo->prepare('SELECT distance_meters FROM society_onboarding_data WHERE id = ?');
        $stm->execute([$societyId]);
        $dm = (int)($stm->fetchColumn() ?: 0);
        if ($dm > 0) { $distanceMeters = $dm; }
    }
    if (has_column($pdo, 'society_onboarding_data', 'qr_expires_at')) {
        $stm = $pdo->prepare('SELECT qr_expires_at FROM society_onboarding_data WHERE id = ?');
        $stm->execute([$societyId]);
        $qrExp = $stm->fetchColumn();
        if (!empty($qrExp)) {
            $exp = new DateTime($qrExp, $tz);
            $nowIstX = (new DateTime('now', $tz));
            if ($nowIstX > $exp) { http_response_code(403); echo json_encode(['error' => 'This QR code has expired.']); exit; }
        }
    }

	// Validate distance (use server lat/lng for security)
	$distKm = haversine_km($lat, $lng, (float)$soc['latitude'], (float)$soc['longitude']);
	$allowedKm = ($distanceMeters + $toleranceMeters) / 1000.0;
	if ($distKm > $allowedKm) { http_response_code(403); echo json_encode(['error' => 'You are not at the location. Please visit the location and try again.']); exit; }

	// Optional: Validate qr code id if stored
	if (!empty($soc['qr_code']) && $soc['qr_code'] !== $qrCodeId) {
		http_response_code(403); echo json_encode(['error' => 'Invalid QR code for this location.']); exit; }

	// Start transaction and lock open sessions
	$pdo->beginTransaction();
	$colsAttendance = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);
	$hasQrRefCol = in_array('qr_scan_reference', $colsAttendance, true);
	$hasCheckInCol = in_array('check_in_time', $colsAttendance, true);
	$hasCreatedAtCol = in_array('created_at', $colsAttendance, true);
	$hasShiftStartColSel = in_array('shift_start', $colsAttendance, true);
	$timeSelect = $hasCheckInCol 
		? 'check_in_time AS time_col' 
		: ($hasShiftStartColSel 
			? 'shift_start AS time_col' 
			: ($hasCreatedAtCol ? 'created_at AS time_col' : 'id AS time_col'));
	$selectColsArr = ['id', 'society_id'];
	if ($hasQrRefCol) { $selectColsArr[] = 'qr_scan_reference'; }
	$selectColsArr[] = $timeSelect;
	$selectCols = implode(', ', $selectColsArr);
	// find checkout column
	$checkoutCandidates = ['check_out_time','checkout_time','time_out','logout_time'];
	$checkoutCol = null;
	foreach ($checkoutCandidates as $cand) { if (in_array($cand, $colsAttendance, true)) { $checkoutCol = $cand; break; } }
	$open = [];
	$active = null;
	if ($checkoutCol) {
		$stActive = $pdo->prepare("SELECT $selectCols FROM attendance WHERE user_id = ? AND `$checkoutCol` IS NULL FOR UPDATE");
		$stActive->execute([$userId]);
		$open = $stActive->fetchAll(PDO::FETCH_ASSOC);
		$active = $open[0] ?? null;
		if (count($open) > 1) {
			usort($open, function($a,$b){ return strcmp((string)($b['time_col'] ?? ''), (string)($a['time_col'] ?? '')); });
			$keep = $open[0];
			for ($i=1;$i<count($open);$i++) {
				$pdo->prepare("UPDATE attendance SET `$checkoutCol` = NOW() WHERE id = ?")
					->execute([$open[$i]['id']]);
			}
			$active = $keep;
			audit_log('warn', ['user_id'=>$userId,'event'=>'auto_close_multi_open','kept'=>$keep['id'],'closed'=>count($open)-1]);
		}
	} else if (in_array('shift_end', $colsAttendance, true)) {
		// Fallback: treat shift_end NULL as open session
		$stActive = $pdo->prepare("SELECT $selectCols FROM attendance WHERE user_id = ? AND shift_end IS NULL FOR UPDATE");
		$stActive->execute([$userId]);
		$open = $stActive->fetchAll(PDO::FETCH_ASSOC);
		$active = $open[0] ?? null;
		if (count($open) > 1) {
			usort($open, function($a,$b){ return strcmp((string)($b['time_col'] ?? ''), (string)($a['time_col'] ?? '')); });
			$keep = $open[0];
			for ($i=1;$i<count($open);$i++) {
				$pdo->prepare("UPDATE attendance SET shift_end = NOW() WHERE id = ?")
					->execute([$open[$i]['id']]);
			}
			$active = $keep;
			audit_log('warn', ['user_id'=>$userId,'event'=>'auto_close_multi_open_fallback_shift_end','kept'=>$keep['id'],'closed'=>count($open)-1]);
		}
	}

	if ($active) {
		// Checkout branch: requires same QR
		if ($hasQrRefCol && (($active['qr_scan_reference'] ?? null) !== $qrCodeId)) {
			http_response_code(403); echo json_encode(['error' => 'You must logout from the same location.']); exit;
		}
		// Enforce minimum gap to avoid instant double-scan logout
		$minGapMinutes = 10; // configurable if needed
		$timeColVal = $active['time_col'] ?? null;
		if (!empty($timeColVal)) {
			try {
				$startDt = new DateTime($timeColVal, $tz);
				$nowDt = new DateTime('now', $tz);
				$diffMin = (int)floor(($nowDt->getTimestamp() - $startDt->getTimestamp()) / 60);
				if ($diffMin < $minGapMinutes) {
					$pdo->rollBack();
					http_response_code(429);
					echo json_encode(['error' => 'It looks like you just logged in recently. Please try again later.']);
					exit;
				}
			} catch (Throwable $e) { /* ignore parse issues */ }
		}
		// Re-check proximity at checkout
		$distKm2 = haversine_km($lat, $lng, (float)$soc['latitude'], (float)$soc['longitude']);
		if ($distKm2 > $allowedKm) { $pdo->rollBack(); http_response_code(403); echo json_encode(['error' => 'You are not at the location. Please visit the location and try again.']); exit; }
		$checkoutAtIst = (new DateTime('now', $tz))->format('Y-m-d H:i:s');
		// dynamic update fields
		$setParts = [];
		$paramsUpd = [];
		if ($checkoutCol) { $setParts[] = "`$checkoutCol` = ?"; $paramsUpd[] = $checkoutAtIst; }
		else if (in_array('shift_end', $colsAttendance, true)) { $setParts[] = 'shift_end = ?'; $paramsUpd[] = $checkoutAtIst; }
		if (in_array('check_out_latitude', $colsAttendance, true)) { $setParts[] = 'check_out_latitude = ?'; $paramsUpd[] = $lat; }
		if (in_array('check_out_longitude', $colsAttendance, true)) { $setParts[] = 'check_out_longitude = ?'; $paramsUpd[] = $lng; }
		if (in_array('check_out_method', $colsAttendance, true)) { $setParts[] = "check_out_method = 'Mobile App'"; }
		if (in_array('source', $colsAttendance, true)) { $setParts[] = "source = 'Mobile'"; }
		if (in_array('updated_at', $colsAttendance, true)) { $setParts[] = 'updated_at = NOW()'; }
		$setSql = implode(', ', $setParts);
		$upd = $pdo->prepare("UPDATE attendance SET $setSql WHERE id = ?");
		$paramsUpd[] = $active['id'];
		$upd->execute($paramsUpd);
		$pdo->commit();
		audit_log('info', ['user_id'=>$userId,'event'=>'checkout','attendance_id'=>$active['id'],'society_id'=>$societyId,'distance_km'=>$distKm2]);
		echo json_encode([
			'success' => true,
			'mode' => 'checkout',
			'message' => 'Logged out successfully.',
			'data' => [ 'attendance_id' => (int)$active['id'] ]
		]);
		exit;
	}

	// No active session: Check-in branch
	// Validate roster assignment  
	$stRoster = $pdo->prepare('SELECT r.shift_id, sm.start_time, sm.end_time FROM roster r JOIN shift_master sm ON sm.id = r.shift_id WHERE r.guard_id = ? AND r.society_id = ? ORDER BY sm.start_time');
	$stRoster->execute([$userId, $societyId]);
	$rosterRows = $stRoster->fetchAll(PDO::FETCH_ASSOC);
	if (!$rosterRows) { $pdo->rollBack(); http_response_code(403); echo json_encode(['error' => "You're not assigned to this location."]); exit; }
	$nowIst = new DateTime('now', $tz);
	$overlapCount = 0; $roster = null;
	foreach ($rosterRows as $rr) {
		$ss = new DateTime($nowIst->format('Y-m-d') . ' ' . ($rr['start_time'] ?? '00:00:00'), $tz);
		$se = new DateTime($nowIst->format('Y-m-d') . ' ' . ($rr['end_time'] ?? '23:59:59'), $tz);
		if (strcmp($rr['end_time'] ?? '','') !== 0 && strcmp($rr['end_time'],$rr['start_time']) <= 0) { $se->modify('+1 day'); }
		if ($nowIst >= $ss && $nowIst <= $se) { $overlapCount++; $roster = $rr; }
	}
	if ($overlapCount > 1) { $pdo->rollBack(); http_response_code(409); echo json_encode(['error' => 'Overlapping shifts detected. Please contact admin.']); exit; }
	if ($roster === null) { $roster = $rosterRows[0]; }

	// Shift time validation with 60-minute grace window (IST) incl. cross-midnight
	$todayIst = $nowIst->format('Y-m-d');
	$startStr = $roster['start_time'] ?? '00:00:00';
	$endStr = $roster['end_time'] ?? '23:59:59';
	$shiftStart = new DateTime($todayIst . ' ' . $startStr, $tz);
	$shiftEnd = new DateTime($todayIst . ' ' . $endStr, $tz);
	// Cross-midnight: if end <= start, add 1 day to end
	if (strcmp($endStr, $startStr) <= 0) { $shiftEnd->modify('+1 day'); }
	$graceMinutes = 60;
	if (has_column($pdo, 'shift_master', 'grace_minutes')) {
		$stm = $pdo->prepare('SELECT grace_minutes FROM shift_master WHERE start_time = ? AND end_time = ? LIMIT 1');
		$stm->execute([$startStr,$endStr]);
		$gm = (int)($stm->fetchColumn() ?: 0);
		if ($gm > 0) { $graceMinutes = $gm; }
	}
	$graceStart = (clone $shiftStart)->modify('-' . $graceMinutes . ' minutes');
	$graceEnd = (clone $shiftEnd)->modify('+' . $graceMinutes . ' minutes');
	if ($nowIst < $graceStart || $nowIst > $graceEnd) { http_response_code(403); echo json_encode(['error' => 'Shift time mismatch. Cannot check-in now.']); exit; }

	// Create attendance row (IST date/time). Detect columns.
	$attCols = $colsAttendance;
	$hasDateCol = in_array('date', $attCols, true); $hasAttendanceDateCol = in_array('attendance_date', $attCols, true);
	$hasShiftId = in_array('shift_id', $attCols, true); $hasShiftStartCol = in_array('shift_start', $attCols, true); $hasShiftEndCol = in_array('shift_end', $attCols, true);
	$hasAttMasterId = in_array('attendance_master_id', $attCols, true);
	$hasInTimeCol = in_array('check_in_time', $attCols, true);
	$hasStatusCol = in_array('status', $attCols, true);
	$hasInLatCol = in_array('check_in_latitude', $attCols, true);
	$hasInLngCol = in_array('check_in_longitude', $attCols, true);
	$hasInMethodCol = in_array('check_in_method', $attCols, true);
	$hasCreatedAtCol2 = in_array('created_at', $attCols, true);
	$hasUpdatedAtCol2 = in_array('updated_at', $attCols, true);
	$checkInAtIst = $nowIst->format('Y-m-d H:i:s');
	$attDateIst = $nowIst->format('Y-m-d');
	$amId = null;
	if ($hasAttMasterId) {
		$stAm = $pdo->prepare("SELECT id FROM attendance_master WHERE code = 'P' LIMIT 1");
		$stAm->execute(); $amId = $stAm->fetchColumn() ?: null;
	}
	// Prevent multiple entries for same date and same shift
	$dupFound = false;
	if ($hasShiftId && ($hasDateCol || $hasAttendanceDateCol)) {
		$dateColName = $hasDateCol ? 'date' : 'attendance_date';
		$stDup = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND `$dateColName` = ? AND shift_id = ? LIMIT 1");
		$stDup->execute([$userId, $attDateIst, (int)$roster['shift_id']]);
		$dupFound = (bool)$stDup->fetchColumn();
	} elseif ($hasDateCol || $hasAttendanceDateCol) {
		$dateColName = $hasDateCol ? 'date' : 'attendance_date';
		$stDup = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND `$dateColName` = ? LIMIT 1");
		$stDup->execute([$userId, $attDateIst]);
		$dupFound = (bool)$stDup->fetchColumn();
	} elseif ($hasCreatedAtCol2) {
		$stDup = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND DATE(created_at) = ? LIMIT 1");
		$stDup->execute([$userId, $attDateIst]);
		$dupFound = (bool)$stDup->fetchColumn();
	}
	if ($dupFound) { $pdo->rollBack(); http_response_code(409); echo json_encode(['error' => 'Attendance already marked for this shift today.']); exit; }
	$fields = ['user_id','society_id'];
	$place  = ['?','?'];
	$params = [$userId,$societyId];
	if ($hasDateCol) { $fields[]='date'; $place[]='?'; $params[]=$attDateIst; }
	if ($hasAttendanceDateCol) { $fields[]='attendance_date'; $place[]='?'; $params[]=$attDateIst; }
	if ($hasInTimeCol) { $fields[]='check_in_time'; $place[]='?'; $params[]=$checkInAtIst; }
	if ($hasStatusCol) { $fields[]='status'; $place[]='?'; $params[]='Present'; }
	if ($hasInLatCol) { $fields[]='check_in_latitude'; $place[]='?'; $params[]=$lat; }
	if ($hasInLngCol) { $fields[]='check_in_longitude'; $place[]='?'; $params[]=$lng; }
	if ($hasInMethodCol) { $fields[]='check_in_method'; $place[]='?'; $params[]='Mobile App'; }
	if ($hasCreatedAtCol2) { $fields[]='created_at'; $place[]='NOW()'; }
	if ($hasUpdatedAtCol2) { $fields[]='updated_at'; $place[]='NOW()'; }
	if ($hasShiftId) { $fields[]='shift_id'; $place[]='?'; $params[]=(int)$roster['shift_id']; }
	// Set shift_start to system time only if check_in_time column does not exist
	if (!$hasInTimeCol && $hasShiftStartCol) { $fields[]='shift_start'; $place[]='?'; $params[]=$nowIst->format('H:i:s'); }
	// Do not set shift_end at check-in
	if (in_array('qr_scan_reference', $attCols, true)) { $fields[]='qr_scan_reference'; $place[]='?'; $params[]=$qrCodeId; }
	if ($hasAttMasterId && $amId) { $fields[]='attendance_master_id'; $place[]='?'; $params[]=(int)$amId; }
	if (in_array('source', $attCols, true)) { $fields[]='source'; $place[]='?'; $params[]='Mobile'; }
	$sqlIns = 'INSERT INTO attendance (' . implode(',', $fields) . ') VALUES (' . implode(',', $place) . ')';
	$ins = $pdo->prepare($sqlIns);
	$ins->execute($params);
	$attendanceId = (int)$pdo->lastInsertId();
	$pdo->commit();
	audit_log('info', ['user_id'=>$userId,'event'=>'checkin','attendance_id'=>$attendanceId,'society_id'=>$societyId,'distance_km'=>$distKm]);

	echo json_encode([
		'success' => true,
		'mode' => 'checkin',
		'message' => 'Logged in successfully.',
		'data' => [ 'attendance_id' => $attendanceId ]
	]);
} catch (Throwable $e) {
	// Log the actual error for debugging
	audit_log('error', ['event' => 'attendance_scan_error', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
	
	// Return clean error message for production
	http_response_code(500);
	echo json_encode(['error' => 'An error occurred. Please try again.']);
}


