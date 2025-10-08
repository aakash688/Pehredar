<?php
// mobileappapis/clients/society_guard_attendance.php
// Comprehensive API for society-based guard attendance tracking
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
    require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';
    $pdo = get_api_db_connection_safe();
} catch (PDOException $e) {
    send_error_response('Database connection failed.', 500);
}

// Auth: Ensures user is a client and gets their society_id
$user_data = get_authenticated_user_data();
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

// Date filters
$date = $_GET['date'] ?? null; // YYYY-MM-DD
$date_start = $_GET['date_start'] ?? ($_GET['start_date'] ?? null);
$date_end = $_GET['date_end'] ?? ($_GET['end_date'] ?? null);

// Default to today if nothing provided
if (!$date && !$date_start && !$date_end) {
    $date = date('Y-m-d');
}

// Additional filters
$guard_id = isset($_GET['guard_id']) ? (int)$_GET['guard_id'] : null;
$shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
$attendance_code = $_GET['attendance_code'] ?? null; // Present, Absent, etc.
$guard_name = $_GET['guard_name'] ?? null; // Search by guard name

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 25));
$offset = ($page - 1) * $limit;

// Sorting
$sort_by = $_GET['sort_by'] ?? 'attendance_date';
$sort_order = strtolower($_GET['sort_order'] ?? 'desc');
if (!in_array($sort_order, ['asc', 'desc'], true)) {
    $sort_order = 'desc';
}

// Build comprehensive query for society guard attendance
$sql = "SELECT 
            a.id AS attendance_id,
            a.attendance_date,
            a.shift_id,
            a.shift_start AS actual_check_in_time,     -- Actual check-in time
            a.shift_end AS actual_check_out_time,       -- Actual check-out time
            a.marked_by,
            a.source,
            a.created_at,
            a.updated_at,
            
            -- Guard Information
            u.id AS guard_id,
            u.first_name,
            u.surname,
            CONCAT(u.first_name, ' ', u.surname) AS guard_name,
            u.user_type,
            u.email_id AS guard_email,
            
            -- Society Information
            s.id AS society_id,
            s.society_name,
            
            -- Shift Information
            sm.shift_name,
            sm.start_time AS scheduled_start_time,      -- Planned shift start
            sm.end_time AS scheduled_end_time,          -- Planned shift end
            
            -- Attendance Status
            am.code AS attendance_code,
            am.name AS attendance_status_name
            
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN society_onboarding_data s ON a.society_id = s.id
        JOIN attendance_master am ON a.attendance_master_id = am.id
        LEFT JOIN shift_master sm ON a.shift_id = sm.id
        WHERE a.society_id = ?";

$args = [$society_id];

// Add date filters
if ($date) {
    $sql .= " AND a.attendance_date = ?";
    $args[] = $date;
} elseif ($date_start && $date_end) {
    $sql .= " AND a.attendance_date BETWEEN ? AND ?";
    $args[] = $date_start;
    $args[] = $date_end;
} elseif ($date_start) {
    $sql .= " AND a.attendance_date >= ?";
    $args[] = $date_start;
} elseif ($date_end) {
    $sql .= " AND a.attendance_date <= ?";
    $args[] = $date_end;
}

// Add guard filter
if ($guard_id) {
    $sql .= " AND a.user_id = ?";
    $args[] = $guard_id;
}

// Add shift filter
if ($shift_id) {
    $sql .= " AND a.shift_id = ?";
    $args[] = $shift_id;
}

// Add attendance code filter
if ($attendance_code) {
    $sql .= " AND am.code = ?";
    $args[] = $attendance_code;
}

// Add guard name search
if ($guard_name) {
    $sql .= " AND (u.first_name LIKE ? OR u.surname LIKE ? OR CONCAT(u.first_name, ' ', u.surname) LIKE ?)";
    $searchTerm = "%$guard_name%";
    $args[] = $searchTerm;
    $args[] = $searchTerm;
    $args[] = $searchTerm;
}

// Add sorting
$sql .= " ORDER BY a.attendance_date " . strtoupper($sort_order) . ", a.shift_start " . strtoupper($sort_order);

// Add pagination
$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

try {
    // Execute main query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $attendance_data = $stmt->fetchAll();

    // Count total records for pagination
    $countSql = "SELECT COUNT(*) AS total
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 JOIN society_onboarding_data s ON a.society_id = s.id
                 JOIN attendance_master am ON a.attendance_master_id = am.id
                 WHERE a.society_id = ?";
    
    $countArgs = [$society_id];
    
    // Rebuild count filters
    if ($date) {
        $countSql .= " AND a.attendance_date = ?";
        $countArgs[] = $date;
    } elseif ($date_start && $date_end) {
        $countSql .= " AND a.attendance_date BETWEEN ? AND ?";
        $countArgs[] = $date_start;
        $countArgs[] = $date_end;
    } elseif ($date_start) {
        $countSql .= " AND a.attendance_date >= ?";
        $countArgs[] = $date_start;
    } elseif ($date_end) {
        $countSql .= " AND a.attendance_date <= ?";
        $countArgs[] = $date_end;
    }
    
    if ($guard_id) {
        $countSql .= " AND a.user_id = ?";
        $countArgs[] = $guard_id;
    }
    
    if ($shift_id) {
        $countSql .= " AND a.shift_id = ?";
        $countArgs[] = $shift_id;
    }
    
    if ($attendance_code) {
        $countSql .= " AND am.code = ?";
        $countArgs[] = $attendance_code;
    }
    
    if ($guard_name) {
        $countSql .= " AND (u.first_name LIKE ? OR u.surname LIKE ? OR CONCAT(u.first_name, ' ', u.surname) LIKE ?)";
        $searchTerm = "%$guard_name%";
        $countArgs[] = $searchTerm;
        $countArgs[] = $searchTerm;
        $countArgs[] = $searchTerm;
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countArgs);
    $totalRows = (int)$countStmt->fetchColumn();
    $hasMore = $offset + $limit < $totalRows;

    // Get summary statistics
    $summarySql = "SELECT 
                        am.code,
                        am.name,
                        COUNT(*) as count
                    FROM attendance a
                    JOIN attendance_master am ON a.attendance_master_id = am.id
                    WHERE a.society_id = ?";
    
    $summaryArgs = [$society_id];
    
    if ($date) {
        $summarySql .= " AND a.attendance_date = ?";
        $summaryArgs[] = $date;
    } elseif ($date_start && $date_end) {
        $summarySql .= " AND a.attendance_date BETWEEN ? AND ?";
        $summaryArgs[] = $date_start;
        $summaryArgs[] = $date_end;
    }
    
    $summarySql .= " GROUP BY am.code, am.name ORDER BY count DESC";
    
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryArgs);
    $summary = $summaryStmt->fetchAll();

    // Send comprehensive response
    send_json_response([
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'total_rows' => $totalRows,
        'has_more' => $hasMore,
        'sort_by' => $sort_by,
        'sort_order' => $sort_order,
        'society_id' => $society_id,
        'filters' => [
            'date' => $date,
            'date_start' => $date_start,
            'date_end' => $date_end,
            'guard_id' => $guard_id,
            'shift_id' => $shift_id,
            'attendance_code' => $attendance_code,
            'guard_name' => $guard_name,
        ],
        'summary' => $summary,
        'attendance' => $attendance_data,
    ]);

} catch (Throwable $t) {
    send_error_response('Failed to fetch society guard attendance: ' . $t->getMessage(), 500);
}
