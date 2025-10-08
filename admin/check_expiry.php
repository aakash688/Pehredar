<?php
/**
 * Web-based License Expiry Checker
 * Access via: http://localhost/project/test/admin/check_expiry.php
 */

session_start();
require_once __DIR__ . '/../helpers/license_manager.php';

// Simple authentication (you can enhance this)
$admin_password = 'admin123';
$is_authenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['admin_authenticated'] = true;
    $is_authenticated = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    $is_authenticated = false;
}

$message = '';
$messageType = '';

if ($is_authenticated && isset($_POST['check_expiry'])) {
    try {
        $licenseManager = new LicenseManager();
        $licenseData = $licenseManager->getLicenseStatus();
        
        if ($licenseData['status'] === 'active' && $licenseData['expires_at']) {
            $expiryTime = strtotime($licenseData['expires_at']);
            $currentTime = time();
            
            if ($expiryTime < $currentTime) {
                // License is expired
                $result = $licenseManager->updateLicenseStatus('expired', null, 'License automatically expired due to expiry date');
                if ($result) {
                    $message = "License has been automatically expired!";
                    $messageType = "success";
                } else {
                    $message = "Failed to expire license!";
                    $messageType = "error";
                }
            } else {
                $daysRemaining = ceil(($expiryTime - $currentTime) / (24 * 60 * 60));
                $message = "License is active. Days remaining: " . $daysRemaining;
                $messageType = "info";
            }
        } else {
            $message = "License has no expiry date or is not active.";
            $messageType = "info";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

$licenseManager = new LicenseManager();
$licenseStatus = $licenseManager->getLicenseStatus();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Expiry Checker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .content {
            padding: 30px;
        }
        
        .auth-form {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            max-width: 300px;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }
        
        .status-card {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 600;
            color: #374151;
        }
        
        .status-value {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .status-active { color: #10b981; }
        .status-suspended { color: #f59e0b; }
        .status-expired { color: #ef4444; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .logout-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-link:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🕒 License Expiry Checker</h1>
            <p>Check and manage license expiration status</p>
        </div>
        
        <div class="content">
            <?php if (!$is_authenticated): ?>
                <div class="auth-form">
                    <h2>Admin Authentication</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="password">Admin Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="?logout=1" class="logout-link">Logout</a>
                </div>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="status-card">
                    <h3>Current License Status</h3>
                    <div class="status-item">
                        <span class="status-label">Status:</span>
                        <span class="status-value status-<?php echo $licenseStatus['status']; ?>">
                            <?php echo strtoupper($licenseStatus['status']); ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Expires At:</span>
                        <span class="status-value">
                            <?php echo $licenseStatus['expires_at'] ?: 'Never'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Is Active:</span>
                        <span class="status-value">
                            <?php echo $licenseStatus['is_active'] ? 'YES' : 'NO'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Checked:</span>
                        <span class="status-value">
                            <?php echo $licenseStatus['last_checked']; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Reason:</span>
                        <span class="status-value">
                            <?php echo $licenseStatus['reason']; ?>
                        </span>
                    </div>
                </div>
                
                <form method="POST" style="text-align: center;">
                    <button type="submit" name="check_expiry" class="btn btn-danger">
                        🔍 Check & Auto-Expire License
                    </button>
                </form>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <h4>How License Expiry Works:</h4>
                    <ul style="text-align: left; color: #6b7280;">
                        <li><strong>Automatic Check:</strong> License is checked every time the application is accessed</li>
                        <li><strong>Auto-Expiry:</strong> If expiry date has passed, license is automatically set to 'expired'</li>
                        <li><strong>Manual Check:</strong> Use the button above to manually check and expire licenses</li>
                        <li><strong>Cron Job:</strong> For automatic daily checks, set up a cron job to run the checker script</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
