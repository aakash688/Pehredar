<?php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_site_supervisors':
        get_site_supervisors();
        break;
    case 'get_available_supervisors':
        get_available_supervisors();
        break;
    case 'assign_supervisors':
        assign_supervisors();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action for supervisor controller.'], 400);
        break;
}

function get_site_supervisors() {
    $site_id = $_POST['site_id'] ?? 0;
    if (!$site_id) {
        json_response(['success' => false, 'error' => 'Site ID is required.'], 400);
        return;
    }

    try {
        $db = new Database();
        $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.surname) as name, u.user_type, u.profile_photo
                  FROM users u
                  JOIN supervisor_site_assignments ssa ON u.id = ssa.supervisor_id
                  WHERE ssa.site_id = ?";
        $supervisors = $db->query($query, [$site_id])->fetchAll();
        json_response(['success' => true, 'supervisors' => $supervisors]);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

function get_available_supervisors() {
    $site_id = $_POST['site_id'] ?? 0;
    if (!$site_id) {
        json_response(['success' => false, 'error' => 'Site ID is required.'], 400);
        return;
    }

    try {
        $db = new Database();
        $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.surname) as name, u.user_type, u.profile_photo
                  FROM users u
                  WHERE u.user_type IN ('Supervisor', 'Site Supervisor')
                  AND u.id NOT IN (SELECT supervisor_id FROM supervisor_site_assignments WHERE site_id = ?)";
        $supervisors = $db->query($query, [$site_id])->fetchAll();
        json_response(['success' => true, 'supervisors' => $supervisors]);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

function assign_supervisors() {
    $site_id = $_POST['site_id'] ?? 0;
    $supervisor_ids = json_decode($_POST['supervisor_ids'] ?? '[]', true);

    if (!$site_id) {
        json_response(['success' => false, 'error' => 'Site ID is required.'], 400);
        return;
    }

    $db = new Database();
    $db->beginTransaction();
    try {
        // First, remove all existing assignments for this site
        $db->query("DELETE FROM supervisor_site_assignments WHERE site_id = ?", [$site_id]);

        // Then, add the new assignments
        if (!empty($supervisor_ids)) {
            $stmt = $db->prepare("INSERT INTO supervisor_site_assignments (site_id, supervisor_id) VALUES (?, ?)");
            foreach ($supervisor_ids as $supervisor_id) {
                $stmt->execute([$site_id, $supervisor_id]);
            }
        }

        $db->commit();
        json_response(['success' => true, 'message' => 'Assignments updated successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
} 