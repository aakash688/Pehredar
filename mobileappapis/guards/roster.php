<?php
// mobileappapis/guards/roster.php
// Guard-facing API: view roster assignments with lazy loading, search, and filtering

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

require_once '../../vendor/autoload.php';
require_once '../../config.php';
require_once __DIR__ . '/../shared/optimized_guard_helper.php';
require_once __DIR__ . '/../../helpers/database.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $config = require '../../config.php';
    $jwt = getOptimizedBearerToken();
    if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
    $decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
    $userId = (int)($decoded->data->id ?? 0);
    $userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
    if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

    $pdo = ConnectionPool::getConnection();

    // Detect if 'sites' table exists in this database
    $hasSites = false;
    try {
        $checkStmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sites' LIMIT 1");
        $checkStmt->execute();
        $hasSites = (bool)$checkStmt->fetchColumn();
    } catch (Throwable $__) {
        $hasSites = false;
    }

    // Pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
    $filterDateStart = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
    $filterDateEnd = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';
    $filterShift = isset($_GET['shift']) ? trim($_GET['shift']) : '';
    $filterClient = isset($_GET['client']) ? trim($_GET['client']) : '';
    $filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

    // Build WHERE clause for roster filtering
    $where = ['r.guard_id = ?'];
    $params = [$userId];

    // Search filter (society name, site name, shift name)
    if (!empty($search)) {
        if ($hasSites) {
            $where[] = '(s.society_name LIKE ? OR site.site_name LIKE ? OR sm.shift_name LIKE ? OR t.team_name LIKE ?)';
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        } else {
            $where[] = '(s.society_name LIKE ? OR sm.shift_name LIKE ? OR t.team_name LIKE ?)';
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }
    }

    // Date filters
    if (!empty($filterDate)) {
        $where[] = 'DATE(r.created_at) = ?';
        $params[] = $filterDate;
    }

    if (!empty($filterDateStart) && !empty($filterDateEnd)) {
        $where[] = 'DATE(r.created_at) BETWEEN ? AND ?';
        $params[] = $filterDateStart;
        $params[] = $filterDateEnd;
    }

    // Shift filter
    if (!empty($filterShift)) {
        $where[] = 'sm.shift_name LIKE ?';
        $params[] = "%$filterShift%";
    }

    // Client filter (society/site)
    if (!empty($filterClient)) {
        if ($hasSites) {
            $where[] = '(s.society_name LIKE ? OR site.site_name LIKE ?)';
            $clientParam = "%$filterClient%";
            $params[] = $clientParam;
            $params[] = $clientParam;
        } else {
            $where[] = 's.society_name LIKE ?';
            $clientParam = "%$filterClient%";
            $params[] = $clientParam;
        }
    }

    // Status filter (active, completed, upcoming)
    if (!empty($filterStatus)) {
        switch ($filterStatus) {
            case 'active':
                $where[] = 'r.assignment_start_date <= CURDATE() AND (r.assignment_end_date IS NULL OR r.assignment_end_date >= CURDATE())';
                break;
            case 'completed':
                $where[] = 'r.assignment_end_date < CURDATE()';
                break;
            case 'upcoming':
                $where[] = 'r.assignment_start_date > CURDATE()';
                break;
        }
    }

    // Month/year filter: return any assignment that intersects the given month
    $filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    $filterYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
    if ($filterMonth >= 1 && $filterMonth <= 12 && $filterYear >= 1900 && $filterYear <= 3000) {
        $startOfMonth = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));
        // Intersects logic: starts in month OR ends in month OR spans across month
        $where[] = '((MONTH(r.assignment_start_date) = ? AND YEAR(r.assignment_start_date) = ?) OR (MONTH(r.assignment_end_date) = ? AND YEAR(r.assignment_end_date) = ?) OR (r.assignment_start_date <= ? AND (r.assignment_end_date IS NULL OR r.assignment_end_date >= ?)))';
        $params[] = $filterMonth;
        $params[] = $filterYear;
        $params[] = $filterMonth;
        $params[] = $filterYear;
        $params[] = $endOfMonth; // assignment_start_date <= end of month
        $params[] = $startOfMonth; // assignment_end_date >= start of month
    }

    $whereClause = implode(' AND ', $where);

    // Common SELECT fragments dependent on sites table
    $siteJoin = $hasSites ? "LEFT JOIN sites site ON r.site_id = site.id" : "";
    $siteSelect = $hasSites ? "site.site_name," : "NULL as site_name,";

    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM roster r
        LEFT JOIN society_onboarding_data s ON r.society_id = s.id
        {$siteJoin}
        LEFT JOIN shift_master sm ON r.shift_id = sm.id
        LEFT JOIN teams t ON r.team_id = t.id
        WHERE $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // Main roster query with joins
    $rosterQuery = "
        SELECT 
            r.id,
            r.guard_id,
            r.society_id,
            r.shift_id,
            r.team_id,
            r.assignment_start_date,
            r.assignment_end_date,
            r.created_at,
            r.updated_at,
            
            -- Society/Client details
            s.society_name,

            
            -- Site details (if available)
            {$siteSelect}
            
            -- Shift details
            sm.shift_name,
            sm.start_time,
            sm.end_time,
                    
            -- Team details
            t.team_name,
    
            
            -- Additional calculated fields
            CASE 
                WHEN r.assignment_start_date <= CURDATE() AND (r.assignment_end_date IS NULL OR r.assignment_end_date >= CURDATE()) 
                THEN 'active'
                WHEN r.assignment_end_date < CURDATE() 
                THEN 'completed'
                WHEN r.assignment_start_date > CURDATE() 
                THEN 'upcoming'
                ELSE 'unknown'
            END as assignment_status,
            
            DATEDIFF(COALESCE(r.assignment_end_date, CURDATE()), r.assignment_start_date) as days_assigned,
            
            -- Format dates for display
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as assigned_date,
            DATE_FORMAT(r.updated_at, '%Y-%m-%d %H:%i') as last_updated
        FROM roster r
        LEFT JOIN society_onboarding_data s ON r.society_id = s.id
        {$siteJoin}
        LEFT JOIN shift_master sm ON r.shift_id = sm.id
        LEFT JOIN teams t ON r.team_id = t.id
        WHERE $whereClause
        ORDER BY 
            CASE 
                WHEN r.assignment_start_date <= CURDATE() AND (r.assignment_end_date IS NULL OR r.assignment_end_date >= CURDATE()) THEN 1
                WHEN r.assignment_start_date > CURDATE() THEN 2
                ELSE 3
            END,
            r.assignment_start_date DESC,
            r.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $rosterStmt = $pdo->prepare($rosterQuery);
    $rosterStmt->execute($params);
    $rosters = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

    // Process roster data for frontend
    $items = [];
    foreach ($rosters as $roster) {
        // Calculate shift duration
        $shiftStart = $roster['start_time'] ?? '00:00:00';
        $shiftEnd = $roster['end_time'] ?? '23:59:59';
        $shiftDuration = null;
        
        if ($shiftStart && $shiftEnd) {
            $start = strtotime($shiftStart);
            $end = strtotime($shiftEnd);
            if ($end < $start) $end += 86400; // Add 24 hours for overnight shifts
            $shiftDuration = round(($end - $start) / 3600, 1); // Hours
        }

        // Format shift time for display
        $shiftTimeDisplay = '';
        if ($shiftStart && $shiftEnd) {
            $shiftTimeDisplay = date('H:i', strtotime($shiftStart)) . ' - ' . date('H:i', strtotime($shiftEnd));
        }

        // Determine client name (prefer site name over society name)
        $clientName = $roster['site_name'] ?? $roster['society_name'] ?? 'Unknown Location';
        $clientType = $roster['site_name'] ? 'Site' : 'Society';

        $items[] = [
            'id' => (int)$roster['id'],
            'guard_id' => (int)$roster['guard_id'],
            'society_id' => (int)$roster['society_id'],
            'shift_id' => (int)$roster['shift_id'],
            'team_id' => (int)$roster['team_id'],
            
            // Client/Location details
            'client_name' => $clientName,
            'client_type' => $clientType,
            'client_address' => '',
            'contact_person' => $roster['contact_person'] ?? '',
            'contact_number' => $roster['contact_number'] ?? '',
            
            // Shift details
            'shift_name' => $roster['shift_name'] ?? 'Unknown Shift',
            'shift_time' => $shiftTimeDisplay,
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'shift_duration_hours' => $shiftDuration,
            'grace_minutes' => (int)($roster['grace_minutes'] ?? 0),
            
            // Team details
            'team_name' => $roster['team_name'] ?? 'Unassigned',
           
            
            // Assignment details
            'assignment_start_date' => $roster['assignment_start_date'],
            'assignment_end_date' => $roster['assignment_end_date'],
            'assignment_status' => $roster['assignment_status'],
            'days_assigned' => (int)$roster['days_assigned'],
            
            // Timestamps
            'assigned_date' => $roster['assigned_date'],
            'last_updated' => $roster['last_updated'],
            'created_at' => $roster['created_at'],
            'updated_at' => $roster['updated_at']
        ];
    }

    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_assignments,
            COUNT(CASE WHEN r.assignment_start_date <= CURDATE() AND (r.assignment_end_date IS NULL OR r.assignment_end_date >= CURDATE()) THEN 1 END) as active_assignments,
            COUNT(CASE WHEN r.assignment_end_date < CURDATE() THEN 1 END) as completed_assignments,
            COUNT(CASE WHEN r.assignment_start_date > CURDATE() THEN 1 END) as upcoming_assignments,
            COUNT(DISTINCT r.society_id) as total_locations,
            COUNT(DISTINCT r.shift_id) as total_shifts,
            COUNT(DISTINCT r.team_id) as total_teams
        FROM roster r
        WHERE r.guard_id = ?
    ";
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute([$userId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Get available filter options for frontend
    if ($hasSites) {
        $filterOptionsQuery = "
            SELECT 
                'shift' as filter_type,
                sm.shift_name as filter_value,
                COUNT(*) as count
            FROM roster r
            JOIN shift_master sm ON r.shift_id = sm.id
            WHERE r.guard_id = ?
            GROUP BY sm.shift_name
            
            UNION ALL
            
            SELECT 
                'client' as filter_type,
                COALESCE(site.site_name, s.society_name) as filter_value,
                COUNT(*) as count
            FROM roster r
            LEFT JOIN society_onboarding_data s ON r.society_id = s.id
            LEFT JOIN sites site ON r.site_id = site.id
            WHERE r.guard_id = ?
            GROUP BY COALESCE(site.site_name, s.society_name)
            
            ORDER BY filter_type, count DESC
        ";
        $filterOptionsStmt = $pdo->prepare($filterOptionsQuery);
        $filterOptionsStmt->execute([$userId, $userId]);
    } else {
        $filterOptionsQuery = "
            SELECT 
                'shift' as filter_type,
                sm.shift_name as filter_value,
                COUNT(*) as count
            FROM roster r
            JOIN shift_master sm ON r.shift_id = sm.id
            WHERE r.guard_id = ?
            GROUP BY sm.shift_name
            
            ORDER BY filter_type, count DESC
        ";
        $filterOptionsStmt = $pdo->prepare($filterOptionsQuery);
        $filterOptionsStmt->execute([$userId]);
    }
    $filterOptions = $filterOptionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group filter options by type
    $filterOptionsGrouped = [];
    foreach ($filterOptions as $option) {
        $type = $option['filter_type'];
        if (!isset($filterOptionsGrouped[$type])) {
            $filterOptionsGrouped[$type] = [];
        }
        $filterOptionsGrouped[$type][] = [
            'value' => $option['filter_value'],
            'count' => (int)$option['count']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'rosters' => $items,
            'summary' => [
                'total_assignments' => (int)$summary['total_assignments'],
                'active_assignments' => (int)$summary['active_assignments'],
                'completed_assignments' => (int)$summary['completed_assignments'],
                'upcoming_assignments' => (int)$summary['upcoming_assignments'],
                'total_locations' => (int)$summary['total_locations'],
                'total_shifts' => (int)$summary['total_shifts'],
                'total_teams' => (int)$summary['total_teams']
            ],
            'filter_options' => $filterOptionsGrouped
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'has_next' => $page < ceil($totalRecords / $limit),
            'has_prev' => $page > 1
        ],
        'filters_applied' => [
            'search' => $search,
            'date' => $filterDate,
            'date_start' => $filterDateStart,
            'date_end' => $filterDateEnd,
            'shift' => $filterShift,
            'client' => $filterClient,
            'status' => $filterStatus
        ]
    ]);

} catch (Throwable $e) {
    error_log("Guard Roster API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error', 
        'details' => $e->getMessage()
    ]);
}
?>

