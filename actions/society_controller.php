<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Ensure Endroid QR Code is available
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Color\Color;

// A simple router to handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'onboard_society':
        onboard_society();
        break;
    case 'update_society':
        update_society();
        break;
    case 'generate_society_qr_code':
        generate_society_qr_code();
        break;
    case 'get_societies_by_supervisor':
        get_societies_by_supervisor();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}

/**
 * Handles the onboarding of a new society, including all related data.
 */
function onboard_society() {
    $db = new Database();
    
    // Log all incoming POST data for debugging
    error_log("Society Onboarding POST Data: " . print_r($_POST, true));
    
    $db->beginTransaction();

    try {
        // Validate required fields
        $required_fields = [
            'society_name', 'street_address', 'client_name', 
            'client_phone', 'client_email', 'client_username', 
            'client_password', 'client_type_id'
        ];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
        }

        // Validate client type exists in the database
        $client_type_stmt = $db->prepare("SELECT COUNT(*) FROM client_types WHERE id = ?");
        $client_type_stmt->execute([$_POST['client_type_id']]);
        if ($client_type_stmt->fetchColumn() == 0) {
            throw new Exception("Invalid client type selected.");
        }

        // --- 1. Create Society Onboarding Data ---
        $onboarding_columns = [
            'society_name', 'client_type_id', 'street_address', 'address', 'city', 'district', 'state', 'pin_code', 'gst_number',
            'latitude', 'longitude', 'onboarding_date', 'contract_expiry_date', 'compliance_status', 'service_charges_enabled', 'service_charges_percentage', 'qr_code',
            'guards', 'guard_client_rate', 'dogs', 'dog_client_rate',
            'armed_guards', 'armed_client_rate', 'housekeeping', 'housekeeping_client_rate', 
            'bouncers', 'bouncer_client_rate', 'site_supervisors',
            'site_supervisor_client_rate', 'supervisors', 'supervisor_client_rate'
        ];
        
        $placeholders = implode(', ', array_fill(0, count($onboarding_columns), '?'));
        $column_sql = implode(', ', $onboarding_columns);

        $stmt = $db->prepare("INSERT INTO society_onboarding_data ($column_sql) VALUES ($placeholders)");

        $values = [];
        foreach ($onboarding_columns as $col) {
            if (str_ends_with($col, '_date') && empty($_POST[$col])) {
                $values[] = null;
            } elseif ($col === 'address') {
                // Use street_address as the default value for address field
                $values[] = $_POST['street_address'] ?? '';
            } elseif ($col === 'client_type_id') {
                // Explicitly add client type
                $values[] = $_POST['client_type_id'];
            } elseif ($col === 'service_charges_enabled') {
                // Handle service charges enabled
                $values[] = isset($_POST['service_charges_enabled']) ? (int)$_POST['service_charges_enabled'] : 0;
            } elseif ($col === 'service_charges_percentage') {
                // Handle service charges percentage - only set if enabled
                $serviceChargeEnabled = isset($_POST['service_charges_enabled']) ? (int)$_POST['service_charges_enabled'] : 0;
                if ($serviceChargeEnabled === 1 && !empty($_POST['service_charges_percentage'])) {
                    $values[] = (float)$_POST['service_charges_percentage'];
                } else {
                    $values[] = null;
                }
            } elseif (str_ends_with($col, '_rate')) {
                // Ensure rate fields are numeric
                $values[] = isset($_POST[$col]) ? floatval($_POST[$col]) : 0.00;
            } else {
                $values[] = $_POST[$col] ?? 0;
            }
        }
        
        try {
            $stmt->execute($values);
            $society_id = $db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Society Insertion Error: " . $e->getMessage());
            error_log("SQL Error Info: " . print_r($stmt->errorInfo(), true));
            throw $e;
        }

        // --- 2. Create Primary Client User ---
        if (!empty($_POST['client_password'])) {
            $password_salt = bin2hex(random_bytes(16)); // Generate a random salt
            $password_hash = password_hash($_POST['client_password'], PASSWORD_DEFAULT);

            try {
                $db->query(
                    "INSERT INTO clients_users (society_id, name, phone, email, username, password_hash, password_salt, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                    [$society_id, $_POST['client_name'], $_POST['client_phone'], $_POST['client_email'], $_POST['client_username'], $password_hash, $password_salt]
                );
            } catch (PDOException $e) {
                error_log("Client User Insertion Error: " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($db->getPdo()->errorInfo(), true));
                throw $e;
            }
        }

        $db->commit();
        json_response(['success' => true, 'message' => 'Client onboarded successfully!']);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Onboarding Error: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        json_response(['success' => false, 'message' => 'An error occurred during onboarding: ' . $e->getMessage()], 500);
    }
}

/**
 * Handles updating an existing society's information.
 */
function update_society() {
    $db = new Database();
    
    $db->beginTransaction();
    
    try {
        $society_id = $_POST['id'] ?? null;
        if (!$society_id) {
            throw new Exception("Society ID is missing.");
        }

        // --- 1. Update Society Onboarding Data ---
        $onboarding_columns = [
            'society_name', 'client_type_id', 'street_address', 'address', 'city', 'district', 'state', 'pin_code', 'gst_number',
            'latitude', 'longitude', 'onboarding_date', 'contract_expiry_date', 'compliance_status', 'service_charges_enabled', 'service_charges_percentage', 'qr_code',
            'guards', 'guard_client_rate', 'dogs', 'dog_client_rate',
            'armed_guards', 'armed_client_rate', 'housekeeping', 'housekeeping_client_rate', 
            'bouncers', 'bouncer_client_rate', 'site_supervisors',
            'site_supervisor_client_rate', 'supervisors', 'supervisor_client_rate'
        ];

        $set_clauses = [];
        $values = [];
        foreach ($onboarding_columns as $col) {
            $set_clauses[] = "$col = ?";
            if (str_ends_with($col, '_date') && empty($_POST[$col])) {
                $values[] = null;
            } elseif ($col === 'address') {
                // Use street_address as the default value for address field
                $values[] = $_POST['street_address'] ?? '';
            } elseif ($col === 'service_charges_enabled') {
                // Handle service charges enabled
                $values[] = isset($_POST['service_charges_enabled']) ? (int)$_POST['service_charges_enabled'] : 0;
            } elseif ($col === 'service_charges_percentage') {
                // Handle service charges percentage - only set if enabled
                $serviceChargeEnabled = isset($_POST['service_charges_enabled']) ? (int)$_POST['service_charges_enabled'] : 0;
                if ($serviceChargeEnabled === 1 && !empty($_POST['service_charges_percentage'])) {
                    $values[] = (float)$_POST['service_charges_percentage'];
                } else {
                    $values[] = null;
                }
            } elseif (str_ends_with($col, '_rate')) {
                // Ensure rate fields are numeric
                $values[] = isset($_POST[$col]) ? floatval($_POST[$col]) : 0.00;
            } else {
                $values[] = $_POST[$col] ?? 0;
            }
        }
        $values[] = $society_id; // For the WHERE clause

        $stmt = $db->prepare("UPDATE society_onboarding_data SET " . implode(', ', $set_clauses) . " WHERE id = ?");
        $stmt->execute($values);

        // --- 2. Update or Create Primary Client User ---
        $user_stmt = $db->query("SELECT id FROM clients_users WHERE society_id = ? AND is_primary = 1", [$society_id]);
        $primary_user = $user_stmt->fetch();

        $user_data = [
            $_POST['client_name'], $_POST['client_phone'], $_POST['client_email'], $_POST['client_username'],
        ];

        if ($primary_user) { // User exists, update them
            $update_sql = "UPDATE clients_users SET name = ?, phone = ?, email = ?, username = ?";
            if (!empty($_POST['client_password'])) {
                $update_sql .= ", password_hash = ?";
                $user_data[] = password_hash($_POST['client_password'], PASSWORD_DEFAULT);
            }
            $user_data[] = $primary_user['id'];
            $db->query($update_sql . " WHERE id = ?", $user_data);

        } else { // No primary user, create one
            if (!empty($_POST['client_password'])) {
                $user_data[] = password_hash($_POST['client_password'], PASSWORD_DEFAULT);
                $user_data[] = $society_id;
                $db->query("INSERT INTO clients_users (name, phone, email, username, password_hash, is_primary, society_id) VALUES (?, ?, ?, ?, ?, 1, ?)", $user_data);
            }
        }

        $db->commit();
        json_response(['success' => true, 'message' => 'Client updated successfully!']);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Update Error: " . $e->getMessage());
        json_response(['success' => false, 'message' => 'An error occurred during update: ' . $e->getMessage()], 500);
    }
} 

/**
 * Generate a QR code for a society with detailed information
 */
function generate_society_qr_code() {
    // Ensure proper headers for JSON response
    header('Content-Type: application/json');

    // Log the raw input for debugging
    $rawInput = file_get_contents('php://input');
    
    // Validate raw input
    if ($rawInput === false) {
        error_log("Failed to read input stream");
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to read input stream',
            'raw_input' => null
        ]);
        exit(400);
    }

    // Log raw input for debugging
    error_log("Raw QR Code Generation Input: " . $rawInput);

    // Attempt to parse JSON with error handling
    $input = json_decode($rawInput, true);
    
    // Log JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decoding Error: " . json_last_error_msg());
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid JSON input: ' . json_last_error_msg(),
            'raw_input' => $rawInput
        ]);
        exit(400);
    }
    
    if (!is_array($input)) {
        error_log("Input is not an array");
        echo json_encode([
            'success' => false, 
            'error' => 'Input must be a JSON object',
            'input_type' => gettype($input)
        ]);
        exit(400);
    }
    
    // Log input data for debugging
    error_log("Parsed QR Code Input: " . print_r($input, true));
    
    // Validate required fields with more detailed logging
    $requiredFields = ['name', 'address', 'contact', 'email'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: ' . implode(', ', $missingFields),
            'missing_fields' => $missingFields
        ]);
        exit(400);
    }
    
    try {
        // Prepare QR code content with additional error handling
        $qrContent = "BEGIN:VCARD\n";
        $qrContent .= "VERSION:3.0\n";
        $qrContent .= "FN:" . htmlspecialchars($input['name']) . "\n";
        $qrContent .= "ORG:" . htmlspecialchars($input['name']) . "\n";
        $qrContent .= "ADR:" . htmlspecialchars($input['address']) . "\n";
        $qrContent .= "TEL:" . htmlspecialchars($input['contact']) . "\n";
        $qrContent .= "EMAIL:" . htmlspecialchars($input['email']) . "\n";
        $qrContent .= "END:VCARD";
        
        // Create QR Code with new API
        $qrCode = new QrCode($qrContent, size: 300, margin: 10);
        
        // Create writer
        $writer = new PngWriter();
        
        // Add logo (optional)
        $logoPath = __DIR__ . '/../Comapany/assets/logo-6858f5cfb718c-561-5610966_cyber-security-logo-png-transparent-png-removebg-preview.png';
        $logo = null;
        if (file_exists($logoPath)) {
            $logo = new Logo($logoPath, resizeToWidth: 60, resizeToHeight: 60);
        } else {
            error_log("Logo file not found: " . $logoPath);
        }
        
        // Add label
        $label = new Label(
            text: $input['name'],
            font: new OpenSans(size: 12),
            textColor: new Color(255, 255, 255)
        );
        
        // Generate QR Code
        $result = $writer->write($qrCode, $logo, $label);
        
        // Convert to data URI
        $dataUri = 'data:image/png;base64,' . base64_encode($result->getString());
        
        // Return successful response
        echo json_encode([
            'success' => true, 
            'qr_code_uri' => $dataUri
        ]);
        exit(200);
    } catch (Exception $e) {
        error_log("QR Code Generation Error: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to generate QR code: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        exit(500);
    }
} 

/**
 * Retrieves societies assigned to a specific supervisor.
 */
function get_societies_by_supervisor() {
    $supervisor_id = $_GET['supervisor_id'] ?? null;

    if (!$supervisor_id) {
        json_response(['success' => false, 'message' => 'Supervisor ID is required.'], 400);
        return;
    }

    try {
        $db = new Database();
        $stmt = $db->prepare(
            "SELECT s.id, s.society_name 
             FROM society_onboarding_data s
             JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
             WHERE ssa.supervisor_id = ?"
        );
        $stmt->execute([$supervisor_id]);
        $societies = $stmt->fetchAll();
        
        json_response(['success' => true, 'societies' => $societies]);
    } catch (Exception $e) {
        error_log("Error fetching societies by supervisor: " . $e->getMessage());
        json_response(['success' => false, 'message' => 'An error occurred while fetching societies.'], 500);
    }
} 