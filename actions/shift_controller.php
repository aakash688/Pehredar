<?php
// actions/shift_controller.php

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route to appropriate function
switch ($action) {
    case 'get_shifts':
        get_shifts();
        break;
    case 'create_shift':
        create_shift();
        break;
    case 'update_shift':
        update_shift();
        break;
    case 'delete_shift':
        delete_shift();
        break;
    case 'reactivate_shift':
        reactivate_shift();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified.'], 400);
        break;
}

/**
 * Get all shifts or filter by parameters
 */
function get_shifts() {
    $db = new Database();
    
    try {
        $is_active = $_GET['is_active'] ?? null;
        
        // Base query
        $query = "SELECT 
            id, 
            shift_name, 
            start_time, 
            end_time, 
            description, 
            is_active 
        FROM shift_master 
        WHERE 1=1";
        
        $params = [];
        
        // Add filters
        if ($is_active !== null) {
            $query .= " AND is_active = ?";
            $params[] = intval($is_active);
        }
        
        $query .= " ORDER BY shift_name";
        
        // Execute query
        $shifts = $db->query($query, $params)->fetchAll();
        
        json_response([
            'success' => true, 
            'data' => $shifts
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'Error fetching shifts: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Create a new shift
 */
function create_shift() {
    $db = new Database();
    
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $requiredFields = ['shift_name', 'start_time', 'end_time'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Prepare query
        $query = "INSERT INTO shift_master 
                  (shift_name, start_time, end_time, description) 
                  VALUES (?, ?, ?, ?)";
        
        // Execute query
        $db->query($query, [
            $input['shift_name'],
            $input['start_time'],
            $input['end_time'],
            $input['description'] ?? null
        ]);
        
        // Get the ID of the newly inserted shift
        $shiftId = $db->lastInsertId();
        
        json_response([
            'success' => true, 
            'message' => 'Shift created successfully',
            'data' => ['id' => $shiftId]
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'Error creating shift: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Update an existing shift
 */
function update_shift() {
    $db = new Database();
    
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("Shift ID is required");
        }
        
        // Prepare update query
        $updateFields = [];
        $params = [];
        
        // Fields that can be updated
        $allowedFields = [
            'shift_name', 'start_time', 'end_time', 'description', 
            'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No fields to update");
        }
        
        // Add ID to params
        $params[] = $input['id'];
        
        // Construct and execute query
        $query = "UPDATE shift_master 
                  SET " . implode(', ', $updateFields) . " 
                  WHERE id = ?";
        
        $db->query($query, $params);
        
        json_response([
            'success' => true, 
            'message' => 'Shift updated successfully'
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'Error updating shift: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Delete a shift
 */
function delete_shift() {
    $db = new Database();
    
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("Shift ID is required");
        }
        
        // First, set is_active to 0 instead of hard deleting
        $db->query(
            "UPDATE shift_master SET is_active = 0 WHERE id = ?", 
            [$input['id']]
        );
        
        json_response([
            'success' => true, 
            'message' => 'Shift deactivated successfully'
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'Error deactivating shift: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Reactivate a shift
 */
function reactivate_shift() {
    $db = new Database();
    
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("Shift ID is required");
        }
        
        // Set is_active to 1
        $db->query(
            "UPDATE shift_master SET is_active = 1 WHERE id = ?", 
            [$input['id']]
        );
        
        json_response([
            'success' => true, 
            'message' => 'Shift activated successfully'
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'Error activating shift: ' . $e->getMessage()
        ], 500);
    }
}
?> 