<?php
// GET /api/supervisor/activities
// POST /api/supervisor/activities
// POST /api/supervisor/activities/{id}/assign
// POST /api/supervisor/activities/{id}/complete
// PATCH /api/supervisor/activities/{id}
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    exit;
}

require_once __DIR__ . '/api_helpers.php';

$user = sup_get_authenticated_user();
$pdo = sup_get_db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['REQUEST_URI'] ?? '';

// Also support paths like activities.php/123
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (!$pathInfo && isset($_SERVER['SCRIPT_NAME']) && strpos($path, basename(__FILE__) . '/') !== false) {
    $pathInfo = substr($path, strpos($path, basename(__FILE__)) + strlen(basename(__FILE__)));
}

// Helpers
function ensure_location_assigned($pdo, $userId, $locationId) {
	$stmt = $pdo->prepare("SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?");
	$stmt->execute([$userId, $locationId]);
	if (!$stmt->fetch()) {
		sup_send_error_response('Location not assigned to supervisor', 403);
	}
}

// Get single activity by id (pretty route)
if ($method === 'GET' && preg_match('#/activities/(\d+)$#', $path, $m)) {
    $activityId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT a.id, a.title, a.description, a.scheduled_date, a.status, a.society_id AS location_id, a.created_at, s.society_name AS location,
                                   EXISTS(SELECT 1 FROM activity_assignees aa WHERE aa.activity_id = a.id AND aa.user_id = ?) AS assigned_to_me
                           FROM activities a JOIN society_onboarding_data s ON s.id = a.society_id WHERE a.id = ?");
    $stmt->execute([$user->id, $activityId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$row['location_id']);
    // photos
    $pstmt = $pdo->prepare('SELECT id, image_url FROM activity_photos WHERE activity_id = ? ORDER BY created_at DESC LIMIT 50');
    $pstmt->execute([$activityId]);
    $photos = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    $row['photos'] = array_map(function($p){ return ['id' => (int)$p['id'], 'image_url' => $p['image_url']]; }, $photos);
    // assignees
    $astmt = $pdo->prepare("SELECT aa.user_id AS id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type FROM activity_assignees aa JOIN users u ON u.id = aa.user_id WHERE aa.activity_id = ? ORDER BY name ASC");
    $astmt->execute([$activityId]);
    $row['assignees'] = $astmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'activity' => $row]);
    exit;
}

// Get single activity by id (php pathinfo)
if ($method === 'GET' && $pathInfo && preg_match('#^/?(\d+)$#', $pathInfo, $m)) {
    $activityId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT a.id, a.title, a.description, a.scheduled_date, a.status, a.society_id AS location_id, a.created_at, s.society_name AS location,
                                   EXISTS(SELECT 1 FROM activity_assignees aa WHERE aa.activity_id = a.id AND aa.user_id = ?) AS assigned_to_me
                           FROM activities a JOIN society_onboarding_data s ON s.id = a.society_id WHERE a.id = ?");
    $stmt->execute([$user->id, $activityId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$row['location_id']);
    $pstmt = $pdo->prepare('SELECT id, image_url FROM activity_photos WHERE activity_id = ? ORDER BY created_at DESC LIMIT 50');
    $pstmt->execute([$activityId]);
    $photos = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    $row['photos'] = array_map(function($p){ return ['id' => (int)$p['id'], 'image_url' => $p['image_url']]; }, $photos);
    $astmt = $pdo->prepare("SELECT aa.user_id AS id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type FROM activity_assignees aa JOIN users u ON u.id = aa.user_id WHERE aa.activity_id = ? ORDER BY name ASC");
    $astmt->execute([$activityId]);
    $row['assignees'] = $astmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'activity' => $row]);
    exit;
}

// Fallback: query param id
if ($method === 'GET' && isset($_GET['id'])) {
    $activityId = (int)$_GET['id'];
    if ($activityId > 0) {
        $stmt = $pdo->prepare("SELECT a.id, a.title, a.description, a.scheduled_date, a.status, a.society_id AS location_id FROM activities a WHERE a.id = ?");
        $stmt->execute([$activityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { sup_send_error_response('Activity not found', 404); }
        ensure_location_assigned($pdo, $user->id, (int)$row['location_id']);
        echo json_encode(['success' => true, 'activity' => $row]);
        exit;
    }
}

if ($method === 'GET') {
	// list with optional filters
	$where = [];
	$params = [];
	$search = null;
	if (!empty($_GET['location_id'])) {
		$where[] = 'a.society_id = ?';
		$params[] = (int)$_GET['location_id'];
	}
	if (!empty($_GET['status'])) {
		$where[] = 'a.status = ?';
		$params[] = $_GET['status'];
	}
	if (!empty($_GET['q'])) {
		$search = '%' . $_GET['q'] . '%';
		$where[] = '(a.title LIKE ? OR a.description LIKE ?)';
		$params[] = $search;
		$params[] = $search;
	}

	// Restrict to assigned locations
	$where[] = 'a.society_id IN (SELECT site_id FROM supervisor_site_assignments WHERE supervisor_id = ?)';
	$params[] = (int)$user->id;

	$sql = "SELECT a.id, a.title, a.description, a.scheduled_date, a.status,
	               a.society_id AS location_id,
	               s.society_name AS location,
	               EXISTS(SELECT 1 FROM activity_assignees aa WHERE aa.activity_id = a.id AND aa.user_id = ?) AS assigned_to_me
		FROM activities a
		JOIN society_onboarding_data s ON s.id = a.society_id
		WHERE " . implode(' AND ', $where) . "
		ORDER BY a.scheduled_date DESC LIMIT 500";
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array_merge([(int)$user->id], $params));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	// hydrate assignees and latest images
	$ids = array_column($rows, 'id');
	$assigneesByActivity = [];
	$photosByActivity = [];
	if (!empty($ids)) {
		$in = implode(',', array_fill(0, count($ids), '?'));
		// Assignees
		$q = $pdo->prepare("SELECT aa.activity_id, aa.user_id AS id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type
						 FROM activity_assignees aa JOIN users u ON u.id = aa.user_id WHERE aa.activity_id IN ($in)");
		$q->execute($ids);
		while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
			$aid = (int)$r['activity_id'];
			unset($r['activity_id']);
			if (!isset($assigneesByActivity[$aid])) { $assigneesByActivity[$aid] = []; }
			$assigneesByActivity[$aid][] = $r;
		}
		// Photos (latest first)
		$qp = $pdo->prepare("SELECT activity_id, image_url FROM activity_photos WHERE activity_id IN ($in) ORDER BY created_at DESC, id DESC");
		$qp->execute($ids);
		while ($pr = $qp->fetch(PDO::FETCH_ASSOC)) {
			$aid = (int)$pr['activity_id'];
			if (!isset($photosByActivity[$aid])) { $photosByActivity[$aid] = []; }
			$photosByActivity[$aid][] = $pr['image_url'];
		}
	}
	foreach ($rows as &$rowx) {
		$aid = (int)$rowx['id'];
		$rowx['assignees'] = $assigneesByActivity[$aid] ?? [];
		$imgs = $photosByActivity[$aid] ?? [];
		$rowx['images_count'] = count($imgs);
		$rowx['latest_images'] = array_slice($imgs, 0, 3);
	}
	unset($rowx);
	 echo json_encode(['success' => true, 'activities' => $rows]);
	 exit;
}

// Create activity
if ($method === 'POST' && (preg_match('#/activities$#', $path) || basename(parse_url($path, PHP_URL_PATH)) === basename(__FILE__))) {
	$body = json_decode(file_get_contents('php://input'), true);
	$title = trim($body['title'] ?? '');
	$desc = trim($body['description'] ?? '');
	$scheduled = $body['scheduled_date'] ?? null;
	$locationId = (int)($body['location_id'] ?? 0);
	if (!$title || !$desc || !$scheduled || !$locationId) {
		sup_send_error_response('title, description, scheduled_date, location_id required');
	}
	ensure_location_assigned($pdo, $user->id, $locationId);
	$stmt = $pdo->prepare("INSERT INTO activities (society_id, title, description, scheduled_date, status, created_by) VALUES (?,?,?,?, 'Upcoming', ?)");
	$stmt->execute([$locationId, $title, $desc, $scheduled, $user->id]);
	$newId = (int)$pdo->lastInsertId();
	try {
		$assign = $pdo->prepare('INSERT IGNORE INTO activity_assignees (activity_id, user_id) VALUES (?, ?)');
		$assign->execute([$newId, (int)$user->id]);
	} catch (Exception $e) { /* ignore to avoid blocking creation */ }
	echo json_encode(['success' => true, 'id' => $newId]);
	exit;
}

// Assign activity (pretty route)
if ($method === 'POST' && preg_match('#/activities/(\d+)/assign$#', $path, $m)) {
	$activityId = (int)$m[1];
	$body = json_decode(file_get_contents('php://input'), true);
	$assignees = $body['assignees'] ?? [];
	if (!is_array($assignees) || empty($assignees)) {
		sup_send_error_response('assignees array required');
	}
	// find location for activity
	$locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
	$locStmt->execute([$activityId]);
	$act = $locStmt->fetch(PDO::FETCH_ASSOC);
	if (!$act) { sup_send_error_response('Activity not found', 404); }
	ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
	
	$pdo->beginTransaction();
	try {
		$del = $pdo->prepare('DELETE FROM activity_assignees WHERE activity_id = ?');
		$del->execute([$activityId]);
		$ins = $pdo->prepare('INSERT INTO activity_assignees (activity_id, user_id) VALUES (?,?)');
		foreach ($assignees as $uid) {
			$ins->execute([$activityId, (int)$uid]);
		}
		$pdo->commit();
		echo json_encode(['success' => true]);
	} catch (Exception $e) {
		$pdo->rollBack();
		sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
	}
	exit;
}

// Assign activity (php pathinfo)
if ($method === 'POST' && $pathInfo && preg_match('#^/(\d+)/assign$#', $pathInfo, $m)) {
    $activityId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true);
    $assignees = $body['assignees'] ?? [];
    if (!is_array($assignees) || empty($assignees)) {
        sup_send_error_response('assignees array required');
    }
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM activity_assignees WHERE activity_id = ?');
        $del->execute([$activityId]);
        $ins = $pdo->prepare('INSERT INTO activity_assignees (activity_id, user_id) VALUES (?,?)');
        foreach ($assignees as $uid) {
            $ins->execute([$activityId, (int)$uid]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
    }
    exit;
}

// Complete activity
if ($method === 'POST' && preg_match('#/activities/(\d+)/complete$#', $path, $m)) {
	$activityId = (int)$m[1];
	$locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
	$locStmt->execute([$activityId]);
	$act = $locStmt->fetch(PDO::FETCH_ASSOC);
	if (!$act) { sup_send_error_response('Activity not found', 404); }
	ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
	$upd = $pdo->prepare("UPDATE activities SET status = 'Completed' WHERE id = ?");
	$upd->execute([$activityId]);
	echo json_encode(['success' => true]);
	exit;
}

// Complete activity (php pathinfo)
if ($method === 'POST' && $pathInfo && preg_match('#^/(\d+)/complete$#', $pathInfo, $m)) {
    $activityId = (int)$m[1];
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    $upd = $pdo->prepare("UPDATE activities SET status = 'Completed' WHERE id = ?");
    $upd->execute([$activityId]);
    echo json_encode(['success' => true]);
    exit;
}

// Fallback: POST body with action/id
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $idFromBody = isset($body['id']) ? (int)$body['id'] : 0;
    $action = isset($body['action']) ? strtolower(trim($body['action'])) : '';
    if ($idFromBody > 0 && $action === 'assign') {
        $assignees = $body['assignees'] ?? [];
        if (!is_array($assignees) || empty($assignees)) { sup_send_error_response('assignees array required'); }
        $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
        $locStmt->execute([$idFromBody]);
        $act = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$act) { sup_send_error_response('Activity not found', 404); }
        ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM activity_assignees WHERE activity_id = ?')->execute([$idFromBody]);
            $ins = $pdo->prepare('INSERT INTO activity_assignees (activity_id, user_id) VALUES (?,?)');
            foreach ($assignees as $uid) { $ins->execute([$idFromBody, (int)$uid]); }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
        }
        exit;
    }
    if ($idFromBody > 0 && $action === 'complete') {
        $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
        $locStmt->execute([$idFromBody]);
        $act = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$act) { sup_send_error_response('Activity not found', 404); }
        ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
        $pdo->prepare("UPDATE activities SET status = 'Completed' WHERE id = ?")->execute([$idFromBody]);
        echo json_encode(['success' => true]);
        exit;
    }
    // Fallback update via POST when no action is provided
    if ($idFromBody > 0 && $action === '') {
        $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
        $locStmt->execute([$idFromBody]);
        $act = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$act) { sup_send_error_response('Activity not found', 404); }
        ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
        $fields = [];
        $params = [];
        if (!empty($body['title'])) { $fields[] = 'title = ?'; $params[] = $body['title']; }
        if (!empty($body['description'])) { $fields[] = 'description = ?'; $params[] = $body['description']; }
        if (!empty($body['scheduled_date'])) { $fields[] = 'scheduled_date = ?'; $params[] = $body['scheduled_date']; }
        if (!empty($body['status'])) { $fields[] = 'status = ?'; $params[] = $body['status']; }
        if (empty($fields)) { sup_send_error_response('No fields to update'); }
        $params[] = $idFromBody;
        $upd = $pdo->prepare('UPDATE activities SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $upd->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Update limited fields
if ($method === 'PATCH' && preg_match('#/activities/(\d+)$#', $path, $m)) {
    $activityId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true);
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    $fields = [];
    $params = [];
    if (!empty($body['title'])) { $fields[] = 'title = ?'; $params[] = $body['title']; }
    if (!empty($body['description'])) { $fields[] = 'description = ?'; $params[] = $body['description']; }
    if (!empty($body['scheduled_date'])) { $fields[] = 'scheduled_date = ?'; $params[] = $body['scheduled_date']; }
    if (!empty($body['status'])) { $fields[] = 'status = ?'; $params[] = $body['status']; }
    if (empty($fields)) { sup_send_error_response('No fields to update'); }
    $params[] = $activityId;
    $upd = $pdo->prepare('UPDATE activities SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $upd->execute($params);
    echo json_encode(['success' => true]);
    exit;
}

// PATCH php pathinfo
if ($method === 'PATCH' && $pathInfo && preg_match('#^/(\d+)$#', $pathInfo, $m)) {
    $activityId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true);
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    $fields = [];
    $params = [];
    if (!empty($body['title'])) { $fields[] = 'title = ?'; $params[] = $body['title']; }
    if (!empty($body['description'])) { $fields[] = 'description = ?'; $params[] = $body['description']; }
    if (!empty($body['scheduled_date'])) { $fields[] = 'scheduled_date = ?'; $params[] = $body['scheduled_date']; }
    if (!empty($body['status'])) { $fields[] = 'status = ?'; $params[] = $body['status']; }
    if (empty($fields)) { sup_send_error_response('No fields to update'); }
    $params[] = $activityId;
    $upd = $pdo->prepare('UPDATE activities SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $upd->execute($params);
    echo json_encode(['success' => true]);
    exit;
}

// Delete activity (soft delete if column exists else hard delete)
if ($method === 'DELETE' && preg_match('#/activities/(\d+)$#', $path, $m)) {
    $activityId = (int)$m[1];
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    // Attempt soft delete
    try {
        $upd = $pdo->prepare('UPDATE activities SET deleted_at = NOW() WHERE id = ?');
        $upd->execute([$activityId]);
    } catch (Exception $e) {
        $del = $pdo->prepare('DELETE FROM activities WHERE id = ?');
        $del->execute([$activityId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// DELETE php pathinfo
if ($method === 'DELETE' && $pathInfo && preg_match('#^/(\d+)$#', $pathInfo, $m)) {
    $activityId = (int)$m[1];
    $locStmt = $pdo->prepare('SELECT society_id FROM activities WHERE id = ?');
    $locStmt->execute([$activityId]);
    $act = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$act) { sup_send_error_response('Activity not found', 404); }
    ensure_location_assigned($pdo, $user->id, (int)$act['society_id']);
    try {
        $upd = $pdo->prepare('UPDATE activities SET deleted_at = NOW() WHERE id = ?');
        $upd->execute([$activityId]);
    } catch (Exception $e) {
        $del = $pdo->prepare('DELETE FROM activities WHERE id = ?');
        $del->execute([$activityId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

sup_send_error_response('Not found', 404);


