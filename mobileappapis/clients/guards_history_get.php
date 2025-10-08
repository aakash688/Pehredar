<?php
// mobileappapis/clients/guards_history_get.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';

// DB Connection
$config = require __DIR__ . '/../../config.php';
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['dbname']
    );
    // Use optimized connection pool to solve "max connections per hour" issue
require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';

$pdo = get_api_db_connection_safe();
} catch (PDOException $e) {
    send_error_response('Database connection failed.', 500);
}

// Auth: Ensures user is a client and gets their society_id
$user_data  = get_authenticated_user_data();
$authed_society_id = $user_data->society->id;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Invalid request method.', 405);
}

// Inputs
$society_id = isset($_GET['society_id']) ? (int)$_GET['society_id'] : (int)$authed_society_id;

// Enforce society scope for client users
if ($society_id !== (int)$authed_society_id) {
    send_error_response('Forbidden: society scope mismatch.', 403);
}

// Date filters: single-day or range
$date       = $_GET['date']        ?? null; // YYYY-MM-DD
$date_start = $_GET['date_start']  ?? ($_GET['start_date'] ?? null);
$date_end   = $_GET['date_end']    ?? ($_GET['end_date']   ?? null);

// New parameter to fetch latest entries without date constraints
$fetch_latest = isset($_GET['fetch_latest']) && $_GET['fetch_latest'] === 'true';

// Default behavior: if no date filters and not fetching latest, default to today
if (!$date && !$date_start && !$date_end && !$fetch_latest) {
    $date = date('Y-m-d');
}

// Guard optional filters
$guard_id   = isset($_GET['guard_id']) ? (int)$_GET['guard_id'] : null;
$user_type  = isset($_GET['user_type']) ? trim((string)$_GET['user_type']) : null; // e.g., Guard, Supervisor, etc.
$shift_id   = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
$code       = $_GET['code'] ?? null; // attendance code filter (e.g., Present)

// Include records where attendance code does not require a society
// Parse boolean-like GET values robustly
$include_non_society = false;
if (isset($_GET['include_non_society'])) {
    $raw = strtolower(trim((string)$_GET['include_non_society']));
    $include_non_society = in_array($raw, ['1', 'true', 'yes'], true) || ($raw === '0' ? false : $raw === '');
}

// Pagination
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

// Sorting: default "today first" which is DESC by date
$sort = strtolower($_GET['sort'] ?? 'desc');
if (!in_array($sort, ['asc', 'desc'], true)) {
    $sort = 'desc';
}

// Special optimization for today's data
if ($date && $date === date('Y-m-d') && !$date_start && !$date_end) {
    // Use a more efficient query for today's data
    $sql = "SELECT 
                a.id AS attendance_id,
                a.attendance_date,
                a.shift_start,
                a.shift_end,
                a.shift_id,
                am.code AS attendance_code,
                am.name AS attendance_status_name,
                u.id AS guard_id,
                CONCAT(u.first_name, ' ', u.surname) AS guard_name,
                u.user_type,
                u.email_id AS guard_email,
                s.id AS society_id,
                s.society_name,
                sm.shift_name,
                sm.start_time AS scheduled_start_time,
                sm.end_time AS scheduled_end_time,
                a.shift_start AS actual_check_in_time,    -- This is the ACTUAL check-in time
                a.shift_end AS actual_check_out_time      -- This is the ACTUAL check-out time
            FROM attendance a
            FORCE INDEX (idx_society_date) /* Optimize for society_id + attendance_date queries */
            JOIN attendance_master am ON a.attendance_master_id = am.id
            JOIN users u ON a.user_id = u.id
            JOIN society_onboarding_data s ON a.society_id = s.id
            LEFT JOIN shift_master sm ON a.shift_id = sm.id
            WHERE a.society_id = ? AND a.attendance_date = ?
            ORDER BY a.shift_start DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $args = [$society_id, $date];
    
    // Execute optimized query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $history = $stmt->fetchAll();
    
    // Count for today only
    $countSql = "SELECT COUNT(*) AS total
                 FROM attendance a
                 WHERE a.society_id = ? AND a.attendance_date = ?";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([$society_id, $date]);
    $totalRows = (int)$countStmt->fetchColumn();
    $hasMore = $offset + $limit < $totalRows;
    
    send_json_response([
        'success'     => true,
        'page'        => $page,
        'limit'       => $limit,
        'total_rows'  => $totalRows,
        'has_more'    => $hasMore,
        'sort'        => $sort,
        'society_id'  => $society_id,
        'filters'     => [
            'date'       => $date,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'guard_id'   => $guard_id,
            'shift_id'   => $shift_id,
            'code'       => $code,
            'include_non_society' => $include_non_society,
            'fetch_latest' => $fetch_latest,
        ],
        'history'     => $history,
        'data_type'   => 'date_filtered',
    ]);
    exit;
}

// Special optimization for fetching latest entries without date constraints
if ($fetch_latest && !$date && !$date_start && !$date_end) {
    // Fetch the most recent entries regardless of date
    $sql = "SELECT 
                a.id AS attendance_id,
                a.attendance_date,
                a.shift_start,
                a.shift_end,
                a.shift_id,
                am.code AS attendance_code,
                am.name AS attendance_status_name,
                u.id AS guard_id,
                CONCAT(u.first_name, ' ', u.surname) AS guard_name,
                u.user_type,
                u.email_id AS guard_email,
                s.id AS society_id,
                s.society_name,
                sm.shift_name,
                sm.start_time AS scheduled_start_time,
                sm.end_time AS scheduled_end_time,
                a.shift_start AS actual_check_in_time,
                a.shift_end AS actual_check_out_time
            FROM attendance a
            JOIN attendance_master am ON a.attendance_master_id = am.id
            JOIN users u ON a.user_id = u.id
            JOIN society_onboarding_data s ON a.society_id = s.id
            LEFT JOIN shift_master sm ON a.shift_id = sm.id
            WHERE a.society_id = ?";
    
    // Add other filters if provided
    if ($guard_id) {
        $sql .= " AND a.user_id = ?";
    }
    if ($user_type) {
        $sql .= " AND u.user_type = ?";
    }
    if ($shift_id) {
        $sql .= " AND a.shift_id = ?";
    }
    if ($code) {
        $sql .= " AND am.code = ?";
    }
    if (!$include_non_society) {
        $sql .= " AND am.require_society = 1";
    }
    
    $sql .= " ORDER BY a.attendance_date DESC, a.shift_start DESC";
    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    // Build args array
    $args = [$society_id];
    if ($guard_id) $args[] = $guard_id;
    if ($user_type) $args[] = $user_type;
    if ($shift_id) $args[] = $shift_id;
    if ($code) $args[] = $code;
    
    // Execute latest entries query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $history = $stmt->fetchAll();
    
    // Count total available records for pagination
    $countSql = "SELECT COUNT(*) AS total
                 FROM attendance a
                 JOIN attendance_master am ON a.attendance_master_id = am.id";
    
    if ($user_type) {
        $countSql .= " JOIN users u ON a.user_id = u.id";
    }
    
    $countSql .= " WHERE a.society_id = ?";
    $countArgs = [$society_id];
    
    if ($guard_id) {
        $countSql .= " AND a.user_id = ?";
        $countArgs[] = $guard_id;
    }
    if ($user_type) {
        $countSql .= " AND u.user_type = ?";
        $countArgs[] = $user_type;
    }
    if ($shift_id) {
        $countSql .= " AND a.shift_id = ?";
        $countArgs[] = $shift_id;
    }
    if ($code) {
        $countSql .= " AND am.code = ?";
        $countArgs[] = $code;
    }
    if (!$include_non_society) {
        $countSql .= " AND am.require_society = 1";
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countArgs);
    $totalRows = (int)$countStmt->fetchColumn();
    $hasMore = $offset + $limit < $totalRows;
    
    send_json_response([
        'success'     => true,
        'page'        => $page,
        'limit'       => $limit,
        'total_rows'  => $totalRows,
        'has_more'    => $hasMore,
        'sort'        => $sort,
        'society_id'  => $society_id,
        'filters'     => [
            'date'       => $date,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'guard_id'   => $guard_id,
            'shift_id'   => $shift_id,
            'code'       => $code,
            'include_non_society' => $include_non_society,
            'fetch_latest' => $fetch_latest,
        ],
        'history'     => $history,
        'data_type'   => 'latest_entries',
    ]);
    exit;
}

// Build query for date range queries - CORRECTED to show actual vs scheduled times
$sql = "SELECT 
            a.id AS attendance_id,
            a.attendance_date,
            a.shift_start,
            a.shift_end,
            a.shift_id,
            am.code AS attendance_code,
            am.name AS attendance_status_name,
            u.id AS guard_id,
            CONCAT(u.first_name, ' ', u.surname) AS guard_name,
            u.user_type,
            u.email_id AS guard_email,
            s.id AS society_id,
            s.society_name,
            sm.shift_name,
            sm.start_time AS scheduled_start_time,     -- This is the PLANNED shift start
            sm.end_time AS scheduled_end_time,         -- This is the PLANNED shift end
            a.shift_start AS actual_check_in_time,     -- This is the ACTUAL check-in time
            a.shift_end AS actual_check_out_time       -- This is the ACTUAL check-out time
        FROM attendance a
        JOIN attendance_master am ON a.attendance_master_id = am.id
        JOIN users u ON a.user_id = u.id
        JOIN society_onboarding_data s ON a.society_id = s.id
        LEFT JOIN shift_master sm ON a.shift_id = sm.id";

$where = ["a.society_id = ?"]; 
$args  = [$society_id];

if ($date) {
    $where[] = "a.attendance_date = ?";
    $args[]  = $date;
}
if ($date_start) {
    $where[] = "a.attendance_date >= ?";
    $args[]  = $date_start;
}
if ($date_end) {
    $where[] = "a.attendance_date <= ?";
    $args[]  = $date_end;
}
if ($guard_id) {
    $where[] = "a.user_id = ?";
    $args[]  = $guard_id;
}
if ($user_type) {
    $where[] = "u.user_type = ?";
    $args[]  = $user_type;
}
if ($shift_id) {
    $where[] = "a.shift_id = ?";
    $args[]  = $shift_id;
}
if ($code) {
    // Allow code or ID; match on code here
    $where[] = "am.code = ?";
    $args[]  = $code;
}
if (!$include_non_society) {
    // By default, only include attendance types that require a society
    $where[] = "am.require_society = 1";
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY a.attendance_date ' . strtoupper($sort) . ', a.shift_start ' . strtoupper($sort);
$sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

try {
    // Query rows
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $history = $stmt->fetchAll();

    // Count for pagination
    $countSql = "SELECT COUNT(*) AS total
                 FROM attendance a
                 JOIN attendance_master am ON a.attendance_master_id = am.id";

    // Only join users table when needed for user_type filtering
    if ($user_type) {
        $countSql .= " JOIN users u ON a.user_id = u.id";
    }

    $countSql .= " WHERE a.society_id = ?";
    $countArgs = [$society_id];

    // Rebuild count filters consistently
    if ($date) {
        $countSql .= " AND a.attendance_date = ?";
        $countArgs[] = $date;
    }
    if ($date_start) {
        $countSql .= " AND a.attendance_date >= ?";
        $countArgs[] = $date_start;
    }
    if ($date_end) {
        $countSql .= " AND a.attendance_date <= ?";
        $countArgs[] = $date_end;
    }
    if ($guard_id) {
        $countSql .= " AND a.user_id = ?";
        $countArgs[] = $guard_id;
    }
    if ($user_type) {
        $countSql .= " AND u.user_type = ?";
        $countArgs[] = $user_type;
    }
    if ($shift_id) {
        $countSql .= " AND a.shift_id = ?";
        $countArgs[] = $shift_id;
    }
    if ($code) {
        $countSql .= " AND am.code = ?";
        $countArgs[] = $code;
    }
    if (!$include_non_society) {
        $countSql .= " AND am.require_society = 1";
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countArgs);
    $totalRows = (int)$countStmt->fetchColumn();
    $hasMore   = $offset + $limit < $totalRows;

    send_json_response([
        'success'     => true,
        'page'        => $page,
        'limit'       => $limit,
        'total_rows'  => $totalRows,
        'has_more'    => $hasMore,
        'sort'        => $sort,
        'society_id'  => $society_id,
        'filters'     => [
            'date'       => $date,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'guard_id'   => $guard_id,
            'shift_id'   => $shift_id,
            'code'       => $code,
            'include_non_society' => $include_non_society,
        ],
        'history'     => $history,
    ]);
} catch (Throwable $t) {
    send_error_response('Failed to fetch guard history: ' . $t->getMessage(), 500);
}


