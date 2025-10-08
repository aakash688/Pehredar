<?php
// Supervisor Attendance Management API
// Comprehensive attendance management matching web functionality
// GET  /mobileappapis/supervisor/attendance_manage.php?action=get_data&month=X&year=Y
// POST /mobileappapis/supervisor/attendance_manage.php?action=bulk_update
// GET  /mobileappapis/supervisor/attendance_manage.php?action=get_codes
// GET  /mobileappapis/supervisor/attendance_manage.php?action=get_societies
// GET  /mobileappapis/supervisor/attendance_manage.php?action=get_audit_log&id=X

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/api_helpers.php';
// Removed early include of attendance_controller; actions below will include as needed
// require_once __DIR__ . '/../../actions/attendance_controller.php';

try {
    $user = sup_get_authenticated_user();
    $db = sup_get_db();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_data':
            // Proxy to the web attendance controller, but filter by supervisor's team
            $_GET['action'] = 'get_attendance_data';
            
            // Get the current authenticated user
            $current_user_id = $user->id;
            $current_user_type = strtoupper(trim($user->user_type ?? ''));
            
            $managed_team_ids = [];
            if ($current_user_type === 'ADMIN') {
                // Admin: manage all teams
                $team_stmt = $db->prepare("SELECT id FROM teams");
                $team_stmt->execute();
                $managed_team_ids = $team_stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                // Supervisor or Site Supervisor: teams where user is mapped accordingly
                $team_stmt = $db->prepare("SELECT DISTINCT team_id 
                                           FROM team_members 
                                           WHERE user_id = ? AND role IN ('Supervisor','Site Supervisor')");
                $team_stmt->execute([$current_user_id]);
                $managed_team_ids = $team_stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // If no teams found for non-admin, return empty result
            if (empty($managed_team_ids)) {
                sup_send_json_response([
                    'success' => true, 
                    'data' => [
                        'users' => [],
                        'holidays' => [],
                        'days_in_month' => cal_days_in_month(CAL_GREGORIAN, $_GET['month'] ?? date('m'), $_GET['year'] ?? date('Y')),
                        'month' => $_GET['month'] ?? date('m'),
                        'year' => $_GET['year'] ?? date('Y'),
                        'societies' => [],
                        'attendance' => [],
                        'dates' => []
                    ]
                ]);
                exit;
            }
            
            // Add team filter to the request
            $_GET['supervisor_team_ids'] = implode(',', $managed_team_ids);
            
            include_once __DIR__ . '/../../actions/attendance_controller.php';
            break;

        case 'bulk_update':
            // Proxy bulk update to web controller
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_GET['action'] = 'bulk_update_attendance';
            include_once __DIR__ . '/../../actions/attendance_controller.php';
            break;

        case 'get_codes':
            // Get attendance master codes
            $_GET['action'] = 'get_attendance_master_codes';
            include_once __DIR__ . '/../../actions/attendance_controller.php';
            break;

        case 'get_societies':
            // Get user societies
            $_GET['action'] = 'get_user_societies';
            include_once __DIR__ . '/../../actions/attendance_controller.php';
            break;

        case 'get_audit_log':
            // Get audit log for specific attendance record
            $_GET['action'] = 'get_attendance_audit_log';
            include_once __DIR__ . '/../../actions/attendance_controller.php';
            break;

        case 'get_team_members':
            // Admin: all guard-type users across all teams
            if (strcasecmp($user->user_type ?? '', 'Admin') === 0) {
                $stmt = $db->prepare("SELECT DISTINCT u.id, 
                                             CONCAT(u.first_name, ' ', u.surname) AS name,
                                             u.user_type AS role,
                                             u.mobile_number AS phone,
                                             t.team_name
                                        FROM team_members tm
                                        JOIN users u ON u.id = tm.user_id
                                        JOIN teams t ON t.id = tm.team_id
                                       WHERE u.user_type IN ('Guard','Security Guard')
                                       ORDER BY u.first_name, u.surname");
                $stmt->execute();
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sup_send_json_response(['success' => true, 'members' => $members]);
                break;
            }
            
            // Supervisor or Site Supervisor: only their teams' guard members
            $stmt = $db->prepare("SELECT DISTINCT u.id, 
                                         CONCAT(u.first_name, ' ', u.surname) AS name,
                                         u.user_type AS role,
                                         u.mobile_number AS phone,
                                         t.team_name
                                    FROM team_members tm
                                    JOIN users u ON u.id = tm.user_id
                                    JOIN teams t ON t.id = tm.team_id
                                   WHERE tm.team_id IN (
                                       SELECT team_id FROM team_members 
                                       WHERE user_id = ? AND role IN ('Supervisor','Site Supervisor')
                                   )
                                   AND u.user_type IN ('Guard','Security Guard')
                                   ORDER BY u.first_name, u.surname");
            $stmt->execute([$user->id]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sup_send_json_response(['success' => true, 'members' => $members]);
            break;

        case 'get_shifts':
            // Get all active shifts
            $stmt = $db->prepare("SELECT id, shift_name, start_time, end_time, is_active 
                                 FROM shift_master 
                                 WHERE is_active = 1 
                                 ORDER BY start_time");
            $stmt->execute();
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sup_send_json_response(['success' => true, 'shifts' => $shifts]);
            break;

        default:
            sup_send_error_response('Invalid action', 400);
    }
} catch (Throwable $e) {
    @error_log('[SUPERVISOR_API][ATTENDANCE_MANAGE] ' . $e->getMessage());
    sup_send_error_response('Server error', 500);
}
