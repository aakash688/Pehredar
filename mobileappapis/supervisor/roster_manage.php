<?php
// Supervisor Roster APIs
// POST /mobileappapis/supervisor/roster_manage.php?action=assign_single
// POST /mobileappapis/supervisor/roster_manage.php?action=assign_bulk
// GET  /mobileappapis/supervisor/roster_manage.php?action=list&team_id=XX

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/api_helpers.php';

try {
    $user = sup_get_authenticated_user();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'list':
            // Proxy to get_rosters
            $_GET['action'] = 'get_rosters';
            include_once __DIR__ . '/../../actions/roster_controller.php';
            break;

        case 'assign_single':
            // Proxy to assign_roster
            $_GET['action'] = 'assign_roster';
            include_once __DIR__ . '/../../actions/roster_controller.php';
            break;

        case 'assign_bulk':
            // Proxy to bulk_assign_roster
            $_GET['action'] = 'bulk_assign_roster';
            include_once __DIR__ . '/../../actions/roster_controller.php';
            break;

        default:
            sup_send_error_response('Invalid action', 400);
    }
} catch (Throwable $e) {
    @error_log('[SUPERVISOR_API][ROSTER_MANAGE] ' . $e->getMessage());
    sup_send_error_response('Server error', 500);
}


