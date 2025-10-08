<?php
/**
 * Mobile App Settings Controller
 * Handles CRUD operations for mobile app configuration
 */

require_once __DIR__ . '/../helpers/database.php';

class MobileAppSettingsController {
    private $db;
    
    public function __construct() {
        $this->db = get_db_connection();
    }
    
    /**
     * Get mobile app configuration with remote sync
     */
    public function getMobileAppConfig() {
        try {
            // First, try to get data from remote server
            $remoteData = $this->getRemoteData();
            
            if ($remoteData['success']) {
                // Remote data available, update local database
                $this->updateLocalFromRemote($remoteData['data']);
                
                // Get updated local data
                $stmt = $this->db->prepare("SELECT * FROM mobile_app_config WHERE sr = 1 LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'data' => $result,
                    'source' => 'remote',
                    'sync_status' => 'synced'
                ];
            } else {
                // Remote data not available, use local data
                $stmt = $this->db->prepare("SELECT * FROM mobile_app_config WHERE sr = 1 LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    // Create default configuration if none exists
                    $this->createDefaultConfig();
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                return [
                    'success' => true,
                    'data' => $result,
                    'source' => 'local',
                    'sync_status' => 'offline',
                    'sync_message' => $remoteData['message'] ?? 'Remote server unavailable'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Mobile app config error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve mobile app configuration',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get remote data from Client API
     */
    private function getRemoteData() {
        try {
            require_once __DIR__ . '/../helpers/client_api_helper.php';
            $clientApi = new ClientAPIHelper();
            
            return $clientApi->getMobileAppData();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote data fetch failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update local database from remote data
     */
    private function updateLocalFromRemote($remoteData) {
        try {
            // First, delete any existing entries to ensure single entry
            $deleteStmt = $this->db->prepare("DELETE FROM mobile_app_config");
            $deleteStmt->execute();
            
            // Insert the remote data as single entry
            $stmt = $this->db->prepare("
                INSERT INTO mobile_app_config (sr, Clientid, APIKey, App_logo_url) 
                VALUES (1, :clientid, :apikey, :logo_url)
            ");
            $stmt->execute([
                'clientid' => $remoteData['Clientid'] ?? 'default_client',
                'apikey' => $remoteData['APIKey'] ?? 'default_api_key',
                'logo_url' => $remoteData['App_logo_url'] ?? 'assets/images/mobile-app-logo.png'
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating local from remote: " . $e->getMessage());
        }
    }

    /**
     * Sync local data with remote server
     */
    private function syncWithRemote($data) {
        try {
            require_once __DIR__ . '/../helpers/client_api_helper.php';
            $clientApi = new ClientAPIHelper();
            
            // Validate Client ID locally first
            $clientId = $data['Clientid'] ?? '';
            if (!empty($clientId)) {
                $validation = $clientApi->validateClientId($clientId);
                if (!$validation['valid']) {
                    return [
                        'success' => false,
                        'message' => implode('. ', $validation['errors']),
                        'validation_error' => true,
                        'field' => 'Clientid',
                        'criteria' => $validation['criteria']
                    ];
                }
            }
            
            // Prepare data for remote sync in the exact format expected by Client API
            $syncData = [
                'Clientid' => $data['Clientid'] ?? 'Mobile App Client',
                'App_logo_url' => $data['App_logo_url'] ?? 'assets/images/mobile-app-logo.png'
            ];
            
            $result = $clientApi->syncWithRemote($syncData);
            
            // Handle validation errors from remote server
            if (!$result['success'] && isset($result['validation_error']) && $result['validation_error']) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'validation_error' => true,
                    'field' => $result['field'] ?? 'Clientid',
                    'suggestion' => $result['suggestion'] ?? null,
                    'criteria' => $result['criteria'] ?? null
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update mobile app configuration
     */
    public function updateMobileAppConfig($data) {
        try {
            // Validate required fields
            if (empty($data['Clientid']) || empty($data['APIKey'])) {
                return [
                    'success' => false,
                    'message' => 'Client ID and API Key are required'
                ];
            }
            
            // Handle logo URL based on mode
            $logoUrl = $this->handleLogoUpload($data);
            if (!$logoUrl) {
                return [
                    'success' => false,
                    'message' => 'Logo is required. Please upload an image or provide a URL.'
                ];
            }
            
            // Get existing API key to preserve it (API key is readonly)
            $existingStmt = $this->db->prepare("SELECT APIKey FROM mobile_app_config WHERE sr = 1 LIMIT 1");
            $existingStmt->execute();
            $existingRecord = $existingStmt->fetch(PDO::FETCH_ASSOC);
            $existingApiKey = $existingRecord ? $existingRecord['APIKey'] : $data['APIKey'];
            
            // First, delete any existing entries to ensure single entry
            $deleteStmt = $this->db->prepare("DELETE FROM mobile_app_config");
            $deleteStmt->execute();
            
            // Then insert the single entry with sr = 1
            $stmt = $this->db->prepare("
                INSERT INTO mobile_app_config (sr, Clientid, APIKey, App_logo_url) 
                VALUES (1, :clientid, :apikey, :logo_url)
            ");
            $stmt->execute([
                'clientid' => $data['Clientid'],
                'apikey' => $existingApiKey, // Use existing API key
                'logo_url' => $logoUrl
            ]);
            
            // Sync with remote server
            $syncResult = $this->syncWithRemote([
                'Clientid' => $data['Clientid'],
                'APIKey' => $existingApiKey,
                'App_logo_url' => $logoUrl
            ]);
            
            // Check if sync failed due to validation error
            if (!$syncResult['success'] && isset($syncResult['validation_error']) && $syncResult['validation_error']) {
                return [
                    'success' => false,
                    'message' => $syncResult['message'],
                    'validation_error' => true,
                    'field' => $syncResult['field'] ?? 'Clientid',
                    'suggestion' => $syncResult['suggestion'] ?? null,
                    'criteria' => $syncResult['criteria'] ?? null
                ];
            }
            
            $message = 'Mobile app configuration updated successfully';
            if ($syncResult['success']) {
                $message .= ' and synced with remote server';
            } else {
                $message .= ' (local only - remote sync failed: ' . $syncResult['message'] . ')';
            }
            
            return [
                'success' => true,
                'message' => $message,
                'logo_url' => $logoUrl,
                'sync_status' => $syncResult['success'] ? 'synced' : 'local_only',
                'sync_message' => $syncResult['message'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Mobile app config update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update mobile app configuration',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle logo upload or URL
     */
    private function handleLogoUpload($data) {
        $logoMode = $data['logo_mode'] ?? 'url';
        
        if ($logoMode === 'upload') {
            // Handle file upload
            error_log("Logo mode is upload, checking for file...");
            if (isset($_FILES['logo_upload'])) {
                $file = $_FILES['logo_upload'];
                error_log("File found: " . print_r($file, true));
                
                // Check for upload errors
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                        UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
                    throw new Exception('Upload failed: ' . $errorMsg);
                }
                
                $uploadDir = __DIR__ . '/../uploads/mobile-logos/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create upload directory.');
                    }
                }
                
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                // Validate file type
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception('Invalid file type. Please upload PNG, JPG, GIF, or SVG. Got: ' . $file['type']);
                }
                
                // Validate file size
                if ($file['size'] > $maxSize) {
                    throw new Exception('File too large. Maximum size is 5MB. Got: ' . round($file['size'] / 1024 / 1024, 2) . 'MB');
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'mobile_logo_' . uniqid() . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                // Move uploaded file
                error_log("Attempting to move file from " . $file['tmp_name'] . " to " . $filepath);
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Construct the complete URL using the correct path
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? '192.168.0.181';
                    
                    // Get base URL from configuration
                    $config = require __DIR__ . '/../config.php';
                    $baseUrl = $config['base_url'] ?? '';
                    
                    // Extract path from base URL (remove protocol and host)
                    $basePath = '';
                    if ($baseUrl) {
                        $parsedUrl = parse_url($baseUrl);
                        $basePath = $parsedUrl['path'] ?? '';
                        // Remove trailing slash if present
                        $basePath = rtrim($basePath, '/');
                    }
                    
                    // For local development, detect if we're in a subdirectory
                    if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '192.168.') === 0) {
                        // Local development - check if we're in a subdirectory
                        $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                        if (strpos($scriptPath, '/project/test') !== false) {
                            $basePath = '/project/test';
                        }
                    }
                    
                    // Use relative path for web URL
                    $webPath = 'uploads/mobile-logos/' . $filename;
                    $fullUrl = $protocol . '://' . $host . $basePath . '/' . $webPath;
                    
                    // Log successful upload
                    error_log("Mobile logo uploaded successfully: " . $fullUrl);
                    error_log("File exists at destination: " . (file_exists($filepath) ? 'YES' : 'NO'));
                    
                    return $fullUrl;
                } else {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Unknown error';
                    error_log("Failed to move uploaded file. Error: " . $errorMsg);
                    throw new Exception('Failed to move uploaded file to destination.');
                }
            } else {
                throw new Exception('No file uploaded.');
            }
        } else {
            // Handle URL
            $logoUrl = $data['App_logo_url'] ?? '';
            if (empty($logoUrl)) {
                throw new Exception('Logo URL is required.');
            }
            
            // Validate URL format
            if (!filter_var($logoUrl, FILTER_VALIDATE_URL) && !preg_match('/^assets\/.*\.(png|jpg|jpeg|gif|svg)$/i', $logoUrl)) {
                throw new Exception('Invalid logo URL format.');
            }
            
            return $logoUrl;
        }
    }
    
    /**
     * Create default mobile app configuration
     */
    private function createDefaultConfig() {
        try {
            // First, delete any existing entries to ensure single entry
            $deleteStmt = $this->db->prepare("DELETE FROM mobile_app_config");
            $deleteStmt->execute();
            
            // Then insert the single entry with sr = 1
            $stmt = $this->db->prepare("
                INSERT INTO mobile_app_config (sr, Clientid, APIKey, App_logo_url) 
                VALUES (1, 'default_client', 'default_api_key', 'assets/images/mobile-app-logo.png')
            ");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creating default mobile app config: " . $e->getMessage());
        }
    }
    
    /**
     * Validate mobile app configuration data
     */
    public function validateConfigData($data) {
        $errors = [];
        
        if (empty($data['Clientid'])) {
            $errors[] = 'Client ID is required';
        }
        
        if (empty($data['APIKey'])) {
            $errors[] = 'API Key is required';
        }
        
        // Validate logo based on mode
        $logoMode = $data['logo_mode'] ?? 'url';
        
        if ($logoMode === 'upload') {
            if (!isset($_FILES['logo_upload']) || $_FILES['logo_upload']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Please upload a logo image';
            }
        } else {
            if (empty($data['App_logo_url'])) {
                $errors[] = 'App Logo URL is required';
            } else if (!filter_var($data['App_logo_url'], FILTER_VALIDATE_URL) && !preg_match('/^assets\/.*\.(png|jpg|jpeg|gif|svg)$/i', $data['App_logo_url'])) {
                $errors[] = 'App Logo URL must be a valid URL or a valid assets path';
            }
        }
        
        return $errors;
    }
}

// Handle AJAX requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $controller = new MobileAppSettingsController();
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($_POST['action']) {
        case 'get_config':
            $response = $controller->getMobileAppConfig();
            break;
            
        case 'update_config':
            // Debug logging
            error_log("Mobile app config update request:");
            error_log("POST data: " . print_r($_POST, true));
            error_log("FILES data: " . print_r($_FILES, true));
            
            // Additional debugging
            error_log("Upload directory exists: " . (is_dir('uploads/mobile-logos/') ? 'YES' : 'NO'));
            error_log("Upload directory writable: " . (is_writable('uploads/mobile-logos/') ? 'YES' : 'NO'));
            
            $data = [
                'Clientid' => $_POST['Clientid'] ?? '',
                'APIKey' => $_POST['APIKey'] ?? '',
                'App_logo_url' => $_POST['App_logo_url'] ?? '',
                'logo_mode' => $_POST['logo_mode'] ?? 'url'
            ];
            
            // Validate data
            $errors = $controller->validateConfigData($data);
            if (!empty($errors)) {
                $response = [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ];
            } else {
                $response = $controller->updateMobileAppConfig($data);
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    echo json_encode($response);
    exit;
}
?>
