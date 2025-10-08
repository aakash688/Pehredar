<?php
/**
 * Client API - Mobile App Configuration Management
 * This API handles client data for mobile app settings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get API key from Authorization header or query parameter
$apiKey = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

// Validate API key
if (!$apiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'API key is required',
        'error' => 'MISSING_API_KEY'
    ]);
    exit();
}

// Simple API key validation (you can make this more sophisticated)
$validApiKeys = [
    'cl_9976c05b189cdbea51526558173993ef',
    'cl_0244d0403d7a6bcc3673f3346ea4a328',
    'cl_08cf1e1a683ce8937ccaf167aaab16cd',
    'test_api_key_12345'
];

if (!in_array($apiKey, $validApiKeys)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid API key',
        'error' => 'INVALID_API_KEY'
    ]);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Handle different actions
switch ($action) {
    case 'info':
        handleGetInfo($apiKey);
        break;
    
    case 'create':
        handleCreateClient($input, $apiKey);
        break;
    
    case 'update':
        handleUpdateClient($input, $apiKey);
        break;
    
    case 'delete':
        handleDeleteClient($input, $apiKey);
        break;
    
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Supported actions: info, create, update, delete',
            'error' => 'INVALID_ACTION'
        ]);
        break;
}

/**
 * Get client information
 */
function handleGetInfo($apiKey) {
    // Mock client data - in a real application, this would come from a database
    $clientData = [
        'client_id' => 'Rayanpf',
        'id' => 1,
        'client_name' => 'Updated Client Name ' . date('Y-m-d H:i:s'),
        'client_email' => 'rpf@gmail.com',
        'company_name' => 'Updated Company Name',
        'logo_url' => 'https://example.com/updated-logo.png',
        'status' => 'active',
        'api_key' => $apiKey
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Client information retrieved successfully',
        'data' => $clientData,
        'timestamp' => date('c')
    ]);
}

/**
 * Create a new client
 */
function handleCreateClient($data, $apiKey) {
    // Validate required fields
    $requiredFields = ['client_name', 'client_email', 'company_name'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field",
                'error' => 'MISSING_FIELD'
            ]);
            return;
        }
    }
    
    // Validate client_id if provided
    if (isset($data['client_id']) && !empty($data['client_id'])) {
        $validation = validateClientId($data['client_id']);
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => implode('. ', $validation['errors']),
                'error' => 'INVALID_CLIENT_ID',
                'criteria' => $validation['criteria']
            ]);
            return;
        }
    }
    
    // Generate client_id if not provided
    $clientId = $data['client_id'] ?? generateClientId();
    
    // Mock response - in a real application, this would save to database
    $response = [
        'success' => true,
        'message' => 'Client created successfully',
        'data' => [
            'id' => rand(100, 999),
            'client_id' => $clientId,
            'installation_id' => 'INST_' . date('Ymd') . '_' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'api_key' => 'cl_' . substr(md5(uniqid()), 0, 32),
            'api_secret' => 'secret' . substr(md5(uniqid()), 0, 16)
        ],
        'timestamp' => date('c')
    ];
    
    http_response_code(201);
    echo json_encode($response);
}

/**
 * Update client information
 */
function handleUpdateClient($data, $apiKey) {
    // Validate client_id if provided
    if (isset($data['client_id']) && !empty($data['client_id'])) {
        $validation = validateClientId($data['client_id']);
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => implode('. ', $validation['errors']),
                'error' => 'INVALID_CLIENT_ID',
                'criteria' => $validation['criteria']
            ]);
            return;
        }
    }
    
    // Mock response - in a real application, this would update the database
    $response = [
        'success' => true,
        'message' => 'Client updated successfully',
        'data' => [
            'client_id' => $data['client_id'] ?? 'Rayanpf',
            'id' => 1,
            'client_name' => $data['client_name'] ?? 'Updated Client Name ' . date('Y-m-d H:i:s'),
            'client_email' => $data['client_email'] ?? 'rpf@gmail.com',
            'company_name' => $data['company_name'] ?? 'Updated Company',
            'logo_url' => $data['logo_url'] ?? 'https://example.com/logo.png',
            'status' => 'active'
        ],
        'updated_fields' => array_keys($data),
        'timestamp' => date('c')
    ];
    
    echo json_encode($response);
}

/**
 * Delete client
 */
function handleDeleteClient($data, $apiKey) {
    // Mock response - in a real application, this would delete from database
    echo json_encode([
        'success' => true,
        'message' => 'Client deleted successfully',
        'timestamp' => date('c')
    ]);
}

/**
 * Validate client ID format and constraints
 */
function validateClientId($clientId) {
    $errors = [];
    $criteria = [
        'max_length' => 10,
        'min_length' => 3,
        'allowed_characters' => 'alphanumeric and underscore only',
        'reserved_prefixes' => ['CLI_', 'INST_', 'API_']
    ];
    
    // Length validation
    if (strlen($clientId) > 10) {
        $errors[] = 'Client ID cannot exceed 10 characters';
    }
    if (strlen($clientId) < 3) {
        $errors[] = 'Client ID must be at least 3 characters';
    }
    
    // Format validation
    if (!preg_match('/^[A-Za-z0-9_]+$/', $clientId)) {
        $errors[] = 'Client ID can only contain letters, numbers, and underscores';
    }
    
    // Reserved prefix validation
    $reservedPrefixes = ['CLI_', 'INST_', 'API_'];
    foreach ($reservedPrefixes as $prefix) {
        if (strpos($clientId, $prefix) === 0) {
            $errors[] = "Client ID cannot start with reserved prefixes ($prefix)";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'criteria' => $criteria
    ];
}

/**
 * Generate a unique client ID
 */
function generateClientId() {
    $prefix = 'CLI_';
    $suffix = strtoupper(substr(md5(uniqid()), 0, 6));
    return $prefix . $suffix;
}
?>
