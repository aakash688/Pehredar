<?php
/**
 * License Control API
 * Real implementation that connects to the database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../helpers/license_manager.php';

// API Key validation functions
function getInstallationApiKey() {
    try {
        // Use the correct database from config
        require_once __DIR__ . '/../config.php';
        $config = require __DIR__ . '/../config.php';
        
        $dbConfig = $config['db'];
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
        
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get the single installation record (should always be id=1)
        $stmt = $pdo->query("SELECT api_key FROM installation_data WHERE id = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['api_key'])) {
            return $result['api_key'];
        }
        
        // Fallback: get any installation data if id=1 doesn't exist
        $stmt = $pdo->query("SELECT api_key FROM installation_data ORDER BY id ASC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['api_key'] : null;
    } catch (Exception $e) {
        error_log("Error getting installation API key: " . $e->getMessage());
        return null;
    }
}

function validateApiKey() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $installationApiKey = getInstallationApiKey();
        
        // Use installation API key if available, otherwise fallback to default
        if (!$installationApiKey) {
            $installationApiKey = 'YOUR_SECRET_API_KEY_HERE_2024'; // Fallback for testing
        }
        
        return $token === $installationApiKey;
    }
    
    return false;
}

// Validate API key
if (!validateApiKey()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Invalid or missing API Key',
        'code' => 401
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed',
                'code' => 405
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'code' => 500
    ]);
}

function handleGetRequest() {
    // Get pagination parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 10))); // Max 100 records per page
    $offset = ($page - 1) * $limit;
    
    // Get status filter
    $statusFilter = $_GET['status'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    $licenseManager = new LicenseManager();
    $status = $licenseManager->getLicenseStatus();
    
    // Get logs with pagination and filters
    $logs = $licenseManager->getLicenseLogs($limit, $offset, $statusFilter, $dateFrom, $dateTo);
    $totalLogs = $licenseManager->getLicenseLogsCount($statusFilter, $dateFrom, $dateTo);
    
    // Calculate pagination info
    $totalPages = ceil($totalLogs / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    echo json_encode([
        'success' => true,
        'license' => $status,
        'logs' => [
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_records' => $totalLogs,
                'total_pages' => $totalPages,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage,
                'next_page' => $hasNextPage ? $page + 1 : null,
                'prev_page' => $hasPrevPage ? $page - 1 : null
            ],
            'filters' => [
                'status' => $statusFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ],
        'timestamp' => date('c')
    ]);
}

function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input',
            'code' => 400
        ]);
        return;
    }
    
    $status = $input['status'] ?? null;
    $reason = $input['reason'] ?? '';
    $expiresAt = $input['expires_at'] ?? null;
    
    if (!$status) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Status is required',
            'code' => 400
        ]);
        return;
    }
    
    $validStatuses = ['active', 'suspended', 'expired'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses),
            'code' => 400
        ]);
        return;
    }
    
    $licenseManager = new LicenseManager();
    $result = $licenseManager->updateLicenseStatus($status, $expiresAt, $reason);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'License status updated successfully',
            'new_status' => $status,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update license status',
            'code' => 500
        ]);
    }
}

function handlePutRequest() {
    $licenseManager = new LicenseManager();
    $status = $licenseManager->getLicenseStatus();
    
    // Calculate days until expiry
    $daysUntilExpiry = null;
    if ($status['expires_at']) {
        $expiryTime = strtotime($status['expires_at']);
        $currentTime = time();
        $daysUntilExpiry = max(0, ceil(($expiryTime - $currentTime) / (24 * 60 * 60)));
    }
    
    $status['days_until_expiry'] = $daysUntilExpiry;
    
    echo json_encode([
        'success' => true,
        'message' => 'License validity checked',
        'license' => $status,
        'timestamp' => date('c')
    ]);
}

function handleDeleteRequest() {
    $olderThan = $_GET['older_than'] ?? null;
    $status = $_GET['status'] ?? null;
    $confirm = $_GET['confirm'] ?? null;
    
    if ($confirm !== 'true') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Confirmation required. Add ?confirm=true to the request',
            'code' => 400
        ]);
        return;
    }
    
    try {
        require_once __DIR__ . '/../helpers/database.php';
        $pdo = get_db_connection();
        
        $whereConditions = [];
        $params = [];
        
        // Add older_than filter
        if ($olderThan && is_numeric($olderThan)) {
            $whereConditions[] = "created_at < DATE_SUB(NOW(), INTERVAL :older_than DAY)";
            $params['older_than'] = intval($olderThan);
        }
        
        // Add status filter
        if ($status && in_array($status, ['active', 'suspended', 'expired'])) {
            $whereConditions[] = "status = :status";
            $params['status'] = $status;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Count records to be deleted
        $countSql = "SELECT COUNT(*) as total FROM license_logs {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $deletedCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Delete records
        $deleteSql = "DELETE FROM license_logs {$whereClause}";
        $deleteStmt = $pdo->prepare($deleteSql);
        foreach ($params as $key => $value) {
            $deleteStmt->bindValue(':' . $key, $value);
        }
        $deleteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'License logs cleared successfully',
            'deleted_count' => intval($deletedCount),
            'filters_applied' => [
                'older_than' => $olderThan,
                'status' => $status
            ],
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to clear logs: ' . $e->getMessage(),
            'code' => 500
        ]);
    }
}
?>