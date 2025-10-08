<?php
session_start();

// CRITICAL: Check installation BEFORE loading any database-dependent files
$config = require __DIR__ . '/config.php';

// Check if application is installed
if (!isset($config['installed']) || $config['installed'] !== true) {
    // Redirect to installation page
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $installUrl = $protocol . '://' . $host . $scriptDir . '/install/';

    header('Location: ' . $installUrl);
    exit('Application not installed. Please run the installer.');
}

// CRITICAL: Check license status BEFORE loading any application features
require_once __DIR__ . '/helpers/license_manager.php';

// Auto-check and expire licenses on every page load
try {
    $licenseManager = new LicenseManager();
    $licenseManager->performAutoExpiryCheck();
} catch (Exception $e) {
    error_log("Auto license expiry check error: " . $e->getMessage());
}
checkApplicationLicense();

// Only load these files AFTER confirming installation
require_once __DIR__ . '/vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/helpers/jwt_helper.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/file_helper.php';
require_once __DIR__ . '/helpers/qr_code_generator.php';
require_once __DIR__ . '/actions/settings_controller.php';
require_once __DIR__ . '/actions/team_controller.php';

// Helper function to generate vCard data
function generateVCard($employee) {
    $fullName = trim($employee['first_name'] . ' ' . $employee['surname']);
    $firstName = $employee['first_name'] ?? '';
    $lastName = $employee['surname'] ?? '';
    $organization = 'RYAN PROTECTION FORCE'; // Default organization
    $mobile = $employee['mobile_number'] ?? '';
    
    // Normalize mobile number for vCard (remove spaces, add country code if needed)
    $mobile = preg_replace('/[^0-9+]/', '', $mobile);
    if (!empty($mobile) && !str_starts_with($mobile, '+')) {
        $mobile = '+91' . $mobile; // Add India country code if not present
    }
    
    $vCard = "BEGIN:VCARD\n";
    $vCard .= "VERSION:3.0\n";
    $vCard .= "FN:" . $fullName . "\n";
    $vCard .= "N:" . $lastName . ";" . $firstName . ";;;\n";
    $vCard .= "ORG:" . $organization . "\n";
    if (!empty($mobile)) {
        $vCard .= "TEL;TYPE=CELL:" . $mobile . "\n";
    }
    $vCard .= "END:VCARD";
    
    return $vCard;
}

// Helper function to generate QR code URL
function generateQRCode($vCardData) {
    // Use QR Server API to generate QR code
    $encodedData = urlencode($vCardData);
    return "https://api.qrserver.com/v1/create-qr-code/?data=" . $encodedData . "&size=200x200&bgcolor=FFFFFF&color=000000&format=png";
}

// --- Database Connection (Optimized with Connection Pool) ---
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please check your configuration.');
}

// --- Helper Functions ---
function handleFileUpload($file, $userId, $type, $base_url) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    // The uploads directory should be relative to the project root, not using __DIR__
    // for consistency if the script is moved.
    $dir = 'uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $targetFile = "user_{$userId}_{$type}_" . uniqid() . ".$ext";
    $targetPath = "$dir/$targetFile";
    
    // move_uploaded_file uses file system paths
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return the relative path to be stored in the database
        return $targetPath;
    }
    return null;
}

// --- Routing & API Handling ---
$page = $_GET['page'] ?? 'login';
$action = $_GET['action'] ?? null;

// Define pages that don't require authentication
$public_pages = ['login', 'register', 'forgot_password'];

// If the requested page is not public and the user is not authenticated, redirect to login
if (!in_array($page, $public_pages) && !is_authenticated() && !$action) {
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'logout') {
    // Unset the cookie by setting its expiration time to the past
    setcookie('jwt', '', [
        'expires' => time() - 3600, // An hour in the past
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    // Destroy the session
    session_destroy();
    // Redirect to the login page
    header('Location: index.php?page=login');
    exit;
} else if ($page === 'get-supervisor-sites') {
    // API endpoint for getting supervisor sites
    include_once __DIR__ . '/helpers/database.php';
    
    // Create Database instance
    $db = new Database();
    $response = ['success' => false];
    
    try {
        // Get supervisor ID from query parameter
        $supervisor_id = isset($_GET['supervisor_id']) ? (int)$_GET['supervisor_id'] : 0;
        
        if ($supervisor_id <= 0) {
            throw new Exception("Invalid supervisor ID");
        }
        
        // Get all sites assigned to this supervisor
        $sites_query = "SELECT s.* 
                       FROM society_onboarding_data s
                       JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
                       WHERE ssa.supervisor_id = ?";
                       
        $sites = $db->query($sites_query, [$supervisor_id])->fetchAll();
        
        $response = [
            'success' => true,
            'sites' => $sites
        ];
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// API actions are triggered by non-GET requests or specific GET actions
$isApiAction = ($_SERVER['REQUEST_METHOD'] !== 'GET') || isset($_GET['action']);

if ($isApiAction) {
    // Determine if it's a JSON request, form-data, or GET with action
    $isJsonRequest = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    
    // Debug: Log request details
    error_log("Request Debug - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
    error_log("Request Debug - isJsonRequest: " . ($isJsonRequest ? 'TRUE' : 'FALSE'));
    error_log("Request Debug - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = $_GET;
    } elseif ($isJsonRequest) {
        $rawInput = file_get_contents('php://input');
        error_log("Request Debug - Raw input: " . $rawInput);
        $data = json_decode($rawInput, true);
        error_log("Request Debug - Parsed data: " . json_encode($data));
    } else {
        $data = $_POST;
    }
    
    $action = $_GET['action'] ?? $data['action'] ?? null;
    
    // For multipart/form-data with an 'action' field, $_POST will be populated.
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $data = $_POST;
    }

    if (in_array($action, ['get_site_supervisors', 'get_available_supervisors', 'assign_supervisors'])) {
        require_once __DIR__ . '/actions/supervisor_controller.php';
        exit;
    }

    if ($action) {
        header('Content-Type: application/json');
        switch ($action) {
            case 'login':
                try {
                    if (empty($data['email_id']) || empty($data['password'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Missing email or password']);
                        exit;
                    }

                    $user = null;
                    $user_type = null;
                    $society_info = null;

                    // 1. Check in 'users' table (Admins, Guards, etc.)
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email_id = ?");
                    $stmt->execute([$data['email_id']]);
                    $employee = $stmt->fetch();
                    
                    if ($employee && password_verify($data['password'], $employee['password'])) {
                        $user = $employee;
                        $user_type = $user['user_type']; // e.g., 'Admin', 'Guard'
                    } else {
                        // 2. If not found or password mismatch, check 'clients_users' table
                        $stmt = $pdo->prepare("SELECT cu.*, so.society_name 
                                               FROM clients_users cu 
                                               JOIN society_onboarding_data so ON cu.society_id = so.id
                                               WHERE cu.username = ? OR cu.email = ?");
                        $stmt->execute([$data['email_id'], $data['email_id']]);
                        $client = $stmt->fetch();

                        if ($client && hash('sha256', $data['password'] . $client['password_salt']) === $client['password_hash']) {
                            $user = $client;
                            $user_type = 'Client';
                            $society_info = [
                                'id' => $client['society_id'],
                                'name' => $client['society_name']
                            ];
                        }
                    }

                    if (!$user) {
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
                        exit;
                    }
                    
                    $full_name = 'N/A';
                    if ($user_type === 'Client') {
                        $full_name = $user['name'];
                    } else {
                        $full_name = $user['first_name'] . ' ' . $user['surname'];
                    }

                    // --- Successful Login ---
                    $user_id = $user['id'];
                    $role = $user_type; // Admin, Guard, Client etc.
                    
                    $base_url = rtrim($config['base_url'] ?? '', '/');
                    $profile_photo = $user['profile_photo'] ?? null;
                    if ($profile_photo && !filter_var($profile_photo, FILTER_VALIDATE_URL)) {
                        $profile_photo = $base_url . '/' . ltrim($profile_photo, '/');
                    }

                    $payload = [
                        'iat' => time(),
                        'jti' => base64_encode(random_bytes(32)),
                        'iss' => $config['base_url'],
                        'nbf' => time(),
                        'exp' => time() + (60 * 60 * $config['jwt']['expires_in_hours']), // Set expiration from config
                        'data' => [
                            'id' => $user_id,
                            'role' => $role,
                            'full_name' => $full_name,
                            'email' => $user['email_id'] ?? $user['email'],
                            'profile_photo' => $profile_photo,
                            'society' => $society_info
                        ]
                    ];

                    $jwt = JWT::encode($payload, $config['jwt']['secret'], 'HS256');

                    // Set JWT in a secure, HttpOnly cookie
                    setcookie('jwt', $jwt, [
                        'expires' => time() + (60 * 60 * $config['jwt']['expires_in_hours']),
                        'path' => '/',
                        'domain' => '', 
                        'secure' => isset($_SERVER['HTTPS']), // Set to true in production
                        'httponly' => true,
                        'samesite' => 'Lax' // Or 'Strict'
                    ]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful!'
                    ]);

                } catch (Exception $e) {
                    // Log the real error for debugging
                    error_log('Login Error: ' . $e->getMessage());
                    // Send a generic error to the client
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'An internal server error occurred. Please try again.']);
                }
                exit; // Stop script execution after handling action
            case 'register':
                // Basic validation for public registration
                $required_fields = ['first_name', 'surname', 'email_id', 'password'];
                foreach ($required_fields as $field) {
                    if (empty($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Missing required field: $field"]);
                        exit;
                    }
                }

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email_id = ?");
                $stmt->execute([$data['email_id']]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Email already in use']);
                    exit;
                }
                
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (first_name, surname, email_id, password) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['first_name'], $data['surname'], $data['email_id'], $data['password']]);
                
                http_response_code(201);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
                break;
            case 'enroll_employee':
                // For multipart form, data is in $_POST
                $data = $_POST;

                // Protected Action
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') {
                        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
                    }
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token']); exit;
                }

                // Manually define allowed fields instead of relying on a strict schema
                // This is more flexible for handling form data that includes non-DB fields.
                $allowedFields = [
                    'first_name', 'surname', 'date_of_birth', 'gender', 'mobile_number', 
                    'email_id', 'address', 'permanent_address', 'aadhar_number', 'pan_number',
                    'voter_id_number', 'passport_number', 'highest_qualification',
                    'esic_number', 'uan_number', 'pf_number',
                    'date_of_joining', 
                    'user_type', 'salary', 'shift_hours', 'advance_salary', 'bank_account_number', 'ifsc_code', 
                    'bank_name', 'password', 'web_access', 'mobile_access', 'pin_code'
                ];

                $insertData = [];
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $insertData[$field] = $data[$field];
                    }
                }
                
                if (empty($insertData['email_id'])) {
                     http_response_code(400); echo json_encode(['error' => 'Email is required.']); exit;
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email_id = ?");
                $stmt->execute([$insertData['email_id']]);
                if ($stmt->fetch()) { http_response_code(409); echo json_encode(['error' => 'Email already in use']); exit; }
                
                $insertData['password'] = password_hash($insertData['password'], PASSWORD_DEFAULT);
                $insertData['web_access'] = isset($data['web_access']) ? 1 : 0;
                $insertData['mobile_access'] = isset($data['mobile_access']) ? 1 : 0;
                
                $cols = implode(", ", array_map(fn($c) => "`$c`", array_keys($insertData)));
                $placeholders = implode(", ", array_fill(0, count($insertData), '?'));

                try {
                    $sql = "INSERT INTO users ($cols) VALUES ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($insertData));
                    $userId = $pdo->lastInsertId();

                    // --- Handle File Uploads ---
                    $fileUploads = [
                        'profile_photo' => $_FILES['profile_photo'] ?? null,
                        'aadhar_card_scan' => $_FILES['aadhar_card_scan'] ?? null,
                        'pan_card_scan' => $_FILES['pan_card_scan'] ?? null,
                        'bank_passbook_scan' => $_FILES['bank_passbook_scan'] ?? null,
                        'police_verification_document' => $_FILES['police_verification_document'] ?? null,
                        'ration_card_scan' => $_FILES['ration_card_scan'] ?? null,
                        'light_bill_scan' => $_FILES['light_bill_scan'] ?? null,
                        'voter_id_scan' => $_FILES['voter_id_scan'] ?? null,
                        'passport_scan' => $_FILES['passport_scan'] ?? null,
                    ];
                    
                    $updatePaths = [];
                    foreach ($fileUploads as $type => $file) {
                        $path = handleFileUpload($file, $userId, $type, $config['base_url']);
                        if ($path) $updatePaths[$type] = $path;
                    }

                    if (!empty($updatePaths)) {
                        $setClauses = implode(", ", array_map(fn($c) => "`$c` = ?", array_keys($updatePaths)));
                        $sql = "UPDATE users SET $setClauses WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([...array_values($updatePaths), $userId]);
                    }

                    http_response_code(201);
                    echo json_encode(['success' => true, 'id' => $userId]);
                } catch (Throwable $e) {
                    http_response_code(500);
                    error_log($e->getMessage());
                    echo json_encode(['error' => 'Server error during enrollment.']);
                }
                break;
            case 'get_users':
                header('Content-Type: application/json');
                // This is a protected action, so we verify the JWT first.
                if (!isset($_COOKIE['jwt'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized - No JWT cookie found']);
                    exit;
                }
                
                try {
                    // Simple token decode with minimal validation
                    // Just check if the token is valid, don't enforce role restrictions
                    JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    
                    // --- Filtering and Searching Logic ---
                    $baseQuery = "SELECT id, first_name, surname, email_id, user_type, created_at, profile_photo, mobile_number, web_access, mobile_access FROM users";
                    $whereClauses = [];
                    $namedParams = [];

                    // Role filtering
                    if (!empty($data['role'])) {
                        $whereClauses[] = "user_type = :role";
                        $namedParams[':role'] = $data['role'];
                    }

                    // PIN code filtering
                    if (!empty($data['pin'])) {
                        $whereClauses[] = "pin_code LIKE :pin";
                        $namedParams[':pin'] = '%' . $data['pin'] . '%';
                    }

                    // Search functionality
                    if (!empty($data['search'])) {
                        $searchTerm = '%' . $data['search'] . '%';
                        $whereClauses[] = "(id = :search_id OR first_name LIKE :search_fname OR surname LIKE :search_sname OR email_id LIKE :search_email)";
                        // Add params for the search. ID is checked for exact match, others for partial.
                        $namedParams[':search_id'] = $data['search'];
                        $namedParams[':search_fname'] = $searchTerm;
                        $namedParams[':search_sname'] = $searchTerm;
                        $namedParams[':search_email'] = $searchTerm;
                    }
                    
                    if (!empty($whereClauses)) {
                        $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
                    }
                    
                    // --- Pagination ---
                    $countQuery = str_replace("SELECT id, first_name, surname, email_id, user_type, created_at, profile_photo, mobile_number, web_access, mobile_access FROM users", "SELECT COUNT(*) FROM users", $baseQuery);
                    $countStmt = $pdo->prepare($countQuery);
                    
                    // Bind named parameters for count query
                    foreach ($namedParams as $param => $value) {
                        $countStmt->bindValue($param, $value);
                    }
                    
                    $countStmt->execute();
                    $totalUsers = $countStmt->fetchColumn();
                    
                    $perPage = 10;
                    $totalPages = ceil($totalUsers / $perPage);
                    $page = isset($data['page']) && is_numeric($data['page']) ? (int)$data['page'] : 1;
                    $offset = ($page - 1) * $perPage;

                    $baseQuery .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
                    
                    $stmt = $pdo->prepare($baseQuery);
                    
                    // Bind all named parameters consistently
                    foreach ($namedParams as $param => $value) {
                        $stmt->bindValue($param, $value);
                    }
                    
                    // Bind limit and offset as named parameters
                    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

                    $stmt->execute();
                    $users = $stmt->fetchAll();

                    // Prepend the base URL to profile photos
                    $base_url = rtrim($config['base_url'] ?? '', '/');
                    foreach ($users as &$user) {
                        if (!empty($user['profile_photo'])) {
                            // Check if the path is already a full URL
                            if (!filter_var($user['profile_photo'], FILTER_VALIDATE_URL)) {
                                $user['profile_photo'] = $base_url . '/' . ltrim($user['profile_photo'], '/');
                            }
                        }
                    }
                    unset($user); // Unset the reference to the last element

                    echo json_encode([
                        'success' => true, 
                        'users' => $users,
                        'pagination' => [
                            'total_records' => (int)$totalUsers,
                            'total_pages' => $totalPages,
                            'current_page' => $page,
                            'per_page' => $perPage
                        ]
                    ]);
                } catch (Exception $e) {
                    error_log('JWT error in get_users: ' . $e->getMessage());
                    http_response_code(401);
                    echo json_encode(['error' => 'Token validation failed: ' . $e->getMessage()]);
                }
                break;
            case 'get_user':
                 if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                 try {
                    JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if (empty($data['id'])) {
                        http_response_code(400); echo json_encode(['error' => 'User ID is required.']); exit;
                    }
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$data['id']]);
                    $user = $stmt->fetch();
                    if ($user) {
                        unset($user['password']); // Never send password hash to client
                        echo json_encode(['success' => true, 'user' => $user]);
                    } else {
                        http_response_code(404); echo json_encode(['error' => 'User not found.']);
                    }
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token']); exit;
                }
                break;

            case 'update_user':
                // For multipart form, data is in $_POST
                $data = $_POST;

                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

                    if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'User ID is missing.']); exit; }
                    
                    $userId = $data['id'];

                    // --- Handle File Uploads ---
                    $fileFields = [
                        'profile_photo',
                        'aadhar_card_scan',
                        'pan_card_scan',
                        'bank_passbook_scan',
                        // Include previously missed fields so they can be updated from the edit form
                        'police_verification_document',
                        'ration_card_scan',
                        'light_bill_scan',
                        'voter_id_scan',
                        'passport_scan'
                    ];
                    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $currentUser = $userStmt->fetch();

                    foreach ($fileFields as $field) {
                        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                            // Delete old file if it exists
                            if (!empty($currentUser[$field])) {
                                // Stored paths are relative (e.g., uploads/...); build absolute path relative to project dir
                                $oldFilePath = __DIR__ . '/' . ltrim($currentUser[$field], '/');
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }
                            // Upload new file
                            $newPath = handleFileUpload($_FILES[$field], $userId, $field, $config['base_url']);
                            if ($newPath) {
                                $data[$field] = $newPath;
                            }
                        }
                    }

                    // --- Update Text/Select Data ---
                    if (!empty($data['password'])) {
                        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                    } else {
                        unset($data['password']);
                    }

                    // Handle checkboxes which might not be in $_POST if unchecked
                    $data['web_access'] = isset($data['web_access']) ? 1 : 0;
                    $data['mobile_access'] = isset($data['mobile_access']) ? 1 : 0;

                    // Unset fields that are not part of the 'users' table columns
                    unset($data['action']);
                    unset($data['id']);

                    // Remove family reference fields from employee update
                    $familyRefFields = [];
                    foreach ($data as $key => $value) {
                        if (strpos($key, 'family_ref_') === 0) {
                            $familyRefFields[$key] = $value;
                            unset($data[$key]);
                        }
                    }
                    
                    // Normalize optional ID fields - convert empty values to NULL to prevent duplicate errors
                    $optional_id_fields = ['passport_number', 'voter_id_number', 'pf_number', 'esic_number', 'uan_number'];
                    foreach ($optional_id_fields as $field) {
                        if (isset($data[$field]) && trim($data[$field]) === '') {
                            $data[$field] = null;
                        }
                    }

                    if(empty($data)) {
                        echo json_encode(['success' => true, 'message' => 'No changes to save.']);
                        exit;
                    }

                    $setClauses = implode(", ", array_map(fn($c) => "`$c` = ?", array_keys($data)));
                    $sql = "UPDATE users SET $setClauses WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([...array_values($data), $userId]);
                    
                    // Handle family references update
                    if (!empty($familyRefFields)) {
                        try {
                            // Begin transaction for family references
                            $pdo->beginTransaction();
                            
                            // Validate and process family references
                            $familyRefs = [];
                            for ($i = 1; $i <= 2; $i++) {
                                $refData = [
                                    'name' => $familyRefFields["family_ref_{$i}_name"] ?? '',
                                    'relation' => $familyRefFields["family_ref_{$i}_relation"] ?? '',
                                    'mobile_primary' => $familyRefFields["family_ref_{$i}_mobile_primary"] ?? '',
                                    'mobile_secondary' => $familyRefFields["family_ref_{$i}_mobile_secondary"] ?? '',
                                    'address' => $familyRefFields["family_ref_{$i}_address"] ?? ''
                                ];
                                
                                // Reference 1 is required
                                if ($i == 1) {
                                    if (empty($refData['name']) || empty($refData['relation']) || 
                                        empty($refData['mobile_primary']) || empty($refData['address'])) {
                                        throw new Exception("Family Reference 1: All required fields must be filled");
                                    }
                                    if (!preg_match('/^[0-9]{10,15}$/', $refData['mobile_primary'])) {
                                        throw new Exception("Family Reference 1: Primary mobile number must be 10-15 digits");
                                    }
                                    if (!empty($refData['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $refData['mobile_secondary'])) {
                                        throw new Exception("Family Reference 1: Secondary mobile number must be 10-15 digits");
                                    }
                                }
                                
                                // Reference 2 is optional - if any field is provided, all required fields must be filled
                                if ($i == 2) {
                                    $hasAnyField = !empty($refData['name']) || !empty($refData['relation']) || 
                                                 !empty($refData['mobile_primary']) || !empty($refData['address']);
                                    
                                    if ($hasAnyField) {
                                        if (empty($refData['name']) || empty($refData['relation']) || 
                                            empty($refData['mobile_primary']) || empty($refData['address'])) {
                                            throw new Exception("Family Reference 2: If any field is provided, all required fields must be filled");
                                        }
                                        if (!preg_match('/^[0-9]{10,15}$/', $refData['mobile_primary'])) {
                                            throw new Exception("Family Reference 2: Primary mobile number must be 10-15 digits");
                                        }
                                        if (!empty($refData['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $refData['mobile_secondary'])) {
                                            throw new Exception("Family Reference 2: Secondary mobile number must be 10-15 digits");
                                        }
                                    }
                                }
                                
                                $familyRefs[] = $refData;
                            }
                            
                            // Delete existing family references
                            $deleteStmt = $pdo->prepare("DELETE FROM employee_family_references WHERE employee_id = ?");
                            $deleteStmt->execute([$userId]);
                            
                            // Insert updated family references
                            foreach ($familyRefs as $index => $refData) {
                                $refIndex = $index + 1;
                                
                                // Skip Reference 2 if all fields are empty
                                if ($refIndex == 2 && empty($refData['name']) && empty($refData['relation']) && 
                                    empty($refData['mobile_primary']) && empty($refData['address'])) {
                                    continue;
                                }
                                
                                $insertStmt = $pdo->prepare("INSERT INTO employee_family_references 
                                    (employee_id, reference_index, name, relation, mobile_primary, mobile_secondary, address, updated_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                
                                $insertStmt->execute([
                                    $userId,
                                    $refIndex,
                                    htmlspecialchars($refData['name']),
                                    htmlspecialchars($refData['relation']),
                                    htmlspecialchars($refData['mobile_primary']),
                                    !empty($refData['mobile_secondary']) ? htmlspecialchars($refData['mobile_secondary']) : null,
                                    htmlspecialchars($refData['address']),
                                    $userId
                                ]);
                            }
                            
                            $pdo->commit();
                            
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw new Exception("Family references update failed: " . $e->getMessage());
                        }
                    }
                    
                    // Set flash message for the redirect
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => 'Employee updated successfully.'
                    ];
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Employee updated successfully.',
                        'redirect_url' => "index.php?page=view-employee&id={$userId}"
                    ]);

                } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['error' => 'An error occurred during update.', 'details' => $e->getMessage()]); exit;
                }
                break;

            case 'delete_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                // Validate token first, separate from delete logic so we don't mask DB errors as auth errors
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token']);
                    exit;
                }
                
                if (($decoded->data->role ?? null) !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'User ID is required.']); exit; }

                $userId = $data['id'];
                
                try {
                    // First, check what dependencies exist for this user
                    $dependencies = [];
                    $tablesToCheck = [
                        'roster' => ['guard_id', 'Roster assignments'],
                        'attendance' => ['user_id', 'Attendance records'],
                        'salary_records' => ['user_id', 'Salary records'],
                        'tickets' => ['user_id', 'Tickets created'],
                        'team_members' => ['user_id', 'Team memberships'],
                        'activity_assignees' => ['user_id', 'Activity assignments'],
                        'ticket_assignees' => ['user_id', 'Ticket assignments'],
                        'advance_payments' => ['employee_id', 'Advance payments'],
                        'advance_salary_enhanced' => ['user_id', 'Advance salary records'],
                        'supervisor_site_assignments' => ['supervisor_id', 'Site supervisor assignments'],
                        'notifications' => ['user_id', 'Notifications'],
                        'user_preferences' => ['user_id', 'User preferences']
                    ];
                    
                    foreach ($tablesToCheck as $table => $config) {
                        try {
                            $column = $config[0];
                            $description = $config[1];
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
                            $stmt->execute([$userId]);
                            $result = $stmt->fetch();
                            
                            if ($result['count'] > 0) {
                                $dependencies[] = [
                                    'table' => $table,
                                    'column' => $column,
                                    'count' => $result['count'],
                                    'description' => $description
                                ];
                            }
                        } catch (Exception $e) {
                            // Table might not exist, skip it
                            continue;
                        }
                    }
                    
                    // If dependencies exist, return detailed error
                    if (!empty($dependencies)) {
                        $errorMessage = "Cannot delete user because of the following linked records:\n\n";
                        foreach ($dependencies as $dep) {
                            $errorMessage .= "• {$dep['description']}: {$dep['count']} record(s)\n";
                        }
                        $errorMessage .= "\nPlease remove or reassign these records before deleting the user.";
                        
                        // Get additional context about the user
                        $userStmt = $pdo->prepare("SELECT first_name, surname, user_type FROM users WHERE id = ?");
                        $userStmt->execute([$userId]);
                        $userInfo = $userStmt->fetch();
                        
                        if ($userInfo) {
                            $userName = $userInfo['first_name'] . ' ' . $userInfo['surname'];
                            $userType = $userInfo['user_type'];
                            $errorMessage = "Cannot delete {$userType} '{$userName}' because of the following linked records:\n\n" . substr($errorMessage, strpos($errorMessage, '•'));
                        }
                        
                        http_response_code(409);
                        echo json_encode([
                            'error' => $errorMessage,
                            'dependencies' => $dependencies,
                            'user_info' => $userInfo ?? null
                        ]);
                        exit;
                    }
                    
                    // No dependencies found, proceed with deletion
                    // Use transaction with foreign key checks disabled to handle constraint issues
                    $pdo->beginTransaction();
                    
                    try {
                        // Temporarily disable foreign key checks to handle any constraint edge cases
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                        
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        // Re-enable foreign key checks
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                    if ($stmt->rowCount() > 0) {
                            // Commit the transaction
                            $pdo->commit();
                            
                            // Also delete user's uploaded files from the uploads directory
                            $uploadsDir = __DIR__ . '/uploads';
                            if (is_dir($uploadsDir)) {
                                $userFiles = glob($uploadsDir . "/user_{$userId}_*");
                                foreach ($userFiles as $file) {
                                    if (is_file($file)) {
                                        unlink($file);
                                    }
                                }
                            }
                            
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
                    } else {
                            $pdo->rollBack();
                        http_response_code(404);
                        echo json_encode(['error' => 'User not found or already deleted.']);
                        }
                        
                    } catch (Exception $deleteError) {
                        // Rollback transaction and re-enable foreign key checks
                        $pdo->rollBack();
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        throw $deleteError;
                    }
                } catch (PDOException $e) {
                    // Fallback error handling for any database errors
                    http_response_code(500);
                        echo json_encode([
                        'error' => 'Database error occurred while deleting user.',
                        'details' => $e->getMessage(),
                            'code' => $e->getCode()
                        ]);
                } catch (Throwable $t) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Unexpected server error', 'details' => $t->getMessage()]);
                }
                break;
                
            case 'refresh_token':
                // Refresh the JWT token if it's about to expire
                if (!isset($_COOKIE['jwt'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'No token to refresh']);
                    exit;
                }
                
                try {
                    // Decode the existing token
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    
                    // Always refresh token to ensure it's valid
                    $refreshNeeded = true;
                    
                    if ($refreshNeeded) {
                        // Get user data for the token
                        $userId = $decoded->data->id;
                        $userRole = $decoded->data->role;
                        
                        // Create new token with a fresh expiry time
                        $payload = [
                            'iss' => "http://your-domain.com",
                            'aud' => "http://your-domain.com",
                            'iat' => time(),
                            'nbf' => time(),
                            'exp' => time() + (60*60*24), // New expiry 24 hours from now
                            'data' => [
                                'id' => $userId,
                                'email' => $decoded->data->email,
                                'role' => $userRole,
                                'profile_photo' => $decoded->data->profile_photo,
                                'full_name' => $decoded->data->full_name,
                                'society' => $decoded->data->society ?? null
                            ]
                        ];
                        
                        $newToken = JWT::encode($payload, $config['jwt']['secret'], 'HS256');
                        
                        // Set the new cookie
                        setcookie('jwt', $newToken, [
                            'expires' => time() + (60 * 60 * 24),
                            'path' => '/',
                            'domain' => '',
                            'secure' => isset($_SERVER['HTTPS']),
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                        
                        echo json_encode(['success' => true, 'message' => 'Token refreshed']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Token is still valid']);
                    }
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token, cannot refresh: ' . $e->getMessage()]);
                }
                break;
                
            case 'generate_pdf':
                // Performance optimizations for PDF generation
                ini_set('memory_limit', '256M');
                ini_set('max_execution_time', 30);
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo 'Unauthorized'; exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo 'Forbidden'; exit; }

                    if (empty($_GET['id']) || empty($_GET['type'])) {
                        http_response_code(400); echo 'User ID and Type are required.'; exit;
                    }
                    
                    // Fetch user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $employee = $stmt->fetch();

                    if (!$employee) { http_response_code(404); echo 'User not found.'; exit; }

                    // --- PDF Generation ---
                    $templatePath = __DIR__ . '/templates/pdf/' . $_GET['type'] . '_template.php';
                    if (!file_exists($templatePath)) {
                        http_response_code(404); echo 'Template not found.'; exit;
                    }
                    
                    // Process profile photo - use SAME method as profile page for consistency
                    if($employee['profile_photo']) {
                       $photoPath = $employee['profile_photo']; // e.g., "uploads/user_1_profile_photo_68871d5d50f59.png"
                       
                       // Use the EXACT same URL construction as the profile page
                       $basePath = rtrim($config['base_url'], '/'); // Same as profile page
                       $photoUrl = $basePath . '/' . ltrim($photoPath, '/'); // Same construction as profile page
                       
                       error_log("PDF Generation: Using profile page method - URL: " . $photoUrl);
                       
                       // Try to get the image using the same URL that works on the profile page
                       $context = stream_context_create([
                           'http' => [
                               'timeout' => 15,
                               'user_agent' => 'Mozilla/5.0 (compatible; PDF Generator)',
                               'follow_location' => true,
                               'max_redirects' => 3,
                               'ignore_errors' => false
                           ]
                       ]);
                       
                       $imageData = @file_get_contents($photoUrl, false, $context);
                       if ($imageData !== false && strlen($imageData) > 0) {
                           $fileSize = strlen($imageData);
                           if ($fileSize < 5 * 1024 * 1024) { // 5MB limit
                               // Create a temporary file to get MIME type
                               $tempPath = sys_get_temp_dir() . '/profile_photo_legacy_' . uniqid() . '.tmp';
                               file_put_contents($tempPath, $imageData);
                               $imageMime = mime_content_type($tempPath);
                               
                               if ($imageMime && strpos($imageMime, 'image/') === 0) {
                                   $employee['profile_photo_src'] = 'data:' . $imageMime . ';base64,' . base64_encode($imageData);
                                   error_log("PDF Generation: SUCCESS! Profile photo loaded ({$fileSize} bytes, {$imageMime}) using profile page method");
                       } else {
                                   error_log("PDF Generation: Invalid image type: " . ($imageMime ?: 'unknown'));
                                   unset($employee['profile_photo']);
                               }
                               
                               // Clean up temp file
                               @unlink($tempPath);
                           } else {
                               error_log("PDF Generation: Profile photo too large ({$fileSize} bytes)");
                               unset($employee['profile_photo']);
                           }
                       } else {
                           error_log("PDF Generation: Failed to load using profile page method: " . $photoUrl);
                           
                           // Fallback: Try local file access
                           $localPath = __DIR__ . '/' . ltrim($photoPath, '/');
                           if (file_exists($localPath)) {
                               $imageData = file_get_contents($localPath);
                               $imageMime = mime_content_type($localPath);
                               if ($imageMime && strpos($imageMime, 'image/') === 0) {
                                   $employee['profile_photo_src'] = 'data:' . $imageMime . ';base64,' . base64_encode($imageData);
                                   error_log("PDF Generation: Profile photo loaded from local fallback: " . $localPath);
                               } else {
                                   unset($employee['profile_photo']);
                               }
                           } else {
                               error_log("PDF Generation: Local fallback also failed: " . $localPath);
                               unset($employee['profile_photo']); // Remove to show placeholder
                           }
                       }
                    }

                    ob_start();
                    require $templatePath;
                    $html = ob_get_clean();

                    $options = new Options();
                    $options->set('isRemoteEnabled', true); // Allows loading images from URLs
                    $options->set('defaultFont', 'Helvetica');
                    
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    // Save PDF to a temporary location
                    $filename = $_GET['type'] . '_' . $employee['first_name'] . '_' . $employee['id'] . '.pdf';
                    $tempPdfPath = __DIR__ . '/uploads/temp_pdfs/' . $filename;
                    
                    // Ensure temp directory exists
                    if (!is_dir(dirname($tempPdfPath))) {
                        mkdir(dirname($tempPdfPath), 0755, true);
                    }
                    
                    // Save PDF
                    file_put_contents($tempPdfPath, $dompdf->output());
                    
                    // Relative path for download link
                    $relativePdfPath = 'uploads/temp_pdfs/' . $filename;
                    
                    // If this is an AJAX request (likely for preview), return PDF path
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode([
                            'success' => true, 
                            'pdfPath' => $relativePdfPath,
                            'filename' => $filename
                        ]);
                        exit;
                    }
                    
                    // Otherwise, force download
                    $dompdf->stream($filename, ["Attachment" => true]); // true to force download

                } catch (Exception $e) {
                    http_response_code(500); 
                    error_log('PDF Generation Error: ' . $e->getMessage());
                    echo 'An error occurred during PDF generation.'; 
                    exit;
                }
                break;
                
            case 'generate_pdf_fast':
                // Performance optimizations for PDF generation
                ini_set('memory_limit', '256M');
                ini_set('max_execution_time', 30);
                if (!isset($_COOKIE['jwt'])) { 
                    http_response_code(401); 
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']); 
                    exit; 
                }
                
                try {
                    $totalStartTime = microtime(true); // Track total generation time
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { 
                        http_response_code(403); 
                        echo json_encode(['success' => false, 'error' => 'Forbidden']); 
                        exit; 
                    }

                    if (empty($_GET['id']) || empty($_GET['type'])) {
                        http_response_code(400); 
                        echo json_encode(['success' => false, 'error' => 'User ID and Type are required']); 
                        exit;
                    }
                    
                    $employeeId = (int)$_GET['id'];
                    $pdfType = $_GET['type'];
                    
                    // Force regenerate PDF for now (temporary fix for testing)
                    $filename = $pdfType . '_' . $employeeId . '_' . time() . '.pdf';
                    $tempPdfPath = __DIR__ . '/uploads/temp_pdfs/' . $filename;
                    $relativePdfPath = 'uploads/temp_pdfs/' . $filename;
                    
                    // Temporarily disable PDF caching for testing
                    // if (file_exists($tempPdfPath) && (time() - filemtime($tempPdfPath)) < 3600) {
                    //     error_log("PDF Fast: Using cached PDF for employee {$employeeId}");
                    //     echo json_encode([
                    //         'success' => true, 
                    //         'pdfPath' => $relativePdfPath,
                    //         'filename' => $filename,
                    //         'cached' => true
                    //     ]);
                    //     exit;
                    // }
                    
                    // Temporarily disable employee caching for testing
                    $employee = null;
                    
                    // Always fetch fresh employee data
                    $stmt = $pdo->prepare("SELECT id, first_name, surname, user_type, mobile_number, date_of_birth, date_of_joining, profile_photo, email_id, address, gender, permanent_address, highest_qualification, salary, aadhar_number, pan_number, bank_name, bank_account_number, ifsc_code FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([$employeeId]);
                    $employee = $stmt->fetch();

                    // Fetch company settings for the template
                    $company_settings = [];
                    try {
                        $stmt = $pdo->query('SELECT * FROM company_settings LIMIT 1');
                        $company_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $e) {
                        error_log('PDF Fast: Failed to fetch company settings: ' . $e->getMessage());
                    }

                    // Process company logo if available
                    $company_logo_src = null;
                    if (!empty($company_settings['logo_path'])) {
                        $logoPath = $company_settings['logo_path'];
                        $localLogoPath = __DIR__ . '/' . ltrim($logoPath, '/');
                        
                        if (file_exists($localLogoPath) && is_readable($localLogoPath)) {
                            $logoMime = mime_content_type($localLogoPath);
                            if (strpos($logoMime, 'image/') === 0) {
                                $logoData = file_get_contents($localLogoPath);
                                $company_logo_src = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
                            }
                        }
                    }

                    if (!$employee) { 
                        http_response_code(404); 
                        echo json_encode(['success' => false, 'error' => 'Employee not found']); 
                        exit; 
                    }

                    // Validate template exists
                    $templatePath = __DIR__ . '/templates/pdf/' . $pdfType . '_template.php';
                    if (!file_exists($templatePath)) {
                        http_response_code(404); 
                        echo json_encode(['success' => false, 'error' => 'Template not found']); 
                        exit;
                    }
                    
                    // Process profile photo with aggressive caching for performance
                    $profilePhotoProcessed = false;
                    if ($employee['profile_photo']) {
                        $photoPath = $employee['profile_photo'];
                        $photoStartTime = microtime(true);
                        
                        // Check photo cache first (cache photos for 6 hours)
                        $photoCacheKey = 'photo_' . md5($photoPath);
                        $photoCacheFile = __DIR__ . '/cache/photos/' . $photoCacheKey . '.cache';
                        
                        if (file_exists($photoCacheFile) && (time() - filemtime($photoCacheFile)) < 21600) {
                            // Use cached photo data
                            $cachedData = file_get_contents($photoCacheFile);
                            if ($cachedData) {
                                $employee['profile_photo_src'] = $cachedData;
                                $profilePhotoProcessed = true;
                                $photoTime = round((microtime(true) - $photoStartTime) * 1000, 1);
                                error_log("PDF Fast: Used cached profile photo ({$photoTime}ms)");
                            }
                        }
                        
                        if (!$profilePhotoProcessed) {
                            // Try local file first (fastest)
                            $localPath = __DIR__ . '/' . ltrim($photoPath, '/');
                            if (file_exists($localPath) && is_readable($localPath)) {
                                $fileSize = filesize($localPath);
                                if ($fileSize > 0 && $fileSize < 5 * 1024 * 1024) {
                                    $imageData = file_get_contents($localPath);
                                    $imageMime = mime_content_type($localPath);
                                    
                                    if ($imageMime && strpos($imageMime, 'image/') === 0) {
                                        $photoDataUri = 'data:' . $imageMime . ';base64,' . base64_encode($imageData);
                                        $employee['profile_photo_src'] = $photoDataUri;
                                        $profilePhotoProcessed = true;
                                        
                                        // Cache the processed photo
                                        if (!is_dir(dirname($photoCacheFile))) {
                                            mkdir(dirname($photoCacheFile), 0755, true);
                                        }
                                        file_put_contents($photoCacheFile, $photoDataUri);
                                        
                                        $photoTime = round((microtime(true) - $photoStartTime) * 1000, 1);
                                        error_log("PDF Fast: Profile photo loaded locally and cached ({$photoTime}ms, {$fileSize} bytes)");
                                    }
                                }
                            }
                            
                            // Fallback to URL method if local failed
                            if (!$profilePhotoProcessed) {
                                $basePath = rtrim($config['base_url'], '/');
                                $photoUrl = $basePath . '/' . ltrim($photoPath, '/');
                                
                                $context = stream_context_create([
                                    'http' => [
                                        'timeout' => 8, // Reduced timeout for speed
                                        'user_agent' => 'Mozilla/5.0 (compatible; PDF Generator)',
                                        'follow_location' => false, // Disable redirects for speed
                                        'max_redirects' => 0
                                    ]
                                ]);
                                
                                $imageData = @file_get_contents($photoUrl, false, $context);
                                if ($imageData !== false && strlen($imageData) > 0) {
                                    $fileSize = strlen($imageData);
                                    if ($fileSize < 5 * 1024 * 1024) {
                                        // Quick MIME detection without temp file
                                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                                        $imageMime = $finfo->buffer($imageData);
                                        
                                        if ($imageMime && strpos($imageMime, 'image/') === 0) {
                                            $photoDataUri = 'data:' . $imageMime . ';base64,' . base64_encode($imageData);
                                            $employee['profile_photo_src'] = $photoDataUri;
                                            $profilePhotoProcessed = true;
                                            
                                            // Cache the photo
                                            if (!is_dir(dirname($photoCacheFile))) {
                                                mkdir(dirname($photoCacheFile), 0755, true);
                                            }
                                            file_put_contents($photoCacheFile, $photoDataUri);
                                            
                                            $photoTime = round((microtime(true) - $photoStartTime) * 1000, 1);
                                            error_log("PDF Fast: Profile photo downloaded and cached ({$photoTime}ms, {$fileSize} bytes)");
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$profilePhotoProcessed) {
                        unset($employee['profile_photo']);
                    }

                    // Generate HTML with output buffering
                    ob_start();
                    
                    // Debug: Log what's being passed to template
                    error_log("PDF Debug - Employee data: " . json_encode(array_keys($employee)));
                    error_log("PDF Debug - Company settings: " . json_encode(array_keys($company_settings)));
                    error_log("PDF Debug - Company logo: " . ($company_logo_src ? 'Set' : 'Not set'));
                    
                    require $templatePath;
                    $html = ob_get_clean();

                    // Ultra-optimized PDF generation settings for maximum speed
                    $options = new Options();
                    $options->set('isRemoteEnabled', false); // Disable remote loading
                    $options->set('isPhpEnabled', false); // Disable PHP evaluation
                    $options->set('isJavascriptEnabled', false); // Disable JavaScript
                    $options->set('isHtml5ParserEnabled', false); // Use faster HTML4 parser
                    $options->set('defaultFont', 'Helvetica'); // Fast system font
                    $options->set('defaultMediaType', 'print'); // Optimize for print
                    $options->set('isFontSubsettingEnabled', false); // Disable font subsetting for speed
                    $options->set('debugKeepTemp', false);
                    $options->set('debugPng', false);
                    $options->set('debugCss', false);
                    $options->set('debugLayout', false);
                    $options->set('debugLayoutLines', false);
                    $options->set('debugLayoutBlocks', false);
                    $options->set('debugLayoutInline', false);
                    $options->set('debugLayoutPaddingBox', false);
                    
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    
                    // Measure generation time with detailed breakdown
                    $renderStartTime = microtime(true);
                    $dompdf->render();
                    $renderTime = round((microtime(true) - $renderStartTime) * 1000, 2);
                    
                    // Ensure temp directory exists
                    if (!is_dir(dirname($tempPdfPath))) {
                        mkdir(dirname($tempPdfPath), 0755, true);
                    }
                    
                    // Save PDF with timing
                    $saveStartTime = microtime(true);
                    file_put_contents($tempPdfPath, $dompdf->output());
                    $saveTime = round((microtime(true) - $saveStartTime) * 1000, 2);
                    
                    $totalTime = round((microtime(true) - $totalStartTime) * 1000, 2);
                    
                    error_log("PDF Fast Performance - Employee {$employeeId}: Total={$totalTime}ms, Render={$renderTime}ms, Save={$saveTime}ms");
                    
                    // Return success response with performance metrics
                    echo json_encode([
                        'success' => true, 
                        'pdfPath' => $relativePdfPath,
                        'filename' => $filename,
                        'cached' => false,
                        'performance' => [
                            'total_time_ms' => $totalTime,
                            'render_time_ms' => $renderTime,
                            'save_time_ms' => $saveTime
                        ]
                    ]);

                } catch (Exception $e) {
                    http_response_code(500); 
                    error_log('PDF Fast Generation Error: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_societies':
                header('Content-Type: application/json');
                
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
                $offset = ($page - 1) * $perPage;
                
                $search = $_GET['search'] ?? '';
                $status = $_GET['status'] ?? '';
                $clientTypeId = $_GET['client_type_id'] ?? '';
                $compliance = $_GET['compliance'] ?? '';

                $baseQuery = "FROM society_onboarding_data s";
                $whereClauses = [];
                    $params = [];
                    
                if (!empty($search)) {
                    $whereClauses[] = "(s.society_name LIKE ? OR s.street_address LIKE ? OR s.city LIKE ? OR s.id = ?)";
                    $searchTerm = "%{$search}%";
                    array_push($params, $searchTerm, $searchTerm, $searchTerm, $search);
                }

                if (!empty($clientTypeId)) {
                    $whereClauses[] = "s.client_type_id = ?";
                    $params[] = $clientTypeId;
                    }
                    
                if ($compliance !== '') {
                    $whereClauses[] = "s.compliance_status = ?";
                    $params[] = $compliance;
                }

                if (!empty($status)) {
                    $today = date('Y-m-d');
                    $ninetyDaysLater = date('Y-m-d', strtotime('+90 days'));
                    if ($status === 'expired') {
                        $whereClauses[] = "s.contract_expiry_date < ?";
                        $params[] = $today;
                    } elseif ($status === 'expiring') {
                        $whereClauses[] = "s.contract_expiry_date BETWEEN ? AND ?";
                        array_push($params, $today, $ninetyDaysLater);
                    } elseif ($status === 'ongoing') {
                        $whereClauses[] = "s.contract_expiry_date > ?";
                        $params[] = $ninetyDaysLater;
                    }
                }

                $whereSql = '';
                if (!empty($whereClauses)) {
                    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
                }

                // Get total count for pagination
                $totalResult = $pdo->prepare("SELECT COUNT(*) as total $baseQuery $whereSql");
                $totalResult->execute($params);
                $totalItems = $totalResult->fetch()['total'];

                // Get societies for the current page
                $societiesQuery = "SELECT s.* $baseQuery $whereSql ORDER BY s.id DESC LIMIT ? OFFSET ?";
                        
                $societiesStmt = $pdo->prepare($societiesQuery);
                
                // Bind WHERE clause parameters, then LIMIT and OFFSET
                        $i = 1;
                        foreach ($params as $value) {
                    $societiesStmt->bindValue($i++, $value);
                        }
                $societiesStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
                $societiesStmt->bindValue($i++, $offset, PDO::PARAM_INT);

                $societiesStmt->execute();
                $societies = $societiesStmt->fetchAll();
                        
                        echo json_encode([
                            'success' => true, 
                            'societies' => $societies,
                            'pagination' => [
                        'total_items' => $totalItems,
                        'total_pages' => ceil($totalItems / $perPage),
                                'current_page' => $page,
                                'per_page' => $perPage
                            ]
                        ]);
                exit;
            case 'onboard_society':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    // For now, only Admins can onboard. This can be expanded later.
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') {
                        http_response_code(403); echo json_encode(['error' => 'Forbidden: You do not have permission to perform this action.']); exit;
                    }

                    $pdo->beginTransaction();

                    try {
                        // --- Insert Society Data ---
                        $societySchema = require __DIR__ . '/schema/society_onboarding.php';
                        $allowedSocietyCols = array_keys($societySchema['society_onboarding_data']['columns']);
                        $societyData = [];

                        foreach ($allowedSocietyCols as $col) {
                            if (isset($data[$col])) {
                                // Ensure numeric values are correctly typed, prevent empty strings
                                if (is_numeric($data[$col]) || !empty($data[$col])) {
                                    $societyData[$col] = $data[$col];
                                }
                            }
                        }
                        
                        // Default values for service counts/rates if not provided
                        $services = [
                            'guards', 'guard_client_rate', 'guard_employee_rate',
                            'dogs', 'dog_client_rate', 'dog_employee_rate',
                            'armed_guards', 'armed_client_rate', 'armed_guard_employee_rate',
                            'housekeeping', 'housekeeping_client_rate', 'housekeeping_employee_rate',
                            'bouncers', 'bouncer_client_rate', 'bouncer_employee_rate',
                            'site_supervisors', 'site_supervisor_client_rate', 'site_supervisor_employee_rate',
                            'supervisors', 'supervisor_client_rate', 'supervisor_employee_rate'
                        ];
                        foreach($services as $service) {
                            if (!isset($societyData[$service])) {
                                $societyData[$service] = 0;
                            }
                        }
                        
                        if (empty($societyData['society_name']) || empty($societyData['street_address']) || empty($societyData['city']) || empty($societyData['district']) || empty($societyData['state']) || empty($societyData['pin_code'])) {
                            throw new Exception("Client name and complete address are required.");
                        }
                        
                        $cols = implode(", ", array_map(fn($c) => "`$c`", array_keys($societyData)));
                        $placeholders = implode(", ", array_fill(0, count($societyData), '?'));
                        $sql = "INSERT INTO society_onboarding_data ($cols) VALUES ($placeholders)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($societyData));
                        $societyId = $pdo->lastInsertId();

                        // --- Create Primary Client User ---
                        if (empty($data['client_name']) || empty($data['client_username']) || empty($data['client_password'])) {
                            throw new Exception("Client user details (name, username, password) are required.");
                        }

                        $salt = bin2hex(random_bytes(16));
                        $password_hash = hash('sha256', $data['client_password'] . $salt);

                        $clientUserSql = "INSERT INTO clients_users (society_id, name, phone, email, username, password_hash, password_salt) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmtClient = $pdo->prepare($clientUserSql);
                        $stmtClient->execute([
                            $societyId,
                            $data['client_name'],
                            $data['client_phone'],
                            $data['client_email'],
                            $data['client_username'],
                            $password_hash,
                            $salt
                        ]);
                        
                        $pdo->commit();
                        http_response_code(201);
                        echo json_encode(['success' => true, 'society_id' => $societyId]);

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        http_response_code(500);
                        error_log("Onboarding Error: " . $e->getMessage());
                        echo json_encode(['error' => 'Server error during onboarding: ' . $e->getMessage()]);
                    }

                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid Token']);
                }
                exit;
            case 'update_society':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

                    if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'Society ID is missing.']); exit; }
                    
                    $societyId = $data['id'];
                    
                    $societySchema = require __DIR__ . '/schema/society_onboarding.php';
                    $allowedSocietyCols = array_keys($societySchema['society_onboarding_data']['columns']);
                    
                    $updateData = [];
                    foreach ($allowedSocietyCols as $col) {
                        if (isset($data[$col])) {
                            // Allow clearing certain fields
                            if (in_array($col, ['contract_expiry_date']) && empty($data[$col])) {
                               $updateData[$col] = null;
                               continue;
                            }
                            // For other fields, only update if a value is provided
                            if (!empty($data[$col]) || $data[$col] === '0') {
                               $updateData[$col] = $data[$col];
                            }
                        }
                    }

                    unset($updateData['id']); // cannot update the primary key

                    if(empty($updateData)) {
                        echo json_encode(['success' => true, 'message' => 'No changes to save.']);
                        exit;
                    }

                    $setClauses = implode(", ", array_map(fn($c) => "`$c` = ?", array_keys($updateData)));
                    $sql = "UPDATE society_onboarding_data SET $setClauses WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([...array_values($updateData), $societyId]);
                    
                    echo json_encode(['success' => true, 'message' => 'Society updated successfully.']);

                } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['error' => 'An error occurred during update.', 'details' => $e->getMessage()]); exit;
                }
                break;
            case 'delete_society':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                 try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                     if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                    
                    if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'Society ID is required.']); exit; }

                    $pdo->beginTransaction();
                    try {
                        // First, delete associated client users
                        $stmt_clients = $pdo->prepare("DELETE FROM clients_users WHERE society_id = ?");
                        $stmt_clients->execute([$data['id']]);

                        // Then, delete the society
                        $stmt_society = $pdo->prepare("DELETE FROM society_onboarding_data WHERE id = ?");
                        $stmt_society->execute([$data['id']]);

                        if ($stmt_society->rowCount() > 0) {
                            $pdo->commit();
                            echo json_encode(['success' => true, 'message' => 'Society and all associated data deleted successfully.']);
                        } else {
                            $pdo->rollBack();
                            http_response_code(404); echo json_encode(['error' => 'Society not found or already deleted.']);
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        http_response_code(500); echo json_encode(['error' => 'Error during deletion: ' . $e->getMessage()]);
                    }
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token']); exit;
                }
                break;
            case 'get_client_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'User ID is required.']); exit; }
                    $stmt = $pdo->prepare("SELECT id, name, username, email, phone FROM clients_users WHERE id = ?");
                    $stmt->execute([$data['id']]);
                    $user = $stmt->fetch();
                    if ($user) {
                        echo json_encode(['success' => true, 'user' => $user]);
                    } else {
                        http_response_code(404); echo json_encode(['error' => 'Client user not found.']);
                    }
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token']);
                }
                break;
            case 'add_client_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                    
                    $required = ['society_id', 'name', 'phone', 'email', 'username', 'password'];
                    foreach($required as $field) {
                        if (empty($data[$field])) { http_response_code(400); echo json_encode(['error' => "Missing required field: $field"]); exit; }
                    }

                    // Check for unique username and email within the client users table
                    $stmt = $pdo->prepare("SELECT id FROM clients_users WHERE username = ? OR email = ?");
                    $stmt->execute([$data['username'], $data['email']]);
                    if($stmt->fetch()) { http_response_code(409); echo json_encode(['error' => 'Username or Email already exists.']); exit; }

                    $salt = bin2hex(random_bytes(16));
                    $password_hash = hash('sha256', $data['password'] . $salt);

                    $sql = "INSERT INTO clients_users (society_id, name, phone, email, username, password_hash, password_salt, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$data['society_id'], $data['name'], $data['phone'], $data['email'], $data['username'], $password_hash, $salt]);
                    
                    $newUserId = $pdo->lastInsertId();
                    echo json_encode(['success' => true, 'id' => $newUserId, 'message' => 'Client user added successfully.']);
                } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
            case 'update_client_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                    
                    if (empty($data['user_id'])) { http_response_code(400); echo json_encode(['error' => 'User ID is required.']); exit; }

                    $sql = "UPDATE clients_users SET name = ?, phone = ?, email = ?, username = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$data['name'], $data['phone'], $data['email'], $data['username'], $data['user_id']]);
                    
                    echo json_encode(['success' => true, 'message' => 'Client user updated successfully.']);
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) { // Duplicate entry
                        http_response_code(409);
                        echo json_encode(['error' => 'This email or username is already in use.']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                    }
                } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
            
            case 'reset_client_user_password':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

                    if (empty($data['user_id']) || empty($data['new_password'])) { http_response_code(400); echo json_encode(['error' => 'User ID and new password are required.']); exit; }
                    
                    $salt = bin2hex(random_bytes(16));
                    $password_hash = hash('sha256', $data['new_password'] . $salt);

                    $sql = "UPDATE clients_users SET password_hash = ?, password_salt = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$password_hash, $salt, $data['user_id']]);

                    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
                } catch (Exception $e) {
                     http_response_code(500); echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
                }
                break;

            case 'set_primary_client_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

                    if (empty($data['user_id']) || empty($data['society_id'])) { 
                        http_response_code(400); 
                        echo json_encode(['error' => 'User ID and Society ID are required.']); 
                        exit; 
                    }
                    
                    $userId = $data['user_id'];
                    $societyId = $data['society_id'];

                    $pdo->beginTransaction();

                    // Step 1: Unset the current primary user for the society
                    $stmt_unset = $pdo->prepare("UPDATE clients_users SET is_primary = 0 WHERE society_id = ? AND is_primary = 1");
                    $stmt_unset->execute([$societyId]);

                    // Step 2: Set the new primary user
                    $stmt_set = $pdo->prepare("UPDATE clients_users SET is_primary = 1 WHERE id = ? AND society_id = ?");
                    $stmt_set->execute([$userId, $societyId]);

                    $pdo->commit();

                    echo json_encode(['success' => true, 'message' => 'Primary contact updated successfully.']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500); echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
                }
                break;

            case 'test_qr_code':
                // Test endpoint without JWT authentication
                try {
                    error_log("test_qr_code - Testing QR code generation without authentication");
                    
                    // Check for required fields
                    if (empty($data['name']) || empty($data['phone']) || empty($data['email'])) {
                        error_log("test_qr_code - Missing required fields: name=" . ($data['name'] ?? 'empty') . ", phone=" . ($data['phone'] ?? 'empty') . ", email=" . ($data['email'] ?? 'empty'));
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Name, phone, and email are required.']);
                        exit;
                    }
                    
                    // Generate the QR code using the helper function
                    $qrCodeUri = generate_vcard_qr_code(
                        $data['name'],
                        $data['phone'],
                        $data['email']
                    );
                    
                    if (!$qrCodeUri) {
                        error_log("test_qr_code - QR code generation failed");
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Failed to generate QR code.']);
                        exit;
                    } else {
                        error_log("test_qr_code - QR code generated successfully, length: " . strlen($qrCodeUri));
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'qr_code_uri' => $qrCodeUri,
                        'message' => 'QR code generated successfully.'
                    ]);
                    
                } catch (Exception $e) {
                    $errorMsg = "test_qr_code - Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
                    error_log($errorMsg);
                    file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;

            case 'generate_user_qr_code':
                if (!isset($_COOKIE['jwt'])) { 
                    error_log("generate_user_qr_code - No JWT cookie found");
                    http_response_code(401); 
                    echo json_encode(['error' => 'Unauthorized']); 
                    exit; 
                }
                try {
                    error_log("generate_user_qr_code - JWT cookie found, attempting decode");
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    error_log("generate_user_qr_code - JWT decoded successfully");
                    
                    // Debug: Log the received data
                    error_log("generate_user_qr_code - Received data: " . json_encode($data));
                    
                    // Check for required fields
                    if (empty($data['name']) || empty($data['phone']) || empty($data['email'])) {
                        error_log("generate_user_qr_code - Missing required fields: name=" . ($data['name'] ?? 'empty') . ", phone=" . ($data['phone'] ?? 'empty') . ", email=" . ($data['email'] ?? 'empty'));
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Name, phone, and email are required.']);
                        exit;
                    }
                    
                    // Debug: Log the data being passed to QR generator
                    error_log("generate_user_qr_code - Calling generate_vcard_qr_code with: name=" . $data['name'] . ", phone=" . $data['phone'] . ", email=" . $data['email']);
                    
                    // Generate the QR code using the helper function
                    $qrCodeUri = generate_vcard_qr_code(
                        $data['name'],
                        $data['phone'],
                        $data['email']
                    );
                    
                    // Debug: Log the result
                    if (!$qrCodeUri) {
                        error_log("generate_user_qr_code - QR code generation failed");
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Failed to generate QR code.']);
                        exit;
                    } else {
                        error_log("generate_user_qr_code - QR code generated successfully, length: " . strlen($qrCodeUri));
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'qr_code_uri' => $qrCodeUri,
                        'message' => 'QR code generated successfully.'
                    ]);
                    
                } catch (Exception $e) {
                    $errorMsg = "generate_user_qr_code - Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
                    error_log($errorMsg);
                    file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
                
            case 'delete_client_user':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                    
                    // Check for required fields
                    if (empty($data['user_id']) || empty($data['society_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'User ID and Society ID are required.']);
                        exit;
                    }
                    
                    // Check if user exists and is not a primary contact
                    $stmt = $pdo->prepare("SELECT is_primary FROM clients_users WHERE id = ? AND society_id = ?");
                    $stmt->execute([$data['user_id'], $data['society_id']]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'User not found.']);
                        exit;
                    }
                    
                    if ($user['is_primary'] == 1) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Cannot delete primary contact. Please assign another user as primary first.']);
                        exit;
                    }
                    
                    // Delete the user
                    $stmt = $pdo->prepare("DELETE FROM clients_users WHERE id = ? AND society_id = ?");
                    $stmt->execute([$data['user_id'], $data['society_id']]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Client user deleted successfully.'
                    ]);
                    
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;

            // --- TICKETING SYSTEM ACTIONS ---

            case 'get_tickets':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    $user_role = $decoded->data->role;
                    $user_society_info = $decoded->data->society ?? null;

                    // Use $_GET instead of $data to ensure compatibility with URL parameters
                    $baseQuery = "SELECT t.id, t.title, s.society_name, t.status, t.priority, t.created_at 
                                  FROM tickets t 
                                  JOIN society_onboarding_data s ON t.society_id = s.id";
                    $whereClauses = [];
                    $params = [];
                    $paramIndex = 1; // Track parameter index for positional parameters

                    // Role-based filtering
                    if ($user_role === 'Client') {
                        if (empty($user_society_info)) {
                             // This should not happen if JWT is created correctly
                            http_response_code(403); echo json_encode(['error' => 'Client user not associated with a society.']); exit;
                        }
                        $whereClauses[] = "t.society_id = ?";
                        $params[] = $user_society_info->id;
                    } elseif ($user_role === 'Admin' && !empty($_GET['society_id'])) {
                        $whereClauses[] = "t.society_id = ?";
                        $params[] = $_GET['society_id'];
                    } elseif ($user_role !== 'Admin') {
                        // Other employee roles (Guard, etc.) currently cannot see tickets.
                        echo json_encode(['success' => true, 'tickets' => []]);
                        exit;
                    }
                    
                    if (!empty($_GET['search'])) {
                        $searchTerm = '%' . $_GET['search'] . '%';
                        $whereClauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                    }
                    if (!empty($_GET['status'])) {
                        $whereClauses[] = "t.status = ?";
                        $params[] = $_GET['status'];
                    }
                    if (!empty($_GET['priority'])) {
                        $whereClauses[] = "t.priority = ?";
                        $params[] = $_GET['priority'];
                    }

                    if (!empty($whereClauses)) {
                        $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
                    }
                    
                    // --- Pagination ---
                    // 1. Get total count for pagination
                    $countQuery = "SELECT COUNT(*) FROM tickets t ";
                    
                    // Add join for society if needed
                    if (!empty($whereClauses)) {
                        $countQuery .= "JOIN society_onboarding_data s ON t.society_id = s.id ";
                        $countQuery .= "WHERE " . implode(" AND ", $whereClauses);
                    }
                    
                    $countStmt = $pdo->prepare($countQuery);
                    
                    // Bind parameters for count query
                    foreach ($params as $i => $value) {
                        $countStmt->bindValue($i + 1, $value);
                    }
                    
                    $countStmt->execute();
                    $totalTickets = $countStmt->fetchColumn();

                    // 2. Setup pagination variables
                    $perPage = 10;
                    $totalPages = ceil($totalTickets / $perPage);
                    
                    // Use pageNum parameter for pagination instead of page to avoid conflict
                    $page = isset($_GET['pageNum']) && is_numeric($_GET['pageNum']) ? (int)$_GET['pageNum'] : 1;
                    $offset = ($page - 1) * $perPage;

                    // Use positional parameters for all bindings
                    $baseQuery .= " ORDER BY t.updated_at DESC LIMIT ? OFFSET ?";
                    $params[] = $perPage;
                    $params[] = $offset;

                    $stmt = $pdo->prepare($baseQuery);
                    
                    // Bind all parameters positionally
                    foreach ($params as $i => $value) {
                        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                        $stmt->bindValue($i + 1, $value, $paramType);
                    }
                    
                    $stmt->execute();
                    $tickets = $stmt->fetchAll();

                    echo json_encode([
                        'success' => true, 
                        'tickets' => $tickets,
                        'pagination' => [
                            'total_tickets' => (int)$totalTickets,
                            'total_pages' => $totalPages,
                            'current_page' => $page,
                            'per_page' => $perPage
                        ]
                    ]);
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token: ' . $e->getMessage()]);
                }
                break;

            case 'create_ticket':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    $user_id = $decoded->data->id;
                    $user_role = $decoded->data->role;
                    $user_society_info = $decoded->data->society ?? null;

                    // Validation
                    if (empty($data['title']) || empty($data['description']) || empty($data['priority'])) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Title, description, and priority are required.']); exit;
                    }

                    $society_id = null;
                    if ($user_role === 'Admin') {
                        if (empty($data['society_id'])) {
                            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Admin must select a society.']); exit;
                        }
                        $society_id = $data['society_id'];
                    } elseif ($user_role === 'Client') {
                         if (empty($user_society_info)) {
                            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Client user not associated with a society.']); exit;
                        }
                        $society_id = $user_society_info->id;
                    } else {
                        // Other roles cannot create tickets yet
                        http_response_code(403); echo json_encode(['success' => false, 'message' => 'You do not have permission to create tickets.']); exit;
                    }

                    $pdo->beginTransaction();

                    // Note: The user_id stored is the ID from either the `users` or `clients_users` table.
                    // This could be an issue if IDs overlap. A better solution would be a separate user concept or prefixed IDs.
                    // For now, we proceed with this simplification.
                    $sql = "INSERT INTO tickets (society_id, user_id, title, description, priority) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$society_id, $user_id, $data['title'], $data['description'], $data['priority']]);
                    $ticketId = $pdo->lastInsertId();

                    // Log history
                    $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type) VALUES (?, ?, 'CREATED')";
                    $pdo->prepare($historySql)->execute([$ticketId, $user_id]);

                    // Handle file uploads
                    if (isset($_FILES['attachments'])) {
                        $files = $_FILES['attachments'];
                        $uploadDir = __DIR__ . '/uploads/tickets';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                        foreach ($files['tmp_name'] as $key => $tmpName) {
                            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                                $fileName = $ticketId . '-' . uniqid() . '-' . basename($files['name'][$key]);
                                $targetPath = "$uploadDir/$fileName";
                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    $filePath = rtrim($config['base_url'], '/') . "/uploads/tickets/$fileName";
                                    $sqlAttach = "INSERT INTO ticket_attachments (ticket_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?)";
                                    $stmtAttach = $pdo->prepare($sqlAttach);
                                    $stmtAttach->execute([$ticketId, $filePath, $files['name'][$key], $files['type'][$key]]);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    // If this was a standard form submission (not JSON/AJAX) and redirect flag is present,
                    // redirect the user to the ticket details page instead of returning raw JSON.
                    if (!$isJsonRequest && isset($data['redirect']) && $data['redirect'] === '1') {
                        header('Location: index.php?page=ticket-details&id=' . $ticketId);
                        exit;
                    }
                    echo json_encode(['success' => true, 'message' => 'Ticket created successfully!', 'ticket_id' => $ticketId]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_ticket_details':
                 if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                 try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if (empty($data['id'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ticket ID required.']); exit; }

                    $ticketId = $data['id'];
                    $user_role = $decoded->data->role;
                    $user_society_info = $decoded->data->society ?? null;

                    // This query needs to join against both users and client_users to get creator name
                    $query = "SELECT t.*, s.society_name,
                                     CASE
                                         WHEN t.user_type = 'Client' THEN cu.name
                                         ELSE TRIM(CONCAT(u.first_name, ' ', u.surname))
                                     END as creator_name,
                                     COALESCE(u.profile_photo, cu.profile_photo) as creator_avatar
                              FROM tickets t
                              JOIN society_onboarding_data s ON t.society_id = s.id
                              LEFT JOIN users u ON t.user_id = u.id AND (t.user_type != 'Client' OR t.user_type IS NULL)
                              LEFT JOIN clients_users cu ON t.user_id = cu.id AND t.user_type = 'Client'
                              WHERE t.id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$ticketId]);
                    $ticket = $stmt->fetch();

                    if (!$ticket) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }
                    
                    // --- Permission Check ---
                    if ($user_role !== 'Admin' && (!$user_society_info || $user_society_info->id != $ticket['society_id'])) {
                        http_response_code(403); echo json_encode(['success' => false, 'message' => 'You do not have permission to view this ticket.']); exit;
                    }

                    // Get attachments
                    $stmtAttach = $pdo->prepare("SELECT file_path, file_name FROM ticket_attachments WHERE ticket_id = ? AND comment_id IS NULL");
                    $stmtAttach->execute([$ticketId]);
                    $ticket['attachments'] = $stmtAttach->fetchAll();

                    // Get comments and their attachments
                    $stmtComm = $pdo->prepare("SELECT c.*,
                                                      CASE
                                                          WHEN c.user_type = 'Client' THEN cu.name
                                                          ELSE TRIM(CONCAT(u.first_name, ' ', u.surname))
                                                      END as user_name,
                                                      COALESCE(u.profile_photo, cu.profile_photo) as user_avatar
                                               FROM ticket_comments c
                                               LEFT JOIN users u ON c.user_id = u.id AND (c.user_type != 'Client' OR c.user_type IS NULL)
                                               LEFT JOIN clients_users cu ON c.user_id = cu.id AND c.user_type = 'Client'
                                               WHERE c.ticket_id = ? ORDER BY c.created_at ASC");
                    $stmtComm->execute([$ticketId]);
                    $comments = $stmtComm->fetchAll();

                    $comment_ids = array_map(fn($c) => $c['id'], $comments);
                    $comment_attachments = []; // Initialize the array
                    if (!empty($comment_ids)) {
                        $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
                        $stmtCommAttach = $pdo->prepare("SELECT comment_id, file_path, file_name, file_type FROM ticket_attachments WHERE comment_id IN ($placeholders)");
                        $stmtCommAttach->execute($comment_ids);
                        $comment_attachments = $stmtCommAttach->fetchAll(PDO::FETCH_GROUP);
                    }

                    $ticket['comments'] = array_map(function($c) use ($comment_attachments) {
                        $c['attachments'] = $comment_attachments[$c['id']] ?? [];
                        return $c;
                    }, $comments);

                    // Get the full, real ticket history
                    $historyStmt = $pdo->prepare("
                        SELECT h.*,
                               CASE
                                   WHEN u.id IS NOT NULL THEN TRIM(CONCAT(u.first_name, ' ', u.surname))
                                   WHEN cu.id IS NOT NULL THEN cu.name
                                   ELSE 'System'
                               END as user_name
                        FROM ticket_history h
                        LEFT JOIN users u ON h.user_id = u.id
                        LEFT JOIN clients_users cu ON h.user_id = cu.id
                        WHERE h.ticket_id = ?
                        ORDER BY h.created_at ASC
                    ");
                    $historyStmt->execute([$ticketId]);
                    $raw_history = $historyStmt->fetchAll();
                    
                    $ticket['history'] = array_map(function($item) {
                        $userName = $item['user_name'];
                        $activity = 'made an update.'; // Default
                        $icon = 'fa-pen'; // Default

                        switch($item['activity_type']) {
                            case 'CREATED':
                                $activity = "Ticket created by {$userName}.";
                                $icon = 'fa-plus';
                                break;
                            case 'STATUS_CHANGED':
                                $activity = "{$userName} changed status from <strong>{$item['old_value']}</strong> to <strong>{$item['new_value']}</strong>.";
                                $icon = 'fa-exchange-alt';
                                break;
                            case 'COMMENT_ADDED':
                                $activity = "{$userName} added a comment.";
                                $icon = 'fa-comment';
                                break;
                        }
                        return ['activity' => $activity, 'timestamp' => $item['created_at'], 'icon' => $icon];
                    }, $raw_history);

                    $is_admin_viewer = ($user_role === 'Admin');

                    echo json_encode(['success' => true, 'ticket' => $ticket, 'is_admin_viewer' => $is_admin_viewer]);
                 } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                 }
                 break;
            
            case 'add_ticket_comment':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    $user_id = $decoded->data->id;
                    $user_role = $decoded->data->role;
                    // Use $_POST for multipart/form-data
                    $ticket_id = $_POST['ticket_id'];
                    $comment_text = $_POST['comment'];

                    if (empty($ticket_id) || empty($comment_text)) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ticket ID and comment are required.']); exit;
                    }
                    
                    // TODO: Check if user is allowed to comment on this ticket
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO ticket_comments (ticket_id, user_id, user_type, comment) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$ticket_id, $user_id, $user_role, $comment_text]);
                    $commentId = $pdo->lastInsertId();

                    // Handle file uploads for the comment
                    if (isset($_FILES['attachments'])) {
                        $files = $_FILES['attachments'];
                        $uploadDir = __DIR__ . '/uploads/tickets';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                        foreach ($files['tmp_name'] as $key => $tmpName) {
                            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                                $fileName = $ticket_id . '-' . $commentId . '-' . uniqid() . '-' . basename($files['name'][$key]);
                                $targetPath = "$uploadDir/$fileName";
                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    $filePath = rtrim($config['base_url'], '/') . "/uploads/tickets/$fileName";
                                    $sqlAttach = "INSERT INTO ticket_attachments (ticket_id, comment_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)";
                                    $pdo->prepare($sqlAttach)->execute([$ticket_id, $commentId, $filePath, $files['name'][$key], $files['type'][$key]]);
                                }
                            }
                        }
                    }

                    // Log history
                    $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type) VALUES (?, ?, 'COMMENT_ADDED')";
                    $pdo->prepare($historySql)->execute([$ticket_id, $user_id]);
                    
                    $pdo->commit();

                    // Optionally, return the new comment object
                    echo json_encode(['success' => true, 'message' => 'Comment added.']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;
            
            case 'update_ticket_status':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    $user_id = $decoded->data->id;

                    if ($decoded->data->role !== 'Admin') {
                        http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden.']); exit;
                    }

                    $ticket_id = $data['ticket_id'];
                    $new_status = $data['status'];

                    if (empty($ticket_id) || empty($new_status)) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ticket ID and status are required.']); exit;
                    }

                    $pdo->beginTransaction();

                    // Get old status for logging
                    $stmtOld = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
                    $stmtOld->execute([$ticket_id]);
                    $old_status = $stmtOld->fetchColumn();

                    $sql = "UPDATE tickets SET status = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$new_status, $ticket_id]);

                    // Log history
                    if ($old_status !== $new_status) {
                        $historySql = "INSERT INTO ticket_history (ticket_id, user_id, activity_type, old_value, new_value) VALUES (?, ?, 'STATUS_CHANGED', ?, ?)";
                        $pdo->prepare($historySql)->execute([$ticket_id, $user_id, $old_status, $new_status]);
                    }
                    
                    $pdo->commit();

                    echo json_encode(['success' => true, 'message' => 'Ticket status updated.']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;

            case 'get_ticket_analytics':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    $user_role = $decoded->data->role;
                    $user_society_info = $decoded->data->society ?? null;

                    $baseWhereClauses = [];
                    $params = [];

                    if ($user_role === 'Client') {
                        if (empty($user_society_info)) {
                             http_response_code(403); echo json_encode(['error' => 'Client user not associated with a society.']); exit;
                        }
                        $baseWhereClauses[] = 'society_id = ?';
                        $params[] = $user_society_info->id;
                    } elseif ($user_role === 'Admin' && !empty($_GET['society_id'])) {
                        $baseWhereClauses[] = 'society_id = ?';
                        $params[] = $_GET['society_id'];
                    }

                    // Add search filter if present
                    if (!empty($_GET['search'])) {
                        $baseWhereClauses[] = '(title LIKE ? OR description LIKE ?)';
                        $searchTerm = '%' . $_GET['search'] . '%';
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                    }
                    
                    // Add status filter if present
                    if (!empty($_GET['status'])) {
                        $baseWhereClauses[] = 'status = ?';
                        $params[] = $_GET['status'];
                    }
                    
                    // Add priority filter if present
                    if (!empty($_GET['priority'])) {
                        $baseWhereClauses[] = 'priority = ?';
                        $params[] = $_GET['priority'];
                    }

                    $baseWhere = empty($baseWhereClauses) ? '' : ' WHERE ' . implode(' AND ', $baseWhereClauses);

                    $fetch_count = function($condition) use ($pdo, $baseWhere, $params) {
                        $queryParams = $params;
                        $query = "SELECT COUNT(*) FROM tickets" . $baseWhere;
                        
                        if ($condition) {
                             $query .= ($baseWhere ? ' AND ' : ' WHERE ') . $condition['sql'];
                             $queryParams = array_merge($queryParams, $condition['params']);
                        }
                        
                        $stmt = $pdo->prepare($query);
                        
                        // Bind parameters positionally
                        foreach ($queryParams as $i => $value) {
                            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                            $stmt->bindValue($i + 1, $value, $paramType);
                        }
                        
                        $stmt->execute();
                        return (int)$stmt->fetchColumn();
                    };
                    
                    $analytics = [
                        'total_tickets' => $fetch_count(null),
                        'open_tickets' => $fetch_count(['sql' => "status = ?", 'params' => ['Open']]),
                        'closed_tickets' => $fetch_count(['sql' => "status = ?", 'params' => ['Closed']]),
                        'high_priority' => $fetch_count(['sql' => "priority = ?", 'params' => ['High']]),
                        'older_1_day' => $fetch_count(['sql' => "status = ? AND created_at < NOW() - INTERVAL 1 DAY", 'params' => ['Open']]),
                        'older_1_week' => $fetch_count(['sql' => "status = ? AND created_at < NOW() - INTERVAL 7 DAY", 'params' => ['Open']])
                    ];
                    
                    echo json_encode(['success' => true, 'analytics' => $analytics]);

                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;

            case 'save_company_settings':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                $result = handle_save_company_settings($pdo, $_POST, $_FILES);
                echo json_encode($result);
                break;

            case 'save_hr_settings':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                $result = handle_save_hr_settings($pdo, $_POST);
                echo json_encode($result);
                break;

            case 'create_activity':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                
                $user_id = null;
                try {
                    if (isset($_COOKIE['jwt'])) {
                        $decoded_jwt = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                        $user_id = $decoded_jwt->data->id;
                    }
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Invalid token.']);
                    exit;
                }

                if (!$user_id) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Could not identify user from token.']);
                    exit;
                }

                try {
                    $pdo->beginTransaction();

                    $data = $_POST; // Data from multipart/form-data

                    // --- Validation ---
                    $required_fields = ['society_id', 'title', 'description', 'date', 'status'];
                    foreach ($required_fields as $field) {
                        if (empty($data[$field])) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
                            exit;
                        }
                    }

                    $sql = "INSERT INTO activities (society_id, title, description, scheduled_date, location, tags, status, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['society_id'], $data['title'], $data['description'],
                        $data['date'], $data['location'] ?? null, $data['tags'] ?? null,
                        $data['status'], $user_id
                    ]);
                    
                    $activityId = $pdo->lastInsertId();

                    // --- Optional Assignees ---
                    if (isset($_POST['assignees_submitted'])) {
                        $assignees = isset($_POST['assignees']) && is_array($_POST['assignees']) ? $_POST['assignees'] : [];
                        // Clear any existing (should be none for new)
                        $pdo->prepare("DELETE FROM activity_assignees WHERE activity_id = ?")->execute([$activityId]);
                        if (!empty($assignees)) {
                            $ins = $pdo->prepare("INSERT INTO activity_assignees (activity_id, user_id) VALUES (?, ?)");
                            foreach ($assignees as $uid) {
                                if ($uid !== '' && $uid !== null) { $ins->execute([$activityId, (int)$uid]); }
                            }
                        }
                    }

                    // --- Handle File Uploads ---
                    if (isset($_FILES['attachments'])) {
                        $uploaded_files = handle_activity_photo_uploads($_FILES['attachments'], $activityId);
                        if (!empty($uploaded_files)) {
                            $sqlAttach = "INSERT INTO activity_photos (activity_id, uploaded_by_user_id, image_url, is_approved) VALUES (?, ?, ?, ?)";
                            $stmtAttach = $pdo->prepare($sqlAttach);
                            // Admins upload with auto-approval (is_approved = 1)
                            foreach ($uploaded_files as $file) {
                                $stmtAttach->execute([$activityId, $user_id, $file['path'], 1]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Activity created successfully!', 'activity_id' => $activityId]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;
            
            case 'update_activity':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
                }
                
                try {
                    $pdo->beginTransaction();

                    $user_id = null;
                    if (isset($_COOKIE['jwt'])) {
                        $decoded_jwt = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                        $user_id = $decoded_jwt->data->id;
                    }
                    if (!$user_id) { throw new Exception("User could not be authenticated."); }

                    $data = $_POST;
                    $activityId = $data['activity_id'];

                    if (empty($activityId)) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Activity ID is missing.']); exit;
                    }

                    // Update main activity details
                    $sql = "UPDATE activities SET society_id = ?, title = ?, description = ?, scheduled_date = ?, location = ?, tags = ?, status = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['society_id'], $data['title'], $data['description'],
                        $data['date'], $data['location'] ?? null, $data['tags'] ?? null,
                        $data['status'], $activityId
                    ]);

                    // Handle new file uploads
                    if (isset($_FILES['attachments'])) {
                        $uploaded_files = handle_activity_photo_uploads($_FILES['attachments'], $activityId);
                         if (!empty($uploaded_files)) {
                            $sqlAttach = "INSERT INTO activity_photos (activity_id, uploaded_by_user_id, image_url, is_approved) VALUES (?, ?, ?, ?)";
                            $stmtAttach = $pdo->prepare($sqlAttach);
                            foreach ($uploaded_files as $file) {
                                $stmtAttach->execute([$activityId, $user_id, $file['path'], 1]);
                            }
                        }
                    }

                    // --- Optional Assignees Update ---
                    if (isset($_POST['assignees_submitted'])) {
                        $assignees = isset($_POST['assignees']) && is_array($_POST['assignees']) ? $_POST['assignees'] : [];
                        $pdo->prepare("DELETE FROM activity_assignees WHERE activity_id = ?")->execute([$activityId]);
                        if (!empty($assignees)) {
                            $ins = $pdo->prepare("INSERT INTO activity_assignees (activity_id, user_id) VALUES (?, ?)");
                            foreach ($assignees as $uid) {
                                if ($uid !== '' && $uid !== null) { $ins->execute([$activityId, (int)$uid]); }
                            }
                        }
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Activity updated successfully!']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;
            case 'get_activity_assignees':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }
                $societyId = (int)($_GET['society_id'] ?? 0);
                if ($societyId <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'society_id is required']); exit; }
                try {
                    $stmt = $pdo->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type
                                            FROM supervisor_site_assignments s
                                            JOIN users u ON u.id = s.supervisor_id
                                            WHERE s.site_id = ? AND u.user_type IN ('Supervisor','Site Supervisor')
                                            ORDER BY name ASC");
                    $stmt->execute([$societyId]);
                    echo json_encode(['success' => true, 'assignees' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;

            case 'delete_activity':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
                }
                try {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $activityId = $data['id'];

                    if (empty($activityId)) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Activity ID is required.']); exit;
                    }

                    $pdo->beginTransaction();

                    // 1. Get all photos for the activity
                    $stmtPhotos = $pdo->prepare("SELECT image_url FROM activity_photos WHERE activity_id = ?");
                    $stmtPhotos->execute([$activityId]);
                    $photos = $stmtPhotos->fetchAll();

                    // 2. Delete the physical photo files
                    foreach ($photos as $photo) {
                         $filePath = str_replace($config['base_url'], '', $photo['image_url']);
                         $serverPath = __DIR__ . $filePath;
                         if (file_exists($serverPath)) {
                             unlink($serverPath);
                         }
                    }

                    // 3. Delete photo records from the database
                    $stmtDeletePhotos = $pdo->prepare("DELETE FROM activity_photos WHERE activity_id = ?");
                    $stmtDeletePhotos->execute([$activityId]);
                    
                    // 4. Delete the activity itself
                    $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
                    $stmt->execute([$activityId]);

                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'Activity and all associated photos deleted successfully.']);
                    } else {
                        $pdo->rollBack();
                        http_response_code(404); echo json_encode(['success' => false, 'message' => 'Activity not found.']);
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;

            case 'delete_activity_photo':
                 header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
                }
                try {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $photoId = $data['id'];

                    if (empty($photoId)) {
                        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Photo ID is required.']); exit;
                    }

                    $pdo->beginTransaction();

                    // Get photo URL to delete file
                    $stmt = $pdo->prepare("SELECT image_url FROM activity_photos WHERE id = ?");
                    $stmt->execute([$photoId]);
                    $photo = $stmt->fetch();

                    if ($photo) {
                        // Delete the actual file from server storage
                        $filePath = str_replace($config['base_url'], '', $photo['image_url']);
                        $serverPath = __DIR__ . $filePath;
                        if (file_exists($serverPath)) {
                            unlink($serverPath);
                        }
                    }
                    
                    // Delete the database record
                    $stmt = $pdo->prepare("DELETE FROM activity_photos WHERE id = ?");
                    $stmt->execute([$photoId]);

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Photo deleted.']);

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
                }
                break;

            case 'generate_id_card':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); die('Unauthorized'); }
                if (!isset($_GET['id'])) { die('Employee ID is required.'); }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); die('Forbidden'); }
                    
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $employee = $stmt->fetch();
                    if (!$employee) die('Employee not found.');

                    // --- Image to Data URI conversion ---
                    $image_to_data_uri = function($path) {
                        if (empty($path)) return null;
                        
                        // Convert relative path to absolute path
                        $absolutePath = $path;
                        if (!file_exists($path)) {
                            // If it's a relative path, try to make it absolute
                            $absolutePath = __DIR__ . '/' . ltrim($path, '/');
                        }
                        
                        if (!file_exists($absolutePath)) {
                            error_log("ID Card: Profile photo not found at: " . $absolutePath);
                            return null;
                        }
                        
                        $type = pathinfo($absolutePath, PATHINFO_EXTENSION);
                        $data = file_get_contents($absolutePath);
                        return 'data:image/' . $type . ';base64,' . base64_encode($data);
                    };

                    // Convert profile photo and company logo to data URIs
                    $employee['profile_photo_src'] = $image_to_data_uri($employee['profile_photo']);
                    $company_logo_src = $image_to_data_uri($company_settings['logo_path'] ?? null);
                    
                    // Debug logging
                    error_log("ID Card Generation Debug:");
                    error_log("Employee ID: " . $employee['id']);
                    error_log("Profile photo path from DB: " . ($employee['profile_photo'] ?? 'NULL'));
                    error_log("Profile photo src (data URI): " . (empty($employee['profile_photo_src']) ? 'EMPTY' : 'GENERATED'));
                    error_log("Company logo path: " . ($company_settings['logo_path'] ?? 'NULL'));
                    error_log("Company logo src (data URI): " . (empty($company_logo_src) ? 'EMPTY' : 'GENERATED'));
                    
                    // Generate QR Code
                    $qrCodeUrl = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}&page=view-employee&id={$employee['id']}";
                     
                    // Start output buffering to capture the template
                    ob_start();
                    require __DIR__ . '/templates/pdf/id_card_template.php';
                    $html = ob_get_clean();

                    $options = new Options();
                    $options->set('isRemoteEnabled', true); // Allows loading images from URLs
                    $options->set('defaultFont', 'Helvetica');
                    
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    $filename = 'ID_Card_' . $employee['first_name'] . '_' . $employee['id'] . '.pdf';
                    $dompdf->stream($filename, ["Attachment" => true]); // true to force download

                } catch (Exception $e) {
                    http_response_code(500); 
                    error_log('PDF Generation Error: ' . $e->getMessage());
                    echo 'An error occurred during PDF generation.'; 
                    exit;
                }
                break;

            case 'id-card-pdf':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); die('Unauthorized'); }
                if (!isset($_GET['id'])) { die('Employee ID is required.'); }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); die('Forbidden'); }
                    
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $employee = $stmt->fetch();
                    if (!$employee) die('Employee not found.');

                    // Prepare data for ID card template
                    $page_data = [
                        'employee' => $employee,
                        'company_settings' => $company_settings,
                        'config' => $config
                    ];
                    
                    // Calculate expiry date (joining date + 3 years)
                    $joiningDate = $employee['date_of_joining'] ?? date('Y-m-d');
                    $expiryDate = date('Y-m-d', strtotime($joiningDate . ' +3 years'));
                    
                    // Generate vCard for QR code
                    $vCardData = generateVCard($employee);
                    $qrCodeUrl = generateQRCode($vCardData);
                    
                    // Add additional data to page_data
                    $page_data['expiry_date'] = $expiryDate;
                    $page_data['qr_code_url'] = $qrCodeUrl;
                    $page_data['vcard_data'] = $vCardData;
                    
                    // Make variables available to template
                    $employee = $page_data['employee'];
                    $company_settings = $page_data['company_settings'];
                    $config = $page_data['config'];
                    $expiry_date = $page_data['expiry_date'];
                    $qr_code_url = $page_data['qr_code_url'];
                    $vcard_data = $page_data['vcard_data'];
                    
                    // Start output buffering to capture the template
                    ob_start();
                    require __DIR__ . '/templates/pdf/rpf_id_card_templete.php';
                    $html = ob_get_clean();

                    $options = new Options();
                    $options->set('isRemoteEnabled', true);
                    $options->set('defaultFont', 'Helvetica');
                    
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    $employeeCode = $employee['employee_code'] ?? $employee['id'];
                    $filename = 'ID_Card_' . $employeeCode . '.pdf';
                    $dompdf->stream($filename, ["Attachment" => true]);

                } catch (Exception $e) {
                    http_response_code(500); 
                    error_log('ID Card PDF Error: ' . $e->getMessage());
                    echo 'An error occurred during PDF generation.'; 
                    exit;
                }
                break;

            case 'resume-pdf':
                if (!isset($_COOKIE['jwt'])) { 
                    http_response_code(401); 
                    die('Unauthorized'); 
                }
                if (!isset($_GET['id'])) { 
                    die('Employee ID is required.'); 
                }
                
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { 
                        http_response_code(403); 
                        die('Forbidden'); 
                    }
                    
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $employee = $stmt->fetch();
                    if (!$employee) die('Employee not found.');

                    // Load family references
                    try {
                        $stmt_family = $pdo->prepare("SELECT * FROM employee_family_references WHERE employee_id = ? ORDER BY reference_index ASC");
                        $stmt_family->execute([$_GET['id']]);
                        $family_references = $stmt_family->fetchAll();
                        $page_data['family_references'] = $family_references;
                    } catch (Throwable $t) {
                        // Silently ignore if table doesn't exist yet
                        $page_data['family_references'] = [];
                    }

                    // Prepare data for template
                    $page_data['employee'] = $employee;
                    $page_data['company_settings'] = $company_settings;
                    $page_data['config'] = $config;
                    
                    // Make variables available to template
                    $employee = $page_data['employee'];
                    $company_settings = $page_data['company_settings'];
                    $config = $page_data['config'];
                    
                    // Start output buffering to capture the template
                    ob_start();
                    require __DIR__ . '/templates/pdf/resume_template.php';
                    $html = ob_get_clean();

                    $options = new Options();
                    $options->set('isRemoteEnabled', true);
                    $options->set('defaultFont', 'Helvetica');
                    $options->set('chroot', __DIR__);
                    
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    // Generate filename: "{COMPANY NAME}, RESUME - {USER NAME}.pdf"
                    $companyName = $company_settings['company_name'] ?? 'Company';
                    $userName = trim($employee['first_name'] . ' ' . $employee['surname']);
                    $sanitize = function($str) {
                        return preg_replace('/[\/\\?%*:|"<>]/', '', $str);
                    };
                    $filename = $sanitize($companyName) . ', RESUME - ' . $sanitize($userName) . '.pdf';
                    
                    $dompdf->stream($filename, ["Attachment" => true]);

                } catch (Exception $e) {
                    http_response_code(500); 
                    error_log('Resume PDF Error: ' . $e->getMessage() . ' | Employee ID: ' . ($_GET['id'] ?? 'unknown') . ' | Route: resume-pdf');
                    echo 'An error occurred during PDF generation.'; 
                    exit;
                }
                break;

            case 'get_all_tags':
                header('Content-Type: application/json');
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode([]);
                    exit;
                }
                try {
                    $stmt = $pdo->query("SELECT DISTINCT tags FROM activities WHERE tags IS NOT NULL AND tags != ''");
                    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $tags = [];
                    foreach ($results as $tagString) {
                        // Attempt to decode as JSON
                        $decoded_tags = json_decode($tagString, true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tags)) {
                            // It's JSON from Tagify, extract the 'value'
                            $values = array_column($decoded_tags, 'value');
                            $tags = array_merge($tags, $values);
                        } else {
                            // It's a plain comma-separated string
                            $tags = array_merge($tags, array_map('trim', explode(',', $tagString)));
                        }
                    }
                    // Return a flat, unique array of tag strings
                    echo json_encode(array_values(array_unique($tags)));
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([]);
                }
                break;
            case 'update_user_status':
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    if ($decoded->data->role !== 'Admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
                    
                    if (!isset($data['id'], $data['web_access'], $data['mobile_access'])) {
                        http_response_code(400); echo json_encode(['error' => 'User ID and access states are required.']); exit;
                    }

                    $sql = "UPDATE users SET web_access = ?, mobile_access = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        (int)(bool)$data['web_access'], 
                        (int)(bool)$data['mobile_access'], 
                        $data['id']
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'User status updated successfully.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'User status is already up to date.']);
                    }
                } catch (Exception $e) {
                    http_response_code(401); echo json_encode(['error' => 'Invalid Token or server error.']);
                }
                break;
            case 'view-society':
                if (isset($_GET['id'])) {
                    $stmt = $pdo->prepare("SELECT s.*, ct.type_name as client_type_name 
                                         FROM society_onboarding_data s 
                                         LEFT JOIN client_types ct ON s.client_type_id = ct.id 
                                         WHERE s.id = ?");
                    $stmt->execute([$_GET['id']]);
                    $society = $stmt->fetch();
                    if ($society) {
                        $page_data['society'] = $society;
                        
                        // Fetch client users for this society
                        $stmt_clients = $pdo->prepare("SELECT id, name, username, email, phone, is_primary FROM clients_users WHERE society_id = ? ORDER BY is_primary DESC, name ASC");
                        $stmt_clients->execute([$_GET['id']]);
                        $page_data['all_client_users'] = $stmt_clients->fetchAll();

                        // Fetch primary contact
                        $stmt_primary = $pdo->prepare("SELECT id, name, username, email, phone FROM clients_users WHERE society_id = ? AND is_primary = 1");
                        $stmt_primary->execute([$_GET['id']]);
                        $page_data['primary_contact'] = $stmt_primary->fetch();
                    }
                }
                break;
            case 'edit-society':
                if (isset($_GET['id'])) {
                    $stmt = $pdo->prepare("SELECT s.*, ct.type_name as client_type_name 
                                         FROM society_onboarding_data s 
                                         LEFT JOIN client_types ct ON s.client_type_id = ct.id 
                                         WHERE s.id = ?");
                    $stmt->execute([$_GET['id']]);
                    $society = $stmt->fetch();
                    if (!$society) { die('Society not found.'); }
                    $page_data['society'] = $society;
                    
                    // Fetch client users for this society
                    $stmt_users = $pdo->prepare("SELECT id, name, username, email, phone, is_primary FROM clients_users WHERE society_id = ? ORDER BY is_primary DESC, name ASC");
                    $stmt_users->execute([$_GET['id']]);
                    $page_data['all_client_users'] = $stmt_users->fetchAll();
                }
                break;
            case 'get_site_supervisors':
                header('Content-Type: application/json');
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    
                    // Check required parameters
                    if (empty($_POST['site_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Site ID is required']);
                        exit;
                    }
                    
                    $site_id = (int)$_POST['site_id'];
                    
                    // Get all supervisors assigned to this site
                    $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.surname) as name, 
                                    u.user_type, u.profile_photo
                     FROM users u
                     JOIN supervisor_site_assignments ssa ON u.id = ssa.supervisor_id
                     WHERE ssa.site_id = ?
                     ORDER BY u.user_type, u.first_name";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$site_id]);
                    $supervisors = $stmt->fetchAll();
                    
                    echo json_encode(['success' => true, 'supervisors' => $supervisors]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
            case 'assign_supervisors':
                header('Content-Type: application/json');
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    
                    // Check required parameters
                    if (empty($_POST['site_id']) || !isset($_POST['supervisor_ids'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Site ID and supervisor IDs are required']);
                        exit;
                    }
                    
                    $site_id = (int)$_POST['site_id'];
                    $supervisor_ids = json_decode($_POST['supervisor_ids'], true);
                    
                    if (!is_array($supervisor_ids)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid supervisor IDs format']);
                        exit;
                    }
                    
                    // Remove duplicates
                    $supervisor_ids = array_unique(array_map('intval', $supervisor_ids));
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // First, remove all existing assignments for this site
                        $delete_stmt = $pdo->prepare("DELETE FROM supervisor_site_assignments WHERE site_id = ?");
                        $delete_stmt->execute([$site_id]);
                        
                        // Then, add new assignments
                        if (!empty($supervisor_ids)) {
                            $insert_stmt = $pdo->prepare("INSERT INTO supervisor_site_assignments (supervisor_id, site_id) VALUES (?, ?)");
                            
                            foreach ($supervisor_ids as $supervisor_id) {
                                $insert_stmt->execute([$supervisor_id, $site_id]);
                            }
                        }
                        
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'Supervisors assigned successfully']);
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        
                        if ($e->errorInfo[1] == 1062) { // MySQL duplicate entry error code
                            http_response_code(409);
                            echo json_encode(['success' => false, 'error' => 'One or more supervisors are already assigned to another site']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                        }
                    }
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
            case 'get_available_supervisors':
                header('Content-Type: application/json');
                if (!isset($_COOKIE['jwt'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
                try {
                    $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
                    
                    // Check required parameters
                    if (empty($_POST['site_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Site ID is required']);
                        exit;
                    }
                    
                    $site_id = (int)$_POST['site_id'];
                    
                    // Get all supervisors that are NOT assigned to this site
                    $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.surname) as name, 
                                    u.user_type, u.profile_photo
                     FROM users u
                     WHERE u.user_type IN ('Supervisor', 'Site Supervisor')
                     AND u.id NOT IN (
                         SELECT supervisor_id 
                         FROM supervisor_site_assignments 
                         WHERE site_id = ?
                     )
                     ORDER BY u.user_type, u.first_name";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$site_id]);
                    $supervisors = $stmt->fetchAll();
                    
                    echo json_encode(['success' => true, 'supervisors' => $supervisors]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                }
                break;
            case 'get_activities':
                header('Content-Type: application/json');
                if (!is_authenticated()) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                try {
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $perPage = 10;
                    $offset = ($page - 1) * $perPage;

                    $baseQuery = "SELECT a.*, s.society_name FROM activities a JOIN society_onboarding_data s ON a.society_id = s.id";
                    $countQuery = "SELECT COUNT(a.id) FROM activities a";
                    
                    $whereClauses = [];
                    $params = [];

                    if (!empty($_GET['search'])) {
                        $whereClauses[] = "a.title LIKE ?";
                        $params[] = '%' . $_GET['search'] . '%';
                    }
                    if (!empty($_GET['society_id'])) {
                        $whereClauses[] = "a.society_id = ?";
                        $params[] = $_GET['society_id'];
                    }
                    if (!empty($_GET['status'])) {
                        $whereClauses[] = "a.status = ?";
                        $params[] = $_GET['status'];
                    }

                    if (!empty($whereClauses)) {
                        $whereSQL = " WHERE " . implode(' AND ', $whereClauses);
                        $baseQuery .= $whereSQL;
                        $countQuery .= $whereSQL;
                    }

                    // Get total count for pagination
                    $countStmt = $pdo->prepare($countQuery);
                    $countStmt->execute($params);
                    $totalRecords = (int)$countStmt->fetchColumn();
                    $totalPages = ceil($totalRecords / $perPage);

                    // Get paginated results
                    $baseQuery .= " ORDER BY a.scheduled_date DESC LIMIT ? OFFSET ?";
                    $params[] = $perPage;
                    $params[] = $offset;
                    
                    $stmt = $pdo->prepare($baseQuery);
                    // PDO requires positional params to be 1-indexed
                    foreach ($params as $key => $value) {
                         // Determine type for bindValue
                        if (is_int($value)) {
                            $stmt->bindValue($key + 1, $value, PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue($key + 1, $value, PDO::PARAM_STR);
                        }
                    }
                    $stmt->execute();
                    $activities = $stmt->fetchAll();

                    echo json_encode([
                        'success' => true,
                        'activities' => $activities,
                        'pagination' => [
                            'total_records' => $totalRecords,
                            'total_pages' => $totalPages,
                            'current_page' => $page,
                            'per_page' => $perPage
                        ]
                    ]);

                } catch (Exception $e) {
                    error_log("Error in get_activities: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'A server error occurred while fetching activities.']);
                }
                break;

            case 'update_company_settings':
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                updateCompanySettings($db, $_POST, $_FILES);
                // The function in the controller should handle the response, but we can add a fallback.
                // This might need adjustment based on what updateCompanySettings returns.
                echo json_encode(['success' => true, 'message' => 'Company settings triggered for update.']);
                break;

            case 'update_hr_settings':
                if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                updateHRSettings($db, $_POST);
                echo json_encode(['success' => true, 'message' => 'HR settings triggered for update.']);
                break;

            case 'assign_supervisor':
                 if (!is_authenticated() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                assignSupervisor($db, $_POST);
                echo json_encode(['success' => true, 'message' => 'Supervisor assignment triggered.']);
                break;

            case 'manage_client_type':
                require_once 'actions/client_type_controller.php';
                break;
            case 'get_client_types':
                require_once 'actions/get_client_types.php';
                break;
            case 'advance-salary':
                require_once __DIR__ . '/actions/advance_payment_controller.php';
                $controller = new AdvancePaymentController();
                // Use default params for this flow
                $page_data = $controller->listAdvancePayments('', 1, 10);
                require 'UI/advance_payment_management_view.php';
                break;
            case 'salary-calculation':
                // Salary Calculation Page
                $page_data = [];
                include_once __DIR__ . '/UI/hr/salary_management/salary_calculation_view.php';
                break;
            case 'salary-records':
                // Salary Records Page
                $page_data = [];
                include_once __DIR__ . '/UI/hr/salary_management/salary_records_view.php';
                break;
            case 'salary-slips':
                // Salary Slips Page
                $page_data = [];
                include_once __DIR__ . '/UI/hr/salary_management/salary_slips_view.php';
                break;
            case 'deduction-master':
                // Deduction Master Page
                $page_data = [];
                include_once __DIR__ . '/UI/hr/salary_management/deduction_master_view.php';
                break;
            case 'billing-dashboard':
                // Billing Dashboard Page
                $page_data = [];
                include_once __DIR__ . '/UI/hr/billing_dashboard_view.php';
                break;

        }
                            exit;
                        }
                    }

// --- Page Views ---
// Check for JWT and decode outside the switch for cleaner access
$decoded_jwt = null;
$is_logged_in = false;
try {
                    if (isset($_COOKIE['jwt'])) {
                        $decoded_jwt = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
        $is_logged_in = true;

        // Graceful migration for old tokens
        if (!isset($decoded_jwt->data->full_name) || !isset($decoded_jwt->data->profile_photo)) {
            $new_payload_data = get_refreshed_user_data($pdo, $decoded_jwt->data->id, $decoded_jwt->data->role);
            if ($new_payload_data) {
                $decoded_jwt->data = (object) array_merge((array) $decoded_jwt->data, (array) $new_payload_data);
                // Re-issue the cookie with the updated token
                $jwt = JWT::encode((array)$decoded_jwt, $config['jwt']['secret'], 'HS256');
                setcookie('jwt', $jwt, ['expires' => time() + (60 * 60 * 24), 'path' => '/', 'samesite' => 'Lax']);
            }
        }
    }
                } catch (Exception $e) {
    // Invalid token, user is not logged in
    $is_logged_in = false;
}

// Fetch global company settings for all pages
$company_settings = get_company_settings($pdo);

// If not logged in and not on the login/register page, redirect to login
if (!$is_logged_in && !in_array($page, ['login', 'register', 'forgot-password'])) {
    header("Location: index.php?page=login");
                    exit;
                }

// Page data to be passed to views
// $page_data will be set in the switch statement for specific pages
$page_data = [];

if ($is_logged_in) {
    $user_id = $decoded_jwt->data->id ?? null;
    $user_role = $decoded_jwt->data->role ?? null;
    $user_full_name = get_user_full_name($decoded_jwt->data);
    $user_profile_photo = get_user_profile_photo($decoded_jwt->data);
    $is_admin = ($user_role === 'Admin');
    $user_society_info = $decoded_jwt->data->society ?? null;

    // Fetch data needed for specific views
    if ($page === 'ticket-list' || $page === 'create-ticket' || $page === 'create-activity') {
        $stmt = $pdo->query("SELECT id, society_name FROM society_onboarding_data ORDER BY society_name");
        $societies = $stmt->fetchAll();
        $all_societies = $societies; // Make sure this is available to the view
    }
    
    if ($page === 'company-settings') {
        // Settings are already fetched globally
        $page_data['company_settings'] = $company_settings;
    }
    
    if ($page === 'hr-settings') {
        $page_data['hr_settings'] = get_hr_settings($pdo);
    }

    if ($page === 'attendance-master') {
        // No specific data to fetch for the master list page itself
    }

    if ($page === 'view-attendance-type' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM attendance_master WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $type = $stmt->fetch();
        if ($type) {
            $page_data['attendance_type'] = $type;
        } else {
            // Handle not found, e.g., redirect or show an error
        }
    }

    if ($page === 'view-activity' || $page === 'edit-activity') {
        if (!isset($_GET['id'])) die('Activity ID is required.');
        
        $activityId = $_GET['id'];

        $stmt = $pdo->prepare("\n            SELECT a.*, s.society_name, CONCAT(u.first_name, ' ', u.surname) as creator_name\n            FROM activities a\n            JOIN society_onboarding_data s ON a.society_id = s.id\n            JOIN users u ON a.created_by = u.id\n            WHERE a.id = ?\n        ");
        $stmt->execute([$activityId]);
        $activity = $stmt->fetch();

        if (!$activity) die('Activity not found.');

        $stmtPhotos = $pdo->prepare("SELECT id, image_url FROM activity_photos WHERE activity_id = ? ORDER BY created_at DESC");
        $stmtPhotos->execute([$activityId]);
        $photos = $stmtPhotos->fetchAll();

        // Normalize photo URLs: if stored value is a full URL, convert to path; when rendering use base_url
        $config = require __DIR__ . '/config.php';
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        foreach ($photos as &$p) {
            $url = $p['image_url'] ?? '';
            if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                // If URL begins with base_url, strip it to keep only path for storage consistency
                $withoutBase = preg_replace('#^' . preg_quote($baseUrl, '#') . '#', '', $url);
                $p['image_url'] = ltrim($withoutBase, '/');
            }
            // Build absolute URL for UI
            if (!empty($p['image_url']) && !(strpos($p['image_url'], 'http://') === 0 || strpos($p['image_url'], 'https://') === 0)) {
                $p['image_url_full'] = $baseUrl . '/' . ltrim($p['image_url'], '/');
            } else {
                $p['image_url_full'] = $p['image_url'];
            }
        }
        unset($p);

        // Fetch assignees for display/edit
        $stmtAss = $pdo->prepare("SELECT aa.user_id, CONCAT(u.first_name, ' ', u.surname) AS name, u.user_type\n                                   FROM activity_assignees aa JOIN users u ON u.id = aa.user_id WHERE aa.activity_id = ? ORDER BY name ASC");
        $stmtAss->execute([$activityId]);
        $activity_assignees = $stmtAss->fetchAll(PDO::FETCH_ASSOC);

        // For edit page, we need the societies list as well
        if ($page === 'edit-activity') {
             $stmt_soc = $pdo->query("SELECT id, society_name FROM society_onboarding_data ORDER BY society_name");
             $societies = $stmt_soc->fetchAll();
        }
    }
}

// --- Render Page ---
if (in_array($page, ['login', 'register', 'forgot-password'])) {
    if ($decoded_jwt) { // If token is valid, go to dashboard
        header('Location: index.php?page=dashboard');
                        exit;
                    }
    require __DIR__ . '/UI/login_view.php';
                        } else {
    // Protected area
    if (!$is_logged_in) {
        header('Location: index.php?page=login');
                        exit; 
                    }
                    
    // Pass required user data for the layout
    $user_email = $decoded_jwt->data->email;

    // Admin-only page protection
    $admin_only_pages = [
        'enroll-employee', 'view-employee', 'edit-employee', 
        'company-settings', 'mobile-app-settings', 'hr-settings'
    ];
    if (in_array($page, $admin_only_pages) && !$is_admin) {
        http_response_code(403);
        die('403 Forbidden - You do not have permission to access this page.');
    }
    
    // --- Data Fetching for Specific Views ---
    if (($page === 'view-employee' || $page === 'edit-employee') && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $employee = $stmt->fetch();
        if (!$employee) {
            die('Employee not found.');
        }
        unset($employee['password']);

        // Compute current Advance Salary outstanding balance from the new system
        try {
            $stmtAdv = $pdo->prepare(
                "SELECT COALESCE(SUM(remaining_balance), 0) AS outstanding
                 FROM advance_payments
                 WHERE employee_id = ? AND status IN ('active','approved')"
            );
            $stmtAdv->execute([$_GET['id']]);
            $outstanding = (float)$stmtAdv->fetchColumn();
            $employee['advance_salary'] = $outstanding;
        } catch (Throwable $t) {
            // Silently ignore and fall back to any legacy value if present
        }

        // Load family references
        try {
            $stmt_family = $pdo->prepare("SELECT * FROM employee_family_references WHERE employee_id = ? ORDER BY reference_index ASC");
            $stmt_family->execute([$_GET['id']]);
            $family_references = $stmt_family->fetchAll();
            $page_data['family_references'] = $family_references;
        } catch (Throwable $t) {
            // Silently ignore if table doesn't exist yet
            $page_data['family_references'] = [];
        }

        $page_data['employee'] = $employee;
    }

    // ID Card View routing - use standalone layout
    if ($page === 'id-card-view' && isset($_GET['id'])) {
        if (!isset($_COOKIE['jwt'])) { 
            http_response_code(401); 
            die('Unauthorized'); 
        }
        
        try {
            $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
            if ($decoded->data->role !== 'Admin') { 
                http_response_code(403); 
                die('Forbidden'); 
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $employee = $stmt->fetch();
            if (!$employee) die('Employee not found.');

            // Calculate expiry date (joining date + 3 years)
            $joiningDate = $employee['date_of_joining'] ?? date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime($joiningDate . ' +3 years'));
            
            // Generate vCard for QR code
            $vCardData = generateVCard($employee);
            $qrCodeUrl = generateQRCode($vCardData);
            
            // Prepare data for template
            $page_data['employee'] = $employee;
            $page_data['company_settings'] = $company_settings;
            $page_data['config'] = $config;
            $page_data['expiry_date'] = $expiryDate;
            $page_data['qr_code_url'] = $qrCodeUrl;
            $page_data['vcard_data'] = $vCardData;
            
            // Use standalone layout instead of dashboard layout
            require __DIR__ . '/UI/standalone_layout.php';
            exit;
            
        } catch (Exception $e) {
            http_response_code(500); 
            error_log('ID Card View Error: ' . $e->getMessage());
            die('An error occurred while loading the ID card.'); 
        }
    }

    // Resume View routing - use standalone layout
    if ($page === 'resume-view' && isset($_GET['id'])) {
        if (!isset($_COOKIE['jwt'])) { 
            http_response_code(401); 
            die('Unauthorized'); 
        }
        
        try {
            $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
            if ($decoded->data->role !== 'Admin') { 
                http_response_code(403); 
                die('Forbidden'); 
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $employee = $stmt->fetch();
            if (!$employee) die('Employee not found.');

            // Load family references
            try {
                $stmt_family = $pdo->prepare("SELECT * FROM employee_family_references WHERE employee_id = ? ORDER BY reference_index ASC");
                $stmt_family->execute([$_GET['id']]);
                $family_references = $stmt_family->fetchAll();
                $page_data['family_references'] = $family_references;
            } catch (Throwable $t) {
                // Silently ignore if table doesn't exist yet
                $page_data['family_references'] = [];
            }

            // Prepare data for template
            $page_data['employee'] = $employee;
            $page_data['company_settings'] = $company_settings;
            $page_data['config'] = $config;
            
            // Use standalone layout for resume view
            require __DIR__ . '/UI/standalone_layout.php';
            exit;
            
        } catch (Exception $e) {
            http_response_code(500); 
            error_log('Resume View Error: ' . $e->getMessage());
            die('An error occurred while loading the resume.'); 
        }
    }

    if (($page === 'view-society' || $page === 'edit-society') && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT s.*, ct.type_name as client_type_name 
                             FROM society_onboarding_data s 
                             LEFT JOIN client_types ct ON s.client_type_id = ct.id 
                             WHERE s.id = ?");
        $stmt->execute([$_GET['id']]);
        $society = $stmt->fetch();
        if (!$society) { die('Society not found.'); }
        $page_data['society'] = $society;
        
        // Fetch client users for this society
        $stmt_users = $pdo->prepare("SELECT id, name, username, email, phone, is_primary FROM clients_users WHERE society_id = ? ORDER BY is_primary DESC, name ASC");
        $stmt_users->execute([$_GET['id']]);
        $page_data['all_client_users'] = $stmt_users->fetchAll();
    }
    
    if ($page === 'dashboard') {
        // Fetch total societies
        $stmt = $pdo->query("SELECT COUNT(*) FROM society_onboarding_data");
        $page_data['total_societies'] = $stmt->fetchColumn();

        // Fetch total staff (excluding Admins)
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type != 'Admin'");
        $page_data['total_staff'] = $stmt->fetchColumn();
        
        // Count active employees (logged in but not logged out) - using connection pool
        try {
            // Use the connection pool for industrial-grade robustness
            $pooledConnection = get_db_connection();
            
            // Simple and direct query as requested - count all employees with shift_start but no shift_end
            $stmt = $pooledConnection->prepare("
                SELECT COUNT(DISTINCT user_id) AS count
                FROM attendance
                WHERE shift_start IS NOT NULL
                AND shift_end IS NULL
            ");
            $stmt->execute();
            $page_data['active_employees'] = $stmt->fetchColumn();
            
            // Log the query result for debugging
            error_log("Dashboard Active Employees Query Result: " . $page_data['active_employees']);
            
        } catch (Exception $e) {
            // Robust error handling for industrial-grade system
            error_log("Dashboard Active Employees Query Error: " . $e->getMessage());
            $page_data['active_employees'] = 0;
        }

        // Fetch all society locations for the map
        $stmt = $pdo->query("SELECT society_name, latitude, longitude FROM society_onboarding_data WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
        $page_data['societies_for_map'] = $stmt->fetchAll();
    }
    

    
    // Advance Payment Management Routes (Brand New System)
    if ($page === 'advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            echo "Access denied. Admin or Supervisor role required.";
            exit;
        }
        
        // Set session data for the controller
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_role'] = $user_role;
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        
        // Get search and pagination parameters
        $search = $_GET['search'] ?? '';
        $current_page = (int)($_GET['page_num'] ?? 1);
        $per_page = (int)($_GET['per_page'] ?? 10);
        
        $filters = [
            'status' => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? ($_GET['type'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        $page_data = $controller->listAdvancePayments($search, $current_page, $per_page, $filters);
    }
    
    if ($page === 'advance-salary-details') {
        // Check authorization
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        $result = $controller->getPaymentDetails($_GET['id'] ?? 0);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'create-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
        exit;
    }
    
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        $result = $controller->createAdvancePayment($_POST);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'update-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        $result = $controller->updateAdvancePayment($_POST);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'approve-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $result = $controller->approveAdvancePayment($input['id'] ?? 0);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'activate-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $result = $controller->activateAdvancePayment($input['id'] ?? 0);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'cancel-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $result = $controller->cancelAdvancePayment($input['id'] ?? 0, $input['reason'] ?? '');
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($page === 'export-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            echo 'Access denied';
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        $search = $_GET['search'] ?? '';
        $format = $_GET['format'] ?? 'csv';
        // Pass same filters for export
        $filters = [
            'status' => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? ($_GET['type'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        // Reuse listAdvancePayments to build where clause and then export OR call export directly and rebuild filters similarly
        // For simplicity, call export with search only; filters appended below in query string
        $result = $controller->exportAdvancePayments($search, $filters);
        
        if (!$result['success']) {
            http_response_code(500);
            echo 'Export failed';
            exit;
        }
        
        // Output CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="advance_payments_export.csv"');
        
        $out = fopen('php://output', 'w');
        // Headings
        fputcsv($out, [
            'ID','Request Number','Employee','Type','Amount','Monthly Deduction','Remaining Balance','Installments','Paid Installments','Priority','Emergency','Status','Start Date','Completion Date','Cancel Reason','Cancelled At','Created At'
        ]);
        foreach ($result['rows'] as $row) {
            fputcsv($out, [
                $row['id'],
                $row['request_number'],
                $row['employee_name'],
                $row['employee_type'],
                $row['amount'],
                $row['monthly_deduction'],
                $row['remaining_balance'],
                $row['installment_count'],
                $row['paid_installments'],
                $row['priority'],
                $row['is_emergency'] ? 'Yes' : 'No',
                $row['status'],
                $row['start_date'],
                $row['completion_date'],
                $row['cancel_reason'],
                $row['cancelled_at'],
                $row['created_at']
            ]);
        }
        fclose($out);
        exit;
    }
    
    if ($page === 'process-deduction-advance-salary') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied. Admin or Supervisor role required.']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $result = $controller->processMonthlyDeduction($input['id'] ?? 0, $input['amount'] ?? 0);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    if ($page === 'advance-salary-employees') {
        // Check authorization - only Admin and Supervisor can access
        $allowed_roles = ['Admin', 'Supervisor'];
        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        require_once __DIR__ . '/actions/advance_payment_controller.php';
        $controller = new AdvancePaymentController();
        $result = $controller->getEmployeesList();
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // This single require statement handles all dashboard pages.
    // The logic inside dashboard_layout.php determines which sub-view to show.
    require __DIR__ . '/UI/dashboard_layout.php';
} 





// Add this function near the top of the file
function logPageData($message, $data = null) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/advance_salary_index_debug.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    
    $logMessage = $timestamp . " " . $message;
    if ($data !== null) {
        $logMessage .= " | " . (is_array($data) ? json_encode($data) : $data);
    }
    $logMessage .= "\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}