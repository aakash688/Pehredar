<?php
// actions/deduction_master_controller.php
session_start();
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = new Database();

try {
    switch ($action) {
        case 'get_all':
            getDeductionTypes($db);
            break;
        case 'get_active':
            getActiveDeductionTypes($db);
            break;
        case 'create':
            createDeductionType($db);
            break;
        case 'update':
            updateDeductionType($db);
            break;
        case 'delete':
            deleteDeductionType($db);
            break;
        case 'toggle_status':
            toggleDeductionStatus($db);
            break;
        default:
            sendJsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}

function getDeductionTypes($db) {
    $query = "
        SELECT 
            dm.*,
            u.first_name,
            u.surname
        FROM deduction_master dm
        LEFT JOIN users u ON dm.created_by = u.id
        ORDER BY dm.created_at DESC
    ";
    
    $deductions = $db->query($query)->fetchAll();
    sendJsonResponse(true, 'Deduction types retrieved successfully', $deductions);
}

function getActiveDeductionTypes($db) {
    $query = "
        SELECT 
            id,
            deduction_name,
            deduction_code,
            description
        FROM deduction_master 
        WHERE is_active = 1
        ORDER BY deduction_name ASC
    ";
    
    $deductions = $db->query($query)->fetchAll();
    sendJsonResponse(true, 'Active deduction types retrieved successfully', $deductions);
}

function createDeductionType($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, 'Invalid JSON data');
        return;
    }
    
    $deduction_name = trim($input['deduction_name'] ?? '');
    $deduction_code = trim($input['deduction_code'] ?? '');
    $description = trim($input['description'] ?? '');
    $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    $created_by = $_SESSION['user_id'] ?? null;
    
    // Validation
    if (empty($deduction_name)) {
        sendJsonResponse(false, 'Deduction name is required');
        return;
    }
    
    if (empty($deduction_code)) {
        sendJsonResponse(false, 'Deduction code is required');
        return;
    }
    
    // Check if code already exists
    $checkQuery = "SELECT id FROM deduction_master WHERE deduction_code = ?";
    $existing = $db->query($checkQuery, [$deduction_code])->fetch();
    
    if ($existing) {
        sendJsonResponse(false, 'Deduction code already exists');
        return;
    }
    
    $query = "
        INSERT INTO deduction_master 
        (deduction_name, deduction_code, description, is_active, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ";
    
    $result = $db->query($query, [
        $deduction_name,
        $deduction_code,
        $description,
        $is_active,
        $created_by
    ]);
    
    if ($result) {
        $newId = $db->lastInsertId();
        sendJsonResponse(true, 'Deduction type created successfully', ['id' => $newId]);
    } else {
        sendJsonResponse(false, 'Failed to create deduction type');
    }
}

function updateDeductionType($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, 'Invalid JSON data');
        return;
    }
    
    $id = (int)($input['id'] ?? 0);
    $deduction_name = trim($input['deduction_name'] ?? '');
    $deduction_code = trim($input['deduction_code'] ?? '');
    $description = trim($input['description'] ?? '');
    $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    
    // Validation
    if ($id <= 0) {
        sendJsonResponse(false, 'Invalid deduction ID');
        return;
    }
    
    if (empty($deduction_name)) {
        sendJsonResponse(false, 'Deduction name is required');
        return;
    }
    
    if (empty($deduction_code)) {
        sendJsonResponse(false, 'Deduction code is required');
        return;
    }
    
    // Check if code already exists (excluding current record)
    $checkQuery = "SELECT id FROM deduction_master WHERE deduction_code = ? AND id != ?";
    $existing = $db->query($checkQuery, [$deduction_code, $id])->fetch();
    
    if ($existing) {
        sendJsonResponse(false, 'Deduction code already exists');
        return;
    }
    
    $query = "
        UPDATE deduction_master 
        SET deduction_name = ?, deduction_code = ?, description = ?, is_active = ?
        WHERE id = ?
    ";
    
    $result = $db->query($query, [
        $deduction_name,
        $deduction_code,
        $description,
        $is_active,
        $id
    ]);
    
    if ($result) {
        sendJsonResponse(true, 'Deduction type updated successfully');
    } else {
        sendJsonResponse(false, 'Failed to update deduction type');
    }
}

function deleteDeductionType($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, 'Invalid JSON data');
        return;
    }
    
    $id = (int)($input['id'] ?? 0);
    
    if ($id <= 0) {
        sendJsonResponse(false, 'Invalid deduction ID');
        return;
    }
    
    // Check if deduction exists
    $checkQuery = "SELECT id FROM deduction_master WHERE id = ?";
    $exists = $db->query($checkQuery, [$id])->fetch();
    
    if (!$exists) {
        sendJsonResponse(false, 'Deduction type not found');
        return;
    }
    
    // Check if deduction is being used in salary records
    $usageQuery = "SELECT COUNT(*) as count FROM salary_deductions WHERE deduction_master_id = ?";
    $usage = $db->query($usageQuery, [$id])->fetch();
    
    if ($usage['count'] > 0) {
        sendJsonResponse(false, 'Cannot delete deduction type as it is being used in salary records');
        return;
    }
    
    $query = "DELETE FROM deduction_master WHERE id = ?";
    $result = $db->query($query, [$id]);
    
    if ($result) {
        sendJsonResponse(true, 'Deduction type deleted successfully');
    } else {
        sendJsonResponse(false, 'Failed to delete deduction type');
    }
}

function toggleDeductionStatus($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, 'Invalid JSON data');
        return;
    }
    
    $id = (int)($input['id'] ?? 0);
    
    // Handle different boolean representations
    $is_active_raw = $input['is_active'] ?? false;
    if (is_string($is_active_raw)) {
        $is_active = in_array(strtolower($is_active_raw), ['true', '1', 'yes', 'on']);
    } else {
        $is_active = (bool)$is_active_raw;
    }
    
    if ($id <= 0) {
        sendJsonResponse(false, 'Invalid deduction ID');
        return;
    }
    
    // Check if deduction exists
    $checkQuery = "SELECT id FROM deduction_master WHERE id = ?";
    $exists = $db->query($checkQuery, [$id])->fetch();
    
    if (!$exists) {
        sendJsonResponse(false, 'Deduction type not found');
        return;
    }
    
    $query = "UPDATE deduction_master SET is_active = ? WHERE id = ?";
    $result = $db->query($query, [$is_active ? 1 : 0, $id]);
    
    if ($result) {
        $status = $is_active ? 'activated' : 'deactivated';
        sendJsonResponse(true, "Deduction type {$status} successfully");
    } else {
        sendJsonResponse(false, 'Failed to update deduction status');
    }
}
