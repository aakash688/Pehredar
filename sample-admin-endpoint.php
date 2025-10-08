<?php
/**
 * Sample Admin Panel Installation Endpoint
 * This is a sample of what your admin panel endpoint should look like
 * Place this at: https://gadmin.yantralogic.com/apis/install-endpoint.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$requiredFields = ['base_url', 'Db_name', 'Db_user', 'Db_PAss', 'password', 'installation_date'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Log the installation data
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'client_base_url' => $data['base_url'],
    'database_name' => $data['Db_name'],
    'database_user' => $data['Db_user'],
    'installation_date' => $data['installation_date'],
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Save to log file (you can also save to database)
$logFile = __DIR__ . '/installations.log';
$logEntry = date('Y-m-d H:i:s') . " - New installation from " . $data['base_url'] . "\n";
$logEntry .= "Database: " . $data['Db_name'] . " | User: " . $data['Db_user'] . "\n";
$logEntry .= "Installation Date: " . $data['installation_date'] . "\n";
$logEntry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
$logEntry .= "---\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// You can also save to database
try {
    // Example database save (uncomment and modify as needed)
    /*
    $pdo = new PDO('mysql:host=localhost;dbname=admin_panel', 'username', 'password');
    $stmt = $pdo->prepare("
        INSERT INTO installations 
        (base_url, database_name, database_user, installation_date, ip_address, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['base_url'],
        $data['Db_name'],
        $data['Db_user'],
        $data['installation_date'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    */
} catch (Exception $e) {
    // Log error but don't fail the request
    error_log("Installation endpoint database error: " . $e->getMessage());
}

// Send notification email (optional)
$to = 'admin@yantralogic.com';
$subject = 'New Client Installation - ' . $data['base_url'];
$message = "
New client installation completed:

Base URL: {$data['base_url']}
Database: {$data['Db_name']}
Database User: {$data['Db_user']}
Installation Date: {$data['installation_date']}
IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "

Please verify the installation and add to your client management system.
";

// Uncomment to send email
// mail($to, $subject, $message);

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Installation data received successfully',
    'installation_id' => uniqid('inst_', true),
    'timestamp' => date('c')
]);
?>
