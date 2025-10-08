<?php
// Supervisor Team Management API
// POST /mobileappapis/supervisor/team_manage.php?action=create|update|delete
// GET  /mobileappapis/supervisor/team_manage.php?action=list (optional)

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/../../actions/team_controller.php';

try {
    $user = sup_get_authenticated_user();
    $db = sup_get_db();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Parse JSON body when POST
    $raw = file_get_contents('php://input');
    $payload = [];
    if ($method === 'POST' && $raw !== false && strlen(trim($raw)) > 0) {
        $payload = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sup_send_error_response('Invalid JSON: ' . json_last_error_msg(), 400);
        }
    }

    // Only supervisors (and allowed roles) may create/update teams
    $allowed = ['Supervisor', 'Site Supervisor', 'Area Manager', 'Manager'];
    if (!in_array($user->user_type, $allowed)) {
        sup_send_error_response('Forbidden', 403);
    }

    $controller = new TeamController();

    switch ($action) {
        case 'list':
            // Return teams this supervisor leads
            $stmt = $db->prepare("SELECT DISTINCT t.id, t.team_name, t.description, t.created_at
                                   FROM teams t 
                                   JOIN team_members tm ON tm.team_id = t.id 
                                   WHERE tm.user_id = ? AND tm.role = 'Supervisor' 
                                   ORDER BY t.created_at DESC");
            $stmt->execute([$user->id]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sup_send_json_response(['success' => true, 'teams' => $teams]);

        case 'members':
            $teamId = $_GET['team_id'] ?? $payload['team_id'] ?? null;
            if (!$teamId) sup_send_error_response('team_id is required', 400);
            $stmt = $db->prepare("SELECT tm.user_id as id,
                                         CONCAT(u.first_name, ' ', u.surname) AS name,
                                         u.user_type AS role,
                                         u.mobile_number AS phone
                                    FROM team_members tm
                                    JOIN users u ON u.id = tm.user_id
                                   WHERE tm.team_id = ?
                                     AND u.user_type NOT IN ('Supervisor','Site Supervisor','Admin')");
            $stmt->execute([$teamId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sup_send_json_response(['success' => true, 'members' => $members]);

        case 'candidates':
            // Candidate pool: all guards with mobile access; optionally exclude existing team members if team_id provided
            $teamId = $_GET['team_id'] ?? $payload['team_id'] ?? null;
            $params = [];
            $excludeSql = '';
            if ($teamId) {
                $excludeSql = 'AND u.id NOT IN (SELECT user_id FROM team_members WHERE team_id = ?)';
                $params[] = $teamId;
            }
            $sql = "SELECT u.id,
                           CONCAT(u.first_name, ' ', u.surname) AS name,
                           u.user_type AS role,
                           u.mobile_number AS phone
                      FROM users u
                     WHERE u.user_type IN ('Guard','Security Guard')
                       AND u.mobile_access = 1
                       $excludeSql
                     ORDER BY u.first_name, u.surname";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sup_send_json_response(['success' => true, 'candidates' => $candidates]);

        case 'create':
            $data = [
                'team_name'     => trim($payload['team_name'] ?? ''),
                'supervisor_id' => $payload['supervisor_id'] ?? $user->id, // default to self
                'description'   => trim($payload['description'] ?? ''),
                'team_members'  => is_array($payload['team_members'] ?? null) ? $payload['team_members'] : []
            ];
            $result = $controller->createTeam($data);
            if (!$result['success']) sup_send_error_response($result['error'] ?? 'Create failed', 400);
            sup_send_json_response(['success' => true, 'team_id' => $result['team_id'], 'message' => $result['message'] ?? 'Created']);

        case 'update':
            $data = [
                'team_id'       => $payload['team_id'] ?? null,
                'team_name'     => trim($payload['team_name'] ?? ''),
                'supervisor_id' => $payload['supervisor_id'] ?? $user->id,
                'description'   => trim($payload['description'] ?? ''),
                'team_members'  => is_array($payload['team_members'] ?? null) ? $payload['team_members'] : []
            ];
            $result = $controller->updateTeam($data);
            if (!$result['success']) sup_send_error_response($result['error'] ?? 'Update failed', 400);
            sup_send_json_response(['success' => true, 'message' => $result['message'] ?? 'Updated']);

        case 'delete':
            $teamId = $payload['team_id'] ?? null;
            if (!$teamId) sup_send_error_response('team_id is required', 400);
            $result = $controller->deleteTeam($teamId);
            if (!$result['success']) sup_send_error_response($result['error'] ?? 'Delete failed', 400);
            sup_send_json_response(['success' => true, 'message' => $result['message'] ?? 'Deleted']);

        default:
            sup_send_error_response('Invalid action', 400);
    }
} catch (Throwable $e) {
    @error_log('[SUPERVISOR_API][TEAM_MANAGE] ' . $e->getMessage());
    sup_send_error_response('Server error', 500);
}


