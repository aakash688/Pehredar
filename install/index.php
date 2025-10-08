<?php
/**
 * Enhanced Application Installer
 * This file handles the installation process with step-by-step wizard
 */

// Check if already installed
$configFile = dirname(__DIR__) . '/config-local.php';
if (file_exists($configFile)) {
    $installed = true;
    $existingConfig = include($configFile);
} else {
    $installed = false;
    $existingConfig = null;
}

// Get current step from URL parameter
$currentStep = $_GET['step'] ?? 1;
$currentStep = max(1, min(4, (int)$currentStep)); // Ensure step is between 1-4

$installSuccess = false;
$loginUrl = '';
$adminEmail = 'admin@yantralogic.com';
$adminPassword = '';

// System Requirements Check
function checkSystemRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version',
            'required' => '7.4.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'description' => 'PHP 7.4 or higher is required'
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL Extension',
            'required' => 'Installed',
            'current' => extension_loaded('pdo_mysql') ? 'Installed' : 'Not Installed',
            'status' => extension_loaded('pdo_mysql'),
            'description' => 'Required for database connectivity'
        ],
        'json' => [
            'name' => 'JSON Extension',
            'required' => 'Installed',
            'current' => extension_loaded('json') ? 'Installed' : 'Not Installed',
            'status' => extension_loaded('json'),
            'description' => 'Required for API responses'
        ],
        'curl' => [
            'name' => 'cURL Extension',
            'required' => 'Installed',
            'current' => extension_loaded('curl') ? 'Installed' : 'Not Installed',
            'status' => extension_loaded('curl'),
            'description' => 'Required for external API calls'
        ],
        'file_permissions' => [
            'name' => 'Directory Permissions',
            'required' => 'Writable',
            'current' => is_writable(dirname(__DIR__)) ? 'Writable' : 'Not Writable',
            'status' => is_writable(dirname(__DIR__)),
            'description' => 'Application directory must be writable'
        ],
        'sql_file' => [
            'name' => 'SQL Installation File',
            'required' => 'Present',
            'current' => file_exists(dirname(__DIR__) . '/Final.sql') ? 'Found' : 'Not Found',
            'status' => file_exists(dirname(__DIR__) . '/Final.sql'),
            'description' => 'Database schema file required for installation'
        ]
    ];
    
    $allPassed = true;
    foreach ($requirements as $key => $req) {
        if (!$req['status']) {
            $allPassed = false;
            break;
        }
    }
    
    return [
        'requirements' => $requirements,
        'all_passed' => $allPassed,
        'passed_count' => count(array_filter($requirements, function($req) { return $req['status']; })),
        'total_count' => count($requirements)
    ];
}

// Database Connection Test
function testDatabaseConnection($host, $port, $dbname, $user, $pass) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => 'Database connection successful',
            'pdo' => $pdo
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage(),
            'pdo' => null
        ];
    }
}

// Handle step-by-step processing
$systemCheck = null;
$dbTest = null;
$installationProgress = 0;
$installationLog = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_system') {
        // Step 1: System Requirements Check
        $systemCheck = checkSystemRequirements();
        if ($systemCheck['all_passed']) {
            $currentStep = 2;
        }
    } elseif ($action === 'test_database') {
        // Step 2: Database Connection Test
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        
        $dbTest = testDatabaseConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if ($dbTest['success']) {
            $currentStep = 3;
            // Store database credentials in session for installation
            session_start();
            $_SESSION['install_db'] = [
                'host' => $dbHost,
                'port' => $dbPort,
                'dbname' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass
            ];
        }
    } elseif ($action === 'install') {
        // Step 3: Installation Process
        session_start();
        $dbConfig = $_SESSION['install_db'] ?? null;
        
        if (!$dbConfig) {
            $errors[] = "Database configuration not found. Please go back to step 2.";
        } else {
            $errors = [];
            $installationLog = [];
            
            // Get additional configuration
            $baseUrl = trim($_POST['base_url'] ?? '');
            $jwtSecret = trim($_POST['jwt_secret'] ?? '');
            $adminPassword = trim($_POST['admin_password'] ?? '');
            
            // Validation
            if (empty($baseUrl)) $errors[] = "Base URL is required";
            if (empty($jwtSecret)) $errors[] = "JWT Secret is required";
            if (empty($adminPassword)) $errors[] = "Admin password is required";
            if (!empty($adminPassword) && strlen($adminPassword) < 6) {
                $errors[] = "Admin password must be at least 6 characters";
            }
            
            if (empty($errors)) {
                // Execute installation
                $installationLog[] = "Starting installation process...";
                $installationProgress = 10;
                
                // Test database connection again
                $dbTest = testDatabaseConnection($dbConfig['host'], $dbConfig['port'], $dbConfig['dbname'], $dbConfig['user'], $dbConfig['pass']);
                if (!$dbTest['success']) {
                    $errors[] = $dbTest['message'];
                } else {
                    $pdo = $dbTest['pdo'];
                    $installationProgress = 30;
                    $installationLog[] = "✓ Database connection verified";
                    
                    // Execute SQL file
                    try {
                        $sqlFile = dirname(__DIR__) . '/Final.sql';
                        if (!file_exists($sqlFile)) {
                            $errors[] = "SQL file 'Final.sql' not found";
                        } else {
                            $installationLog[] = "✓ SQL file found";
                            $installationProgress = 50;
                            
                            // Read and execute SQL
                            $sqlContent = file_get_contents($sqlFile);
                            $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
                            $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
                            
                            $statements = [];
                            $currentStatement = '';
                            $lines = explode("\n", $sqlContent);
                            
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) continue;
                                
                                $currentStatement .= $line . "\n";
                                
                                if (substr($line, -1) === ';') {
                                    $stmt = trim($currentStatement);
                                    if (!empty($stmt) && $stmt !== ';') {
                                        $statements[] = $stmt;
                                    }
                                    $currentStatement = '';
                                }
                            }
                            
                            $successCount = 0;
                            $errorCount = 0;
                            
                            foreach ($statements as $statement) {
                                $statement = trim($statement);
                                if (empty($statement)) continue;
                                
                                if (preg_match('/^(SET|START TRANSACTION|COMMIT)/i', $statement)) {
                                    try {
                                        $pdo->exec($statement);
                                    } catch (PDOException $e) {
                                        // Ignore errors for these commands
                                    }
                                    continue;
                                }
                                
                                try {
                                    $pdo->exec($statement);
                                    $successCount++;
                                } catch (PDOException $e) {
                                    $errorCount++;
                                    if (strpos($e->getMessage(), 'already exists') === false) {
                                        $installationLog[] = "⚠ Warning: " . substr($e->getMessage(), 0, 100);
                                    }
                                }
                            }
                            
                            $installationProgress = 70;
                            $installationLog[] = "✓ Database tables created successfully ($successCount operations)";
                            if ($errorCount > 0) {
                                $installationLog[] = "⚠ $errorCount warnings (possibly duplicate tables)";
                            }
                            
                            // Update admin password
                            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = 1");
                            $updateStmt->execute(['password' => $hashedPassword]);
                            $installationLog[] = "✓ Admin password set successfully";
                            $installationProgress = 85;
                            
                            // Load default configuration template to get URLs
                            $defaultConfig = include dirname(__DIR__) . '/config.php';
                            
                            // Create config file
                            $configContent = "<?php
// Auto-generated configuration file
// Created on: " . date('Y-m-d H:i:s') . "

return [
    'installed' => true,
    'base_url' => " . var_export($baseUrl, true) . ",
    
    'db' => [
        'host' => " . var_export($dbConfig['host'], true) . ",
        'port' => " . var_export((int)$dbConfig['port'], true) . ",
        'dbname' => " . var_export($dbConfig['dbname'], true) . ",
        'user' => " . var_export($dbConfig['user'], true) . ",
        'pass' => " . var_export($dbConfig['pass'], true) . "
    ],
    
    'db_fallbacks' => [],
    
    'connection' => [
        'timeout' => 10,
        'retry_attempts' => 3,
        'retry_delay' => 2,
        'enable_fallbacks' => true,
        'cache_connection_test' => 300
    ],
    
    'jwt' => [
        'secret' => " . var_export($jwtSecret, true) . ",
        'expires_in_hours' => 24
    ],
    
    'admin_panel' => [
        'notification_url' => " . var_export($defaultConfig['admin_panel']['notification_url'], true) . ",
        'enabled' => true,
        'timeout' => 30
    ],
    
    'client_api' => [
        'remote_clientapi_endpoint_url' => " . var_export($defaultConfig['client_api']['remote_clientapi_endpoint_url'], true) . ",
        'enabled' => true,
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 2
    ]
];
";
                            
                            if (file_put_contents($configFile, $configContent)) {
                                $installSuccess = true;
                                $loginUrl = $baseUrl;
                                // $adminPassword is already set from form input
                                $installationProgress = 100;
                                $installationLog[] = "✓ Configuration file created";
                                $installationLog[] = "✓ Installation completed successfully!";
                                $currentStep = 4;
                                
                                // Send installation notification to admin panel
                                $notificationResult = sendInstallationNotification($baseUrl, $dbConfig, $adminPassword);
                                if ($notificationResult['success']) {
                                    $installationLog[] = "✓ Installation notification sent to admin panel";
                                    
                                    // Save admin panel response to installation_data table
                                    if (isset($notificationResult['response']) && $notificationResult['response']) {
                                        $responseData = $notificationResult['response'];
                                        $saveResult = saveInstallationData($pdo, $responseData, $baseUrl, $dbConfig, $adminPassword);
                                        if ($saveResult['success']) {
                                            $installationLog[] = "✓ Installation data saved to database (" . $saveResult['action'] . ")";
                                            
                                            // Show key data from response
                                            if (isset($responseData['client_id'])) {
                                                $installationLog[] = "✓ Client ID: " . $responseData['client_id'];
                                            }
                                            if (isset($responseData['installation_id'])) {
                                                $installationLog[] = "✓ Installation ID: " . $responseData['installation_id'];
                                            }
                                            if (isset($responseData['api_key'])) {
                                                $installationLog[] = "✓ API Key: " . $responseData['api_key'];
                                            }
                                            if (isset($responseData['api_secret'])) {
                                                $installationLog[] = "✓ API Secret: " . substr($responseData['api_secret'], 0, 20) . "...";
                                            }
                                            if (isset($responseData['action'])) {
                                                $installationLog[] = "✓ Action: " . $responseData['action'];
                                            }
                                            if (isset($responseData['status'])) {
                                                $installationLog[] = "✓ Status: " . $responseData['status'];
                                            }
                                            
                                            // Show preserved data for reinstallations
                                            if (isset($responseData['preserved_data']) && $responseData['preserved_data']) {
                                                $installationLog[] = "✓ Preserved existing client data";
                                            }
                                            
                                            // Show updated fields for reinstallations
                                            if (isset($responseData['updated_fields']) && $responseData['updated_fields']) {
                                                $updatedCount = count($responseData['updated_fields']);
                                                $installationLog[] = "✓ Updated {$updatedCount} field(s)";
                                            }
                                        } else {
                                            $installationLog[] = "⚠ Warning: Failed to save installation data: " . $saveResult['message'];
                                        }
                                    }
                                } else {
                                    $installationLog[] = "⚠ Warning: " . $notificationResult['message'];
                                }
                                
                                // Clear session data
                                unset($_SESSION['install_db']);
                            } else {
                                $errors[] = "Failed to write configuration file. Please check file permissions.";
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = "Database setup failed: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// If no specific action, run system check for step 1
if ($currentStep === 1 && !$systemCheck) {
    $systemCheck = checkSystemRequirements();
}

// Generate a random JWT secret if not provided
$defaultJwtSecret = bin2hex(random_bytes(32));

/**
 * Save installation data to database
 * Handles both fresh installation and reinstallation scenarios
 * Ensures single entry in installation_data table
 */
function saveInstallationData($pdo, $responseData, $baseUrl, $dbConfig, $adminPassword) {
    try {
        // First, check if there's already an entry
        $checkStmt = $pdo->query("SELECT id FROM installation_data LIMIT 1");
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare data for insertion/update
        $data = [
            'client_id' => $responseData['client_id'] ?? null,
            'installation_id' => $responseData['installation_id'] ?? null,
            'api_key' => $responseData['api_key'] ?? null,
            'api_secret' => $responseData['api_secret'] ?? null,
            'action' => $responseData['action'] ?? null,
            'base_url' => $baseUrl,
            'db_name' => $dbConfig['dbname'],
            'db_user' => $dbConfig['user'],
            'db_pass' => $dbConfig['pass'],
            'admin_password' => $adminPassword,
            'installation_date' => date('Y-m-d H:i:s'),
            'status' => $responseData['status'] ?? 'active',
            'preserved_data' => isset($responseData['preserved_data']) ? json_encode($responseData['preserved_data']) : null,
            'updated_fields' => isset($responseData['updated_fields']) ? json_encode($responseData['updated_fields']) : null,
            'next_steps' => isset($responseData['next_steps']) ? json_encode($responseData['next_steps']) : null
        ];
        
        if ($existingRecord) {
            // Update existing record
            $sql = "UPDATE installation_data SET 
                client_id = :client_id,
                installation_id = :installation_id,
                api_key = :api_key,
                api_secret = :api_secret,
                action = :action,
                base_url = :base_url,
                db_name = :db_name,
                db_user = :db_user,
                db_pass = :db_pass,
                admin_password = :admin_password,
                installation_date = :installation_date,
                status = :status,
                preserved_data = :preserved_data,
                updated_fields = :updated_fields,
                next_steps = :next_steps,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
            
            $data['id'] = $existingRecord['id'];
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($data);
            
            $action = "updated existing record";
        } else {
            // Insert new record
            $sql = "INSERT INTO installation_data (
                client_id, installation_id, api_key, api_secret, action, 
                base_url, db_name, db_user, db_pass, admin_password, 
                installation_date, status, preserved_data, updated_fields, next_steps
            ) VALUES (
                :client_id, :installation_id, :api_key, :api_secret, :action,
                :base_url, :db_name, :db_user, :db_pass, :admin_password,
                :installation_date, :status, :preserved_data, :updated_fields, :next_steps
            )";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($data);
            
            $action = "created new record";
        }
        
        if ($result) {
            // Also save API key to mobile_app_config table (single entry system)
            if (isset($responseData['api_key']) && !empty($responseData['api_key'])) {
                $mobileConfigResult = saveMobileAppConfig($pdo, $responseData['api_key'], $responseData['client_id'] ?? 'default_client');
                if (!$mobileConfigResult['success']) {
                    error_log("Warning: Failed to save mobile app config: " . $mobileConfigResult['message']);
                }
            }
            
            return [
                'success' => true,
                'message' => "Installation data saved successfully ({$action})",
                'action' => $action
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save installation data'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Save mobile app configuration (single entry system)
 */
function saveMobileAppConfig($pdo, $apiKey, $clientId) {
    try {
        // First, delete any existing entries to ensure single entry
        $deleteStmt = $pdo->prepare("DELETE FROM mobile_app_config");
        $deleteStmt->execute();
        
        // Then insert the single entry with sr = 1
        $sql = "INSERT INTO mobile_app_config (sr, Clientid, APIKey, App_logo_url) 
                VALUES (1, :Clientid, :APIKey, :App_logo_url)";
        
        $data = [
            'Clientid' => $clientId,
            'APIKey' => $apiKey,
            'App_logo_url' => 'assets/images/mobile-app-logo.png' // Default logo
        ];
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($data);
        
        $action = "created single entry mobile app config";
        
        if ($result) {
            return [
                'success' => true,
                'message' => "Mobile app config saved successfully ({$action})",
                'action' => $action
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save mobile app config'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Mobile app config error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send installation notification to admin panel
 */
function sendInstallationNotification($baseUrl, $dbConfig, $adminPassword) {
    // Load admin panel configuration helper
    require_once dirname(__DIR__) . '/helpers/admin_panel_config.php';
    
    // Check if notifications are enabled
    if (!isAdminPanelEnabled()) {
        return [
            'success' => true,
            'message' => 'Notifications disabled',
            'response' => null
        ];
    }
    
    // Get notification URL and timeout from config
    $notificationUrl = getAdminPanelNotificationUrl();
    $timeout = getAdminPanelTimeout();
    
    $data = [
        'base_url' => $baseUrl,
        'Db_name' => $dbConfig['dbname'],
        'Db_user' => $dbConfig['user'],
        'Db_PAss' => $dbConfig['pass'],
        'password' => $adminPassword,
        'installation_date' => date('c') // ISO 8601 format
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => $timeout
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        $result = file_get_contents($notificationUrl, false, $context);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to send notification to admin panel',
                'response' => null
            ];
        }
        
        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response from admin panel',
                'response' => null
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Notification sent successfully',
            'response' => $response
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Notification error: ' . $e->getMessage(),
            'response' => null
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Application Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        
        .step.pending .step-number {
            background: #e5e7eb;
            color: #6b7280;
        }
        
        .step-label {
            font-weight: 500;
            color: #374151;
        }
        
        .step.active .step-label {
            color: #667eea;
        }
        
        .step.completed .step-label {
            color: #10b981;
        }
        
        .step-connector {
            width: 40px;
            height: 2px;
            background: #e5e7eb;
            margin: 0 10px;
        }
        
        .step.completed + .step .step-connector {
            background: #10b981;
        }
        
        .step-content {
            min-height: 400px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 4px;
            transition: width 0.5s ease;
            width: 0%;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .requirement-item.pass {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .requirement-item.fail {
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .requirement-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        
        .requirement-details {
            flex: 1;
        }
        
        .requirement-name {
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .requirement-status {
            font-size: 14px;
            color: #6b7280;
        }
        
        .requirement-description {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6b7280;
            font-size: 12px;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-large {
            padding: 16px 40px;
            font-size: 16px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .error-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            color: #dc2626;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .error-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .error-box li {
            margin-bottom: 5px;
        }
        
        .success-box {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-box .icon {
            font-size: 80px;
            color: #10b981;
            margin-bottom: 20px;
            animation: bounce 1s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .success-box h2 {
            color: #10b981;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .success-box p {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .credentials-box {
            background: #f9fafb;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .credentials-box h3 {
            color: #374151;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
        }
        
        .credential-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
        }
        
        .credential-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 14px;
        }
        
        .credential-value {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            color: #111827;
            word-break: break-all;
        }
        
        .installation-log {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .installation-log h4 {
            color: #374151;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .log-item {
            font-size: 13px;
            color: #6b7280;
            padding: 4px 0;
            font-family: 'Courier New', monospace;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .already-installed {
            text-align: center;
            padding: 40px 20px;
        }
        
        .already-installed .icon {
            font-size: 80px;
            color: #10b981;
            margin-bottom: 20px;
        }
        
        .already-installed h2 {
            color: #10b981;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .already-installed p {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .warning-text {
            color: #f59e0b;
            font-size: 13px;
            background: #fef3c7;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #f59e0b;
        }
        
        .section-title {
            color: #374151;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: #667eea;
            margin-right: 12px;
            border-radius: 2px;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #374151;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Enhanced Application Installation</h1>
            <p>Welcome! Let's set up your application with our step-by-step wizard.</p>
        </div>
        
        <div class="content">
            <?php if ($installSuccess): ?>
                <!-- Step 4: Installation Complete -->
                <div class="step-indicator">
                    <div class="step completed">
                        <div class="step-number">1</div>
                        <div class="step-label">System Check</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step completed">
                        <div class="step-number">2</div>
                        <div class="step-label">Database</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step completed">
                        <div class="step-number">3</div>
                        <div class="step-label">Installation</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step completed">
                        <div class="step-number">4</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
                
                <div class="step-content">
                    <div class="success-box fade-in">
                        <div class="icon">✅</div>
                        <h2>Installation Successful!</h2>
                        <p>Your application has been installed and configured successfully.</p>
                        
                        <?php if (!empty($installationLog)): ?>
                            <div class="installation-log">
                                <h4>📋 Installation Log:</h4>
                                <?php foreach ($installationLog as $logEntry): ?>
                                    <div class="log-item"><?php echo htmlspecialchars($logEntry); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="credentials-box">
                            <h3>🔑 Your Login Credentials</h3>
                            <div class="credential-item">
                                <span class="credential-label">Login URL:</span>
                                <span class="credential-value"><?php echo htmlspecialchars($loginUrl); ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Email/Username:</span>
                                <span class="credential-value"><?php echo htmlspecialchars($adminEmail); ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Password:</span>
                                <span class="credential-value"><?php echo htmlspecialchars($adminPassword); ?></span>
                            </div>
                            <div class="warning-text">
                                <strong>⚠️ Important:</strong> Please save these credentials securely. You can change your password after logging in.
                            </div>
                        </div>
                        
                        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn btn-large btn-success">Go to Login Page</a>
                    </div>
                </div>
                
            <?php elseif ($installed): ?>
                <!-- Already Installed -->
                <div class="already-installed">
                    <div class="icon">✅</div>
                    <h2>Already Installed!</h2>
                    <p>This application has already been installed and configured.</p>
                    <a href="<?php echo htmlspecialchars($existingConfig['base_url']); ?>" class="btn btn-large">Go to Application</a>
                </div>
                
            <?php else: ?>
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $currentStep >= 1 ? ($currentStep == 1 ? 'active' : 'completed') : 'pending'; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">System Check</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step <?php echo $currentStep >= 2 ? ($currentStep == 2 ? 'active' : 'completed') : 'pending'; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Database</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step <?php echo $currentStep >= 3 ? ($currentStep == 3 ? 'active' : 'completed') : 'pending'; ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">Installation</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step <?php echo $currentStep >= 4 ? 'completed' : 'pending'; ?>">
                        <div class="step-number">4</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
                
                <div class="step-content">
                    <?php if ($currentStep == 1): ?>
                        <!-- Step 1: System Requirements Check -->
                        <div class="fade-in">
                            <div class="section-title">🔍 System Requirements Check</div>
                            <p style="margin-bottom: 30px; color: #6b7280;">We'll verify that your server meets all necessary requirements for the application.</p>
                            
                            <?php if ($systemCheck): ?>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($systemCheck['passed_count'] / $systemCheck['total_count']) * 100; ?>%"></div>
                                </div>
                                <p style="text-align: center; margin: 10px 0; color: #6b7280;">
                                    <?php echo $systemCheck['passed_count']; ?> of <?php echo $systemCheck['total_count']; ?> requirements met
                                </p>
                                
                                <?php foreach ($systemCheck['requirements'] as $key => $req): ?>
                                    <div class="requirement-item <?php echo $req['status'] ? 'pass' : 'fail'; ?>">
                                        <div class="requirement-icon">
                                            <?php echo $req['status'] ? '✅' : '❌'; ?>
                                        </div>
                                        <div class="requirement-details">
                                            <div class="requirement-name"><?php echo htmlspecialchars($req['name']); ?></div>
                                            <div class="requirement-status">
                                                Current: <?php echo htmlspecialchars($req['current']); ?> | 
                                                Required: <?php echo htmlspecialchars($req['required']); ?>
                                            </div>
                                            <div class="requirement-description"><?php echo htmlspecialchars($req['description']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($systemCheck['all_passed']): ?>
                                    <div style="text-align: center; margin-top: 30px;">
                                        <p style="color: #10b981; font-weight: 600; margin-bottom: 20px;">🎉 All system requirements are met!</p>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="check_system">
                                            <button type="submit" class="btn btn-large">Continue to Database Setup →</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align: center; margin-top: 30px;">
                                        <p style="color: #dc2626; font-weight: 600; margin-bottom: 20px;">❌ Please fix the requirements above before continuing.</p>
                                        <button type="button" class="btn btn-secondary" onclick="location.reload()">Refresh Check</button>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="text-align: center;">
                                    <div class="pulse" style="font-size: 48px; margin: 40px 0;">🔍</div>
                                    <p style="margin-bottom: 30px; color: #6b7280;">Checking system requirements...</p>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="check_system">
                                        <button type="submit" class="btn btn-large">Run System Check</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($currentStep == 2): ?>
                        <!-- Step 2: Database Connection Test -->
                        <div class="fade-in">
                            <div class="section-title">🗄️ Database Connection Setup</div>
                            <p style="margin-bottom: 30px; color: #6b7280;">Enter your database credentials to test the connection.</p>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="error-box">
                                    <strong>⚠️ Please fix the following errors:</strong>
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($dbTest): ?>
                                <div class="requirement-item <?php echo $dbTest['success'] ? 'pass' : 'fail'; ?>">
                                    <div class="requirement-icon">
                                        <?php echo $dbTest['success'] ? '✅' : '❌'; ?>
                                    </div>
                                    <div class="requirement-details">
                                        <div class="requirement-name">Database Connection Test</div>
                                        <div class="requirement-status"><?php echo htmlspecialchars($dbTest['message']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($dbTest['success']): ?>
                                    <div style="text-align: center; margin-top: 30px;">
                                        <p style="color: #10b981; font-weight: 600; margin-bottom: 20px;">🎉 Database connection successful!</p>
                                        <a href="?step=3" class="btn btn-large">Continue to Installation →</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="test_database">
                                
                                <div class="form-group">
                                    <label for="db_host">Database Host *</label>
                                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                                    <small>Usually "localhost" or your server's IP address</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_port">Database Port</label>
                                    <input type="number" id="db_port" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>">
                                    <small>Default MySQL port is 3306</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_name">Database Name *</label>
                                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required>
                                    <small>The name of your MySQL database (must be created beforehand)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_user">Database Username *</label>
                                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
                                    <small>Your MySQL username</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_pass">Database Password</label>
                                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                                    <small>Your MySQL password (leave empty if no password)</small>
                                </div>
                                
                                <div style="text-align: center;">
                                    <button type="submit" class="btn btn-large">Test Database Connection</button>
                                </div>
                            </form>
                        </div>
                        
                    <?php elseif ($currentStep == 3): ?>
                        <!-- Step 3: Installation Process -->
                        <div class="fade-in">
                            <div class="section-title">⚙️ Application Installation</div>
                            <p style="margin-bottom: 30px; color: #6b7280;">Configure your application settings and begin the installation process.</p>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="error-box">
                                    <strong>⚠️ Please fix the following errors:</strong>
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($installationProgress > 0): ?>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $installationProgress; ?>%"></div>
                                </div>
                                <p style="text-align: center; margin: 10px 0; color: #6b7280;">
                                    Installation Progress: <?php echo $installationProgress; ?>%
                                </p>
                                
                                <?php if (!empty($installationLog)): ?>
                                    <div class="installation-log">
                                        <h4>📋 Installation Log:</h4>
                                        <?php foreach ($installationLog as $logEntry): ?>
                                            <div class="log-item"><?php echo htmlspecialchars($logEntry); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($installationProgress == 100): ?>
                                    <div style="text-align: center; margin-top: 30px;">
                                        <p style="color: #10b981; font-weight: 600; margin-bottom: 20px;">🎉 Installation completed successfully!</p>
                                        <a href="?step=4" class="btn btn-large btn-success">View Installation Summary →</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="install">
                                    
                                    <div class="form-group">
                                        <label for="base_url">Base URL *</label>
                                        <input type="url" id="base_url" name="base_url" value="<?php echo htmlspecialchars($_POST['base_url'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'))); ?>" required>
                                        <small>The URL where your application is accessible (e.g., https://yourdomain.com/app)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="jwt_secret">JWT Secret Key *</label>
                                        <input type="text" id="jwt_secret" name="jwt_secret" value="<?php echo htmlspecialchars($_POST['jwt_secret'] ?? $defaultJwtSecret); ?>" required>
                                        <small>Used for secure authentication tokens (auto-generated)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="admin_password">Admin Password *</label>
                                        <input type="password" id="admin_password" name="admin_password" value="" required minlength="6">
                                        <small>Set a strong password for the admin account (minimum 6 characters)</small>
                                        <div style="margin-top: 10px; padding: 10px; background: #e0f2fe; border-radius: 4px; font-size: 13px;">
                                            <strong>Admin Email:</strong> admin@yantralogic.com<br>
                                            <small>You'll use this email to login after installation</small>
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <button type="submit" class="btn btn-large">🚀 Start Installation</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Add some interactive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add tooltips to form fields
            const tooltips = document.querySelectorAll('.tooltip');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function() {
                    this.querySelector('.tooltiptext').style.visibility = 'visible';
                });
                tooltip.addEventListener('mouseleave', function() {
                    this.querySelector('.tooltiptext').style.visibility = 'hidden';
                });
            });
            
            // Auto-refresh progress if installation is running
            <?php if ($currentStep == 3 && $installationProgress > 0 && $installationProgress < 100): ?>
                setTimeout(function() {
                    location.reload();
                }, 2000);
            <?php endif; ?>
        });
    </script>
</body>
</html>