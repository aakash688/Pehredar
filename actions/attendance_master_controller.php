<?php
// actions/attendance_master_controller.php

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_attendance_types':
        get_attendance_types();
        break;
    case 'create_attendance_type':
        create_attendance_type();
        break;
    case 'update_attendance_type':
        update_attendance_type();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified.'], 400);
        break;
}

function get_attendance_types() {
    $db = new Database();
    $types = $db->query("SELECT * FROM attendance_master WHERE is_active = TRUE")->fetchAll();
    json_response(['success' => true, 'data' => $types]);
}

function create_attendance_type() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['code']) || empty($data['name']) || !isset($data['multiplier'])) {
        json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
        return;
    }

    $exists = $db->query("SELECT id FROM attendance_master WHERE code = ?", [$data['code']])->fetch();
    if ($exists) {
        json_response(['success' => false, 'message' => 'Attendance code must be unique.'], 400);
        return;
    }

    $sql = "INSERT INTO attendance_master (code, name, description, multiplier) VALUES (?, ?, ?, ?)";
    $db->query($sql, [$data['code'], $data['name'], $data['description'], $data['multiplier']]);

    json_response(['success' => true, 'message' => 'Attendance type created successfully.']);
}

function update_attendance_type() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        json_response(['success' => false, 'message' => 'Missing ID.'], 400);
        return;
    }

    if (isset($data['is_active']) && $data['is_active'] === false) {
        $sql = "UPDATE attendance_master SET is_active = FALSE WHERE id = ?";
        $db->query($sql, [$data['id']]);
        json_response(['success' => true, 'message' => 'Attendance type deactivated successfully.']);
        return;
    }


    if (empty($data['code']) || empty($data['name']) || !isset($data['multiplier'])) {
        json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
        return;
    }

    $exists = $db->query("SELECT id FROM attendance_master WHERE code = ? AND id != ?", [$data['code'], $data['id']])->fetch();
    if ($exists) {
        json_response(['success' => false, 'message' => 'Attendance code must be unique.'], 400);
        return;
    }

    $sql = "UPDATE attendance_master SET code = ?, name = ?, description = ?, multiplier = ?, is_active = ? WHERE id = ?";
    $db->query($sql, [$data['code'], $data['name'], $data['description'], $data['multiplier'], $data['is_active'], $data['id']]);

    json_response(['success' => true, 'message' => 'Attendance type updated successfully.']);
}

function delete_attendance_type() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        json_response(['success' => false, 'message' => 'Missing ID.'], 400);
        return;
    }

    $sql = "UPDATE attendance_master SET is_active = FALSE WHERE id = ?";
    $db->query($sql, [$data['id']]);

    json_response(['success' => true, 'message' => 'Attendance type deactivated successfully.']);
} 