<?php
/**
 * License Management Dashboard
 * Admin interface to manage application license status
 */

session_start();
require_once __DIR__ . '/../helpers/license_manager.php';

// Simple authentication (you can enhance this)
$admin_password = 'admin123'; // Change this to a secure password
$is_authenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['admin_authenticated'] = true;
    $is_authenticated = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    $is_authenticated = false;
}

$licenseManager = new LicenseManager();
$licenseStatus = $licenseManager->getLicenseStatus();
$licenseLogs = $licenseManager->getLicenseLogs(20);

// Handle status updates
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = $_POST['status'];
        $expiresAt = $_POST['expires_at'] ?: null;
        $reason = $_POST['reason'] ?: '';
        
        $result = $licenseManager->updateLicenseStatus($status, $expiresAt, $reason);
        
        if ($result) {
            $message = "License status updated successfully!";
            $messageType = "success";
            $licenseStatus = $licenseManager->getLicenseStatus();
            $licenseLogs = $licenseManager->getLicenseLogs(20);
        } else {
            $message = "Failed to update license status!";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Management Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-suspended { background: #fef3c7; color: #92400e; }
        .status-expired { background: #fee2e2; color: #991b1b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;
        }
        .btn {
            background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px;
            cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #059669; }
        .btn-success:hover { background: #047857; }
        .btn-warning { background: #d97706; }
        .btn-warning:hover { background: #b45309; }
        .alert {
            padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .login-form { max-width: 400px; margin: 100px auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_authenticated): ?>
            <!-- Login Form -->
            <div class="card login-form">
                <h2>Admin Login</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Dashboard -->
            <div class="header">
                <h1>License Management Dashboard</h1>
                <p>Manage your application's license status and control access</p>
                <a href="?logout=1" class="btn btn-danger" style="float: right;">Logout</a>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid">
                <!-- Current Status -->
                <div class="card">
                    <h3>Current License Status</h3>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $licenseStatus['status']; ?>">
                            <?php echo ucfirst($licenseStatus['status']); ?>
                        </span>
                    </p>
                    <p><strong>Expires:</strong> 
                        <?php echo $licenseStatus['expires_at'] ? date('F j, Y \a\t g:i A', strtotime($licenseStatus['expires_at'])) : 'Never'; ?>
                    </p>
                    <p><strong>Last Checked:</strong> 
                        <?php echo date('F j, Y \a\t g:i A', strtotime($licenseStatus['last_checked'])); ?>
                    </p>
                    <?php if ($licenseStatus['reason']): ?>
                        <p><strong>Reason:</strong> <?php echo htmlspecialchars($licenseStatus['reason']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h3>Quick Actions</h3>
                    <p>Use these buttons to quickly change license status:</p>
                    <div style="margin-top: 15px;">
                        <a href="#" onclick="setStatus('active')" class="btn btn-success">Activate</a>
                        <a href="#" onclick="setStatus('suspended')" class="btn btn-warning">Suspend</a>
                        <a href="#" onclick="setStatus('expired')" class="btn btn-danger">Expire</a>
                    </div>
                </div>
            </div>

            <!-- Update Form -->
            <div class="card">
                <h3>Update License Status</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo $licenseStatus['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $licenseStatus['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="expired" <?php echo $licenseStatus['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="expires_at">Expires At (optional):</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" 
                               value="<?php echo $licenseStatus['expires_at'] ? date('Y-m-d\TH:i', strtotime($licenseStatus['expires_at'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason (optional):</label>
                        <textarea id="reason" name="reason" rows="3" 
                                  placeholder="Enter reason for status change..."><?php echo htmlspecialchars($licenseStatus['reason']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">Update Status</button>
                </form>
            </div>

            <!-- License Logs -->
            <div class="card">
                <h3>Recent License Changes</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenseLogs as $log): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['reason'] ?: 'No reason provided'); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- API Information -->
            <div class="card">
                <h3>API Control</h3>
                <p>You can also control the license status via API:</p>
                <div style="background: #f3f4f6; padding: 15px; border-radius: 4px; margin-top: 10px;">
                    <p><strong>Endpoint:</strong> <code>POST /api/license_control.php</code></p>
                    <p><strong>Headers:</strong> <code>Authorization: Bearer YOUR_SECRET_API_KEY_HERE_2024</code></p>
                    <p><strong>Example Payload:</strong></p>
                    <pre style="background: #1f2937; color: #f9fafb; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
  "status": "suspended",
  "expires_at": "2024-12-31T23:59:59Z",
  "reason": "Payment overdue"
}</pre>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function setStatus(status) {
            document.getElementById('status').value = status;
            document.getElementById('reason').focus();
        }
    </script>
</body>
</html>
