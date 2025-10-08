<?php
// GET /api/supervisor/tickets
// POST /api/supervisor/tickets
// POST /api/supervisor/tickets/{id}/assign
// PATCH /api/supervisor/tickets/{id}
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
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (!$pathInfo && isset($_SERVER['SCRIPT_NAME']) && strpos($path, basename(__FILE__) . '/') !== false) {
    $pathInfo = substr($path, strpos($path, basename(__FILE__)) + strlen(basename(__FILE__)));
}

function ensure_location_assigned_ticket($pdo, $userId, $locationId) {
	$stmt = $pdo->prepare("SELECT 1 FROM supervisor_site_assignments WHERE supervisor_id = ? AND site_id = ?");
	$stmt->execute([$userId, $locationId]);
	if (!$stmt->fetch()) {
		sup_send_error_response('Location not assigned to supervisor', 403);
	}
}

if ($method === 'GET' 
    && !preg_match('#/tickets/(\\d+)$#', $path)
    && !($pathInfo && preg_match('#^/(\\d+)$#', $pathInfo))
    && empty($_GET['id'])) {
	$where = [];
	$params = [];
	if (!empty($_GET['location_id'])) { $where[] = 't.society_id = ?'; $params[] = (int)$_GET['location_id']; }
	if (!empty($_GET['status'])) { $where[] = 't.status = ?'; $params[] = $_GET['status']; }
	if (!empty($_GET['search'])) {
		$like = '%' . $_GET['search'] . '%';
		$where[] = '(t.title LIKE ? OR t.description LIKE ?)';
		$params[] = $like; $params[] = $like;
	}
	if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
		$start = !empty($_GET['start_date']) ? $_GET['start_date'] : '1970-01-01';
		$end   = !empty($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
		$where[] = 'DATE(t.created_at) BETWEEN ? AND ?';
		$params[] = $start; $params[] = $end;
	}
	$where[] = 't.society_id IN (SELECT site_id FROM supervisor_site_assignments WHERE supervisor_id = ?)';
	$params[] = (int)$user->id;

	$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
	$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
	$offset = ($page - 1) * $limit;
	$fetchLimit = $limit + 1; // for has_more detection

	$sql = "SELECT t.id, t.title, t.description, t.status, t.priority, t.society_id AS location_id, t.created_at
		FROM tickets t
		WHERE " . implode(' AND ', $where) . "
		ORDER BY t.created_at DESC LIMIT $fetchLimit OFFSET $offset";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$hasMore = false;
	if (count($rows) > $limit) { $hasMore = true; array_pop($rows); }
	echo json_encode(['success' => true, 'tickets' => $rows, 'has_more' => $hasMore]);
	exit;
}

// Get single ticket (pretty route)
if ($method === 'GET' && preg_match('#/tickets/(\d+)$#', $path, $m)) {
    $ticketId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT t.id, t.title, t.description, t.status, t.priority, t.society_id AS location_id, t.created_at FROM tickets t WHERE t.id = ?");
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$row['location_id']);
    // attachments list (if table exists)
    try {
        // Prefer excluding comment attachments if schema supports comment_id
        try {
            $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? AND (comment_id IS NULL OR comment_id = 0) ORDER BY id DESC');
            $af->execute([$ticketId]);
        } catch (Throwable $eFilter) {
            $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? ORDER BY id DESC');
            $af->execute([$ticketId]);
        }
        $atts = $af->fetchAll(PDO::FETCH_ASSOC);
        $row['attachments'] = array_map(function($a){
            return [
                'id' => (int)$a['id'],
                'file_path' => $a['file_path'],
                'file_name' => $a['file_name'] ?? null,
            ];
        }, $atts);
    } catch (Throwable $t) {
        $row['attachments'] = [];
    }
    // Fallback: if DB row missing, scan uploads/tickets directory for files with ticket prefix
    try {
        if (empty($row['attachments'])) {
            $up = realpath(__DIR__ . '/../../uploads/tickets');
            if ($up !== false) {
                $pattern = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_*');
                foreach (glob($pattern) as $f) {
                    $bn = basename($f);
                    $row['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn];
                }
            }
        }
    } catch (Throwable $t) {}
    // comments (optional table)
    try {
        $cf = $pdo->prepare('SELECT c.id, c.comment, c.created_at, COALESCE(CONCAT(u.first_name, " ", u.surname), "User") AS user_name FROM ticket_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.ticket_id = ? ORDER BY c.id ASC');
        $cf->execute([$ticketId]);
        $row['comments'] = $cf->fetchAll(PDO::FETCH_ASSOC);
        // Link attachments to their comments if schema supports it; else infer by filename pattern
        foreach ($row['comments'] as &$cm) { $cm['attachments'] = []; }
        unset($cm);
        // Try DB join first (support either `comment_id` or legacy `ticket_comment_id`)
        try {
            $aj = $pdo->prepare('SELECT id, file_path, file_name, comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
            $aj->execute([$ticketId]);
            while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                if ($cid > 0) {
                    foreach ($row['comments'] as &$cm) {
                        if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                    }
                    unset($cm);
                }
            }
        } catch (Throwable $e) {
            try {
                $aj = $pdo->prepare('SELECT id, file_path, file_name, ticket_comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
                $aj->execute([$ticketId]);
                while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                    $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                    if ($cid > 0) {
                        foreach ($row['comments'] as &$cm) {
                            if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                        }
                        unset($cm);
                    }
                }
            } catch (Throwable $e2) {
                // Fallback by filename pattern: ticket_{id}_c{commentId}_* (mobile) or {ticketId}-{commentId}-* (web)
                $up = realpath(__DIR__ . '/../../uploads/tickets');
                if ($up !== false) {
                    foreach ($row['comments'] as &$cm2) {
                        $pattern1 = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_c' . (int)$cm2['id'] . '_*');
                        foreach (glob($pattern1) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                        $pattern2 = $up . DIRECTORY_SEPARATOR . ($ticketId . '-' . (int)$cm2['id'] . '-*');
                        foreach (glob($pattern2) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                    }
                    unset($cm2);
                }
            }
        }
    } catch (Throwable $t) {
        $row['comments'] = [];
    }
    echo json_encode(['success' => true, 'ticket' => $row]);
    exit;
}

// Get single ticket (php pathinfo)
if ($method === 'GET' && $pathInfo && preg_match('#^/(\d+)$#', $pathInfo, $m)) {
    $ticketId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT t.id, t.title, t.description, t.status, t.priority, t.society_id AS location_id, t.created_at FROM tickets t WHERE t.id = ?");
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$row['location_id']);
    try {
        try {
            $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? AND (comment_id IS NULL OR comment_id = 0) ORDER BY id DESC');
            $af->execute([$ticketId]);
        } catch (Throwable $eFilter) {
            $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? ORDER BY id DESC');
            $af->execute([$ticketId]);
        }
        $atts = $af->fetchAll(PDO::FETCH_ASSOC);
        $row['attachments'] = array_map(function($a){
            return [
                'id' => (int)$a['id'],
                'file_path' => $a['file_path'],
                'file_name' => $a['file_name'] ?? null,
            ];
        }, $atts);
    } catch (Throwable $t) {
        $row['attachments'] = [];
    }
    try {
        if (empty($row['attachments'])) {
            $up = realpath(__DIR__ . '/../../uploads/tickets');
            if ($up !== false) {
                $pattern = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_*');
                foreach (glob($pattern) as $f) {
                    $bn = basename($f);
                    $row['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn];
                }
            }
        }
    } catch (Throwable $t) {}
    try {
        $cf = $pdo->prepare('SELECT c.id, c.comment, c.created_at, COALESCE(CONCAT(u.first_name, " ", u.surname), "User") AS user_name FROM ticket_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.ticket_id = ? ORDER BY c.id ASC');
        $cf->execute([$ticketId]);
        $row['comments'] = $cf->fetchAll(PDO::FETCH_ASSOC);
        foreach ($row['comments'] as &$cm) { $cm['attachments'] = []; }
        unset($cm);
        try {
            $aj = $pdo->prepare('SELECT id, file_path, file_name, comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
            $aj->execute([$ticketId]);
            while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                if ($cid > 0) {
                    foreach ($row['comments'] as &$cm) {
                        if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                    }
                    unset($cm);
                }
            }
        } catch (Throwable $e) {
            try {
                $aj = $pdo->prepare('SELECT id, file_path, file_name, ticket_comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
                $aj->execute([$ticketId]);
                while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                    $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                    if ($cid > 0) {
                        foreach ($row['comments'] as &$cm) {
                            if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                        }
                        unset($cm);
                    }
                }
            } catch (Throwable $e2) {
                $up = realpath(__DIR__ . '/../../uploads/tickets');
                if ($up !== false) {
                    foreach ($row['comments'] as &$cm2) {
                        $pattern1 = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_c' . (int)$cm2['id'] . '_*');
                        foreach (glob($pattern1) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                        $pattern2 = $up . DIRECTORY_SEPARATOR . ($ticketId . '-' . (int)$cm2['id'] . '-*');
                        foreach (glob($pattern2) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                    }
                    unset($cm2);
                }
            }
        }
    } catch (Throwable $t) {
        $row['comments'] = [];
    }
    echo json_encode(['success' => true, 'ticket' => $row]);
    exit;
}

// Fallback: id query param
if ($method === 'GET' && isset($_GET['id'])) {
    $ticketId = (int)$_GET['id'];
    if ($ticketId > 0) {
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.description, t.status, t.priority, t.society_id AS location_id, t.created_at FROM tickets t WHERE t.id = ?");
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { sup_send_error_response('Ticket not found', 404); }
        ensure_location_assigned_ticket($pdo, $user->id, (int)$row['location_id']);
        try {
            try {
                $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? AND (comment_id IS NULL OR comment_id = 0) ORDER BY id DESC');
                $af->execute([$ticketId]);
            } catch (Throwable $eFilter) {
                $af = $pdo->prepare('SELECT id, file_path, file_name FROM ticket_attachments WHERE ticket_id = ? ORDER BY id DESC');
                $af->execute([$ticketId]);
            }
            $atts = $af->fetchAll(PDO::FETCH_ASSOC);
            $row['attachments'] = array_map(function($a){
                return [
                    'id' => (int)$a['id'],
                    'file_path' => $a['file_path'],
                    'file_name' => $a['file_name'] ?? null,
                ];
            }, $atts);
        } catch (Throwable $t) {
            $row['attachments'] = [];
        }
        try {
            if (empty($row['attachments'])) {
                $up = realpath(__DIR__ . '/../../uploads/tickets');
                if ($up !== false) {
                    $pattern = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_*');
                    foreach (glob($pattern) as $f) {
                        $bn = basename($f);
                        $row['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn];
                    }
                }
            }
        } catch (Throwable $t) {}
        try {
            $cf = $pdo->prepare('SELECT c.id, c.comment, c.created_at, COALESCE(CONCAT(u.first_name, " ", u.surname), "User") AS user_name FROM ticket_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.ticket_id = ? ORDER BY c.id ASC');
            $cf->execute([$ticketId]);
            $row['comments'] = $cf->fetchAll(PDO::FETCH_ASSOC);
            foreach ($row['comments'] as &$cm) { $cm['attachments'] = []; }
            unset($cm);
            try {
                $aj = $pdo->prepare('SELECT id, file_path, file_name, comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
                $aj->execute([$ticketId]);
                while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                    $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                    if ($cid > 0) {
                        foreach ($row['comments'] as &$cm) {
                            if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                        }
                        unset($cm);
                    }
                }
            } catch (Throwable $e) {
                try {
                    $aj = $pdo->prepare('SELECT id, file_path, file_name, ticket_comment_id AS cid FROM ticket_attachments WHERE ticket_id = ?');
                    $aj->execute([$ticketId]);
                    while ($ar = $aj->fetch(PDO::FETCH_ASSOC)) {
                        $cid = isset($ar['cid']) ? (int)$ar['cid'] : 0;
                        if ($cid > 0) {
                            foreach ($row['comments'] as &$cm) {
                                if ((int)$cm['id'] === $cid) { $cm['attachments'][] = ['id' => (int)$ar['id'], 'file_path' => $ar['file_path'], 'file_name' => $ar['file_name']]; break; }
                            }
                            unset($cm);
                        }
                    }
                } catch (Throwable $e2) {
                    $up = realpath(__DIR__ . '/../../uploads/tickets');
                    if ($up !== false) {
                        foreach ($row['comments'] as &$cm2) {
                            $pattern1 = $up . DIRECTORY_SEPARATOR . ('ticket_' . $ticketId . '_c' . (int)$cm2['id'] . '_*');
                            foreach (glob($pattern1) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                            $pattern2 = $up . DIRECTORY_SEPARATOR . ($ticketId . '-' . (int)$cm2['id'] . '-*');
                            foreach (glob($pattern2) as $f) { $bn = basename($f); $cm2['attachments'][] = ['id' => 0, 'file_path' => 'uploads/tickets/' . $bn, 'file_name' => $bn]; }
                        }
                        unset($cm2);
                    }
                }
            }
        } catch (Throwable $t) {
            $row['comments'] = [];
        }
        echo json_encode(['success' => true, 'ticket' => $row]);
        exit;
    }
}

// Add comment to a ticket (pretty route)
if ($method === 'POST' && preg_match('#/tickets/(\d+)/comments$#', $path, $m)) {
    $ticketId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $comment = trim($body['comment'] ?? '');
    if ($comment === '') { sup_send_error_response('comment required', 400); }
    $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
    $locStmt->execute([$ticketId]);
    $t = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
    try {
        $ins = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at) VALUES (?,?,?,NOW())');
        $ins->execute([$ticketId, $user->id, $comment]);
        $cid = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $cid]);
    } catch (Throwable $e) {
        // If table missing, still return success to avoid blocking UX; log server side
        @error_log('[TICKETS][COMMENTS_MISSING_TABLE] ' . $e->getMessage());
        echo json_encode(['success' => true]);
    }
    exit;
}

// Add comment via php pathinfo
if ($method === 'POST' && $pathInfo && preg_match('#^/(\d+)/comments$#', $pathInfo, $m)) {
    $ticketId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $comment = trim($body['comment'] ?? '');
    if ($comment === '') { sup_send_error_response('comment required', 400); }
    $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
    $locStmt->execute([$ticketId]);
    $t = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
    try {
        $ins = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at) VALUES (?,?,?,NOW())');
        $ins->execute([$ticketId, $user->id, $comment]);
        $cid = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $cid]);
    } catch (Throwable $e) {
        @error_log('[TICKETS][COMMENTS_MISSING_TABLE] ' . $e->getMessage());
        echo json_encode(['success' => true]);
    }
    exit;
}

// Fallback: comment by action in POST
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!empty($body['id']) && strtolower(($body['action'] ?? '')) === 'comment') {
        $ticketId = (int)$body['id'];
        $comment = trim($body['comment'] ?? '');
        if ($comment === '') { sup_send_error_response('comment required', 400); }
        $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
        $locStmt->execute([$ticketId]);
        $t = $locStmt->fetch(PDO::FETCH_ASSOC);
        if (!$t) { sup_send_error_response('Ticket not found', 404); }
        ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
        try {
            $ins = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at) VALUES (?,?,?,NOW())');
            $ins->execute([$ticketId, $user->id, $comment]);
            $cid = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $cid]);
        } catch (Throwable $e) {
            @error_log('[TICKETS][COMMENTS_MISSING_TABLE] ' . $e->getMessage());
            echo json_encode(['success' => true]);
        }
        exit;
    }
}

if ($method === 'POST' && (preg_match('#/tickets$#', $path) || basename(parse_url($path, PHP_URL_PATH)) === basename(__FILE__))) {
	$body = json_decode(file_get_contents('php://input'), true);
	$title = trim($body['title'] ?? '');
	$desc = trim($body['description'] ?? '');
	$locationId = (int)($body['location_id'] ?? 0);
	$priority = $body['priority'] ?? 'Medium';
	if (!$title || !$desc || !$locationId) { sup_send_error_response('title, description, location_id required'); }
	ensure_location_assigned_ticket($pdo, $user->id, $locationId);
	$stmt = $pdo->prepare("INSERT INTO tickets (society_id, user_id, user_type, title, description, priority) VALUES (?,?,?,?,?,?)");
	$stmt->execute([$locationId, $user->id, $user->user_type, $title, $desc, $priority]);
	$newId = (int)$pdo->lastInsertId();
	// auto-assign creator as assignee if table exists
	try {
		$assign = $pdo->prepare('INSERT IGNORE INTO ticket_assignees (ticket_id, user_id) VALUES (?, ?)');
		$assign->execute([$newId, (int)$user->id]);
	} catch (Throwable $t) { /* ignore */ }
	echo json_encode(['success' => true, 'id' => $newId]);
	exit;
}

if ($method === 'POST' && preg_match('#/tickets/(\d+)/assign$#', $path, $m)) {
	$ticketId = (int)$m[1];
	$body = json_decode(file_get_contents('php://input'), true);
	$assignees = $body['assignees'] ?? [];
	if (!is_array($assignees) || empty($assignees)) { sup_send_error_response('assignees array required'); }
	$locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
	$locStmt->execute([$ticketId]);
	$t = $locStmt->fetch(PDO::FETCH_ASSOC);
	if (!$t) { sup_send_error_response('Ticket not found', 404); }
	ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
	$pdo->beginTransaction();
	try {
		$del = $pdo->prepare('DELETE FROM ticket_assignees WHERE ticket_id = ?');
		$del->execute([$ticketId]);
		$ins = $pdo->prepare('INSERT INTO ticket_assignees (ticket_id, user_id) VALUES (?,?)');
		foreach ($assignees as $uid) { $ins->execute([$ticketId, (int)$uid]); }
		$pdo->commit();
		echo json_encode(['success' => true]);
	} catch (Exception $e) {
		$pdo->rollBack();
		sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
	}
	exit;
}

// assign via php pathinfo
if ($method === 'POST' && $pathInfo && preg_match('#^/(\d+)/assign$#', $pathInfo, $m)) {
    $ticketId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true);
    $assignees = $body['assignees'] ?? [];
    if (!is_array($assignees) || empty($assignees)) { sup_send_error_response('assignees array required'); }
    $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
    $locStmt->execute([$ticketId]);
    $t = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM ticket_assignees WHERE ticket_id = ?')->execute([$ticketId]);
		$ins = $pdo->prepare('INSERT INTO ticket_assignees (ticket_id, user_id) VALUES (?,?)');
		foreach ($assignees as $uid) { $ins->execute([$ticketId, (int)$uid]); }
		$pdo->commit();
		echo json_encode(['success' => true]);
	} catch (Exception $e) {
		$pdo->rollBack();
		sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
	}
	exit;
}

if ($method === 'PATCH' && preg_match('#/tickets/(\d+)$#', $path, $m)) {
	$ticketId = (int)$m[1];
	$body = json_decode(file_get_contents('php://input'), true);
	$fields = [];
	$params = [];
	
	// Get current ticket details for validation
	$locStmt = $pdo->prepare('SELECT society_id, status FROM tickets WHERE id = ?');
	$locStmt->execute([$ticketId]);
	$t = $locStmt->fetch(PDO::FETCH_ASSOC);
	if (!$t) { sup_send_error_response('Ticket not found', 404); }
	ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
	
	// Validate status update
	if (!empty($body['status'])) {
		$newStatus = $body['status'];
		$currentStatus = $t['status'];
		
		// Valid status values
		$validStatuses = ['Open', 'In Progress', 'Closed', 'On Hold'];
		if (!in_array($newStatus, $validStatuses)) {
			sup_send_error_response('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
		}
		
		// Prevent reopening closed tickets from mobile app
		if ($currentStatus === 'Closed' && $newStatus !== 'Closed') {
			sup_send_error_response('Cannot reopen a closed ticket from mobile application. Please contact admin.', 403);
		}
		
		$fields[] = 'status = ?'; 
		$params[] = $newStatus;
		
		// Log the status change if we have ticket_history table
		try {
			$pdo->beginTransaction();
			$historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type, old_value, new_value, created_at) VALUES (?, ?, 'STATUS_CHANGED', ?, ?, NOW())";
			$pdo->prepare($historySql)->execute([$ticketId, $user->id, $currentStatus, $newStatus]);
		} catch (Exception $e) {
			// If table doesn't exist, continue without logging
		}
	}
	
	if (!empty($body['priority'])) { 
		$newPriority = $body['priority'];
		$validPriorities = ['Low', 'Medium', 'High', 'Critical'];
		if (!in_array($newPriority, $validPriorities)) {
			sup_send_error_response('Invalid priority. Must be one of: ' . implode(', ', $validPriorities), 400);
		}
		$fields[] = 'priority = ?'; 
		$params[] = $newPriority; 
	}
	
	if (empty($fields)) { sup_send_error_response('No fields to update'); }
	
	$params[] = $ticketId;
	$upd = $pdo->prepare('UPDATE tickets SET ' . implode(', ', $fields) . ' WHERE id = ?');
	$upd->execute($params);
	
	if (isset($pdo) && $pdo->inTransaction()) {
		$pdo->commit();
	}
	
	echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
	exit;
}

// PATCH via php pathinfo
if ($method === 'PATCH' && $pathInfo && preg_match('#^/(\d+)$#', $pathInfo, $m)) {
    $ticketId = (int)$m[1];
    $body = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [];
    
    // Get current ticket details for validation
    $locStmt = $pdo->prepare('SELECT society_id, status FROM tickets WHERE id = ?');
    $locStmt->execute([$ticketId]);
    $t = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
    
    // Validate status update
    if (!empty($body['status'])) {
        $newStatus = $body['status'];
        $currentStatus = $t['status'];
        
        // Valid status values
        $validStatuses = ['Open', 'In Progress', 'Closed', 'On Hold'];
        if (!in_array($newStatus, $validStatuses)) {
            sup_send_error_response('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
        }
        
        // Prevent reopening closed tickets from mobile app
        if ($currentStatus === 'Closed' && $newStatus !== 'Closed') {
            sup_send_error_response('Cannot reopen a closed ticket from mobile application. Please contact admin.', 403);
        }
        
        $fields[] = 'status = ?'; 
        $params[] = $newStatus;
        
        // Log the status change if we have ticket_history table
        try {
            $pdo->beginTransaction();
            $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type, old_value, new_value, created_at) VALUES (?, ?, 'STATUS_CHANGED', ?, ?, NOW())";
            $pdo->prepare($historySql)->execute([$ticketId, $user->id, $currentStatus, $newStatus]);
        } catch (Exception $e) {
            // If table doesn't exist, continue without logging
        }
    }
    
    if (!empty($body['priority'])) { 
        $newPriority = $body['priority'];
        $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
        if (!in_array($newPriority, $validPriorities)) {
            sup_send_error_response('Invalid priority. Must be one of: ' . implode(', ', $validPriorities), 400);
        }
        $fields[] = 'priority = ?'; 
        $params[] = $newPriority; 
    }
    
    if (empty($fields)) { sup_send_error_response('No fields to update'); }
    
    $params[] = $ticketId;
    $upd = $pdo->prepare('UPDATE tickets SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $upd->execute($params);
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->commit();
    }
    
    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
    exit;
}

// Fallback actions via POST body
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!empty($body['id']) && !empty($body['action'])) {
        $ticketId = (int)$body['id'];
        $action = strtolower(trim($body['action']));
        if ($ticketId > 0 && $action === 'assign') {
            $assignees = $body['assignees'] ?? [];
            if (!is_array($assignees) || empty($assignees)) { sup_send_error_response('assignees array required'); }
            $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
            $locStmt->execute([$ticketId]);
            $t = $locStmt->fetch(PDO::FETCH_ASSOC);
            if (!$t) { sup_send_error_response('Ticket not found', 404); }
            ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM ticket_assignees WHERE ticket_id = ?')->execute([$ticketId]);
                $ins = $pdo->prepare('INSERT INTO ticket_assignees (ticket_id, user_id) VALUES (?,?)');
                foreach ($assignees as $uid) { $ins->execute([$ticketId, (int)$uid]); }
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                sup_send_error_response('Failed to assign: ' . $e->getMessage(), 500);
            }
            exit;
        }
    }
}

// Delete ticket (soft delete preferred)
if ($method === 'DELETE' && preg_match('#/tickets/(\d+)$#', $path, $m)) {
    $ticketId = (int)$m[1];
    $locStmt = $pdo->prepare('SELECT society_id FROM tickets WHERE id = ?');
    $locStmt->execute([$ticketId]);
    $t = $locStmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) { sup_send_error_response('Ticket not found', 404); }
    ensure_location_assigned_ticket($pdo, $user->id, (int)$t['society_id']);
    try {
        $upd = $pdo->prepare('UPDATE tickets SET deleted_at = NOW() WHERE id = ?');
        $upd->execute([$ticketId]);
    } catch (Exception $e) {
        $del = $pdo->prepare('DELETE FROM tickets WHERE id = ?');
        $del->execute([$ticketId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

sup_send_error_response('Not found', 404);


