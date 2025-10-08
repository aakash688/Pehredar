<?php
require_once __DIR__ . '/../helpers/database.php';

header('Content-Type: application/json');
if (!is_authenticated() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $sub_action = $data['sub_action'] ?? null;
    $db = new Database();

    if ($sub_action === 'add' && !empty($data['type_name'])) {
        $type_name = trim($data['type_name']);
        $description = trim($data['description'] ?? '');
        
        // Check if type name already exists
        $exists = $db->query("SELECT COUNT(*) as cnt FROM client_types WHERE type_name = ?", [$type_name])->fetch()['cnt'];
        if ($exists > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A client type with this name already exists.']);
            exit;
        }
        
        $sql = "INSERT INTO client_types (type_name, description, name) VALUES (?, ?, ?)";
        $db->query($sql, [$type_name, $description, $type_name]);
        echo json_encode(['success' => true, 'message' => 'Client type added successfully.']);
    
    } elseif ($sub_action === 'edit' && !empty($data['id'])) {
        $id = $data['id'];
        $type_name = trim($data['type_name']);
        $description = trim($data['description'] ?? '');
        
        // Check if type name already exists for another ID
        $exists = $db->query("SELECT COUNT(*) as cnt FROM client_types WHERE type_name = ? AND id != ?", 
            [$type_name, $id])->fetch()['cnt'];
        if ($exists > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Another client type with this name already exists.']);
            exit;
        }
        
        $sql = "UPDATE client_types SET type_name=?, description=?, name=? WHERE id=?";
        $db->query($sql, [$type_name, $description, $type_name, $id]);
        echo json_encode(['success' => true, 'message' => 'Client type updated successfully.']);

    } elseif ($sub_action === 'delete' && !empty($data['id'])) {
        $id = $data['id'];
        
        // Check if client type is in use
        $in_use = $db->query("SELECT COUNT(*) as cnt FROM society_onboarding_data WHERE client_type_id = ?", [$id])->fetch()['cnt'];
        if ($in_use > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This client type is in use and cannot be deleted.']);
            exit;
        }
        
        $sql = "DELETE FROM client_types WHERE id = ?";
        $db->query($sql, [$id]);
        echo json_encode(['success' => true, 'message' => 'Client type deleted successfully.']);
    
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action or missing parameters.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
} 