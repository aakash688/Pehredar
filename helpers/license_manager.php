<?php
/**
 * License Management System
 * Handles application licensing and status control
 */

class LicenseManager {
    private $config;
    private $db;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $this->db = $this->getDatabaseConnection();
    }
    
    private function getDatabaseConnection() {
        try {
            $dsn = "mysql:host={$this->config['db']['host']};port={$this->config['db']['port']};dbname={$this->config['db']['dbname']};charset=utf8mb4";
            return new PDO($dsn, $this->config['db']['user'], $this->config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10
            ]);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get current license status
     */
    public function getLicenseStatus() {
        // Check if database connection is available
        if ($this->db === null) {
            error_log("LicenseManager: Database connection failed, returning default status");
            return $this->getDefaultLicenseStatus();
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM license_status WHERE id = 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // Create default license status if not exists
                return $this->createDefaultLicenseStatus();
            }
            
            return [
                'status' => $result['status'], // active, suspended, expired
                'expires_at' => $result['expires_at'],
                'last_checked' => $result['last_checked'],
                'reason' => $result['reason'],
                'is_active' => $this->isLicenseActive($result)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'expires_at' => null,
                'last_checked' => date('Y-m-d H:i:s'),
                'reason' => 'Database error: ' . $e->getMessage(),
                'is_active' => false
            ];
        }
    }
    
    /**
     * Get default license status when database is not available
     */
    private function getDefaultLicenseStatus() {
        return [
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'last_checked' => date('Y-m-d H:i:s'),
            'reason' => 'Database connection unavailable - using default status',
            'is_active' => true
        ];
    }
    
    /**
     * Check if database connection is available
     */
    private function checkDatabaseConnection() {
        if ($this->db === null) {
            error_log("LicenseManager: Database connection not available");
            return false;
        }
        return true;
    }
    
    /**
     * Update license status via API
     */
    public function updateLicenseStatus($status, $expiresAt = null, $reason = '') {
        if (!$this->checkDatabaseConnection()) {
            error_log("LicenseManager: Cannot update license status - database connection unavailable");
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO license_status (id, status, expires_at, last_checked, reason) 
                VALUES (1, :status, :expires_at, NOW(), :reason)
                ON DUPLICATE KEY UPDATE 
                status = :status, 
                expires_at = :expires_at, 
                last_checked = NOW(), 
                reason = :reason
            ");
            
            $result = $stmt->execute([
                'status' => $status,
                'expires_at' => $expiresAt,
                'reason' => $reason
            ]);
            
            if ($result) {
                // Log the status change
                $this->logStatusChange($status, $reason);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("License status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if license is currently active
     */
    public function isLicenseActive($licenseData = null) {
        if (!$licenseData) {
            $licenseData = $this->getLicenseStatus();
        }
        
        if ($licenseData['status'] === 'active') {
            // Check if not expired
            if ($licenseData['expires_at'] && strtotime($licenseData['expires_at']) < time()) {
                // Auto-expire the license if it's past expiry date
                $this->autoExpireLicense();
                return false;
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Auto-check and expire licenses on every application access
     * This method should be called at the beginning of every page load
     */
    public function performAutoExpiryCheck() {
        try {
            // Check for any active licenses that have expired
            $stmt = $this->db->prepare("
                SELECT id, expires_at, reason 
                FROM license_status 
                WHERE status = 'active' 
                AND expires_at IS NOT NULL 
                AND expires_at < NOW()
            ");
            $stmt->execute();
            $expiredLicenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($expiredLicenses)) {
                foreach ($expiredLicenses as $license) {
                    // Auto-expire each expired license
                    $this->autoExpireSpecificLicense($license['id'], $license['expires_at']);
                }
                
                error_log("Auto-expiry check: Expired " . count($expiredLicenses) . " license(s)");
            }
            
            // Also check for licenses expiring soon (within 24 hours) and log warnings
            $this->checkUpcomingExpirations();
            
        } catch (Exception $e) {
            error_log("Auto-expiry check error: " . $e->getMessage());
        }
    }
    
    /**
     * Check for licenses expiring soon and log warnings
     */
    private function checkUpcomingExpirations() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, expires_at, reason 
                FROM license_status 
                WHERE status = 'active' 
                AND expires_at IS NOT NULL 
                AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $expiringSoon = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($expiringSoon)) {
                foreach ($expiringSoon as $license) {
                    $hoursLeft = round((strtotime($license['expires_at']) - time()) / 3600, 1);
                    error_log("WARNING: License {$license['id']} expires in {$hoursLeft} hours at {$license['expires_at']}");
                }
            }
        } catch (Exception $e) {
            error_log("Upcoming expiration check error: " . $e->getMessage());
        }
    }
    
    /**
     * Auto-expire a specific license
     */
    private function autoExpireSpecificLicense($licenseId, $expiresAt) {
        try {
            $stmt = $this->db->prepare("
                UPDATE license_status 
                SET status = 'expired', 
                    reason = CONCAT('License automatically expired on ', NOW(), ' (was set to expire on ', :expires_at, ')'),
                    last_checked = NOW()
                WHERE id = :id AND status = 'active'
            ");
            $stmt->execute([
                'id' => $licenseId,
                'expires_at' => $expiresAt
            ]);
            
            if ($stmt->rowCount() > 0) {
                // Log the auto-expiry
                $this->logStatusChange('expired', "License automatically expired (was set to expire on {$expiresAt})");
                error_log("License {$licenseId} automatically expired (was set to expire on {$expiresAt})");
            }
        } catch (Exception $e) {
            error_log("Error auto-expiring license {$licenseId}: " . $e->getMessage());
        }
    }
    
    /**
     * Automatically expire license if past expiry date
     */
    private function autoExpireLicense() {
        try {
            $stmt = $this->db->prepare("
                UPDATE license_status 
                SET status = 'expired', 
                    reason = CONCAT('License automatically expired on ', NOW()),
                    last_checked = NOW()
                WHERE status = 'active' 
                AND expires_at IS NOT NULL 
                AND expires_at < NOW()
            ");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Log the auto-expiry
                $this->logStatusChange('expired', 'License automatically expired due to expiry date');
                error_log("License automatically expired due to expiry date");
            }
        } catch (Exception $e) {
            error_log("Error auto-expiring license: " . $e->getMessage());
        }
    }
    
    /**
     * Create default license status
     */
    private function createDefaultLicenseStatus() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO license_status (id, status, expires_at, last_checked, reason) 
                VALUES (1, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), 'Initial setup')
            ");
            $stmt->execute();
            
            return [
                'status' => 'active',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'last_checked' => date('Y-m-d H:i:s'),
                'reason' => 'Initial setup',
                'is_active' => true
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'expires_at' => null,
                'last_checked' => date('Y-m-d H:i:s'),
                'reason' => 'Failed to create license status: ' . $e->getMessage(),
                'is_active' => false
            ];
        }
    }
    
    /**
     * Log status changes
     */
    private function logStatusChange($status, $reason) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO license_logs (status, reason, created_at) 
                VALUES (:status, :reason, NOW())
            ");
            $stmt->execute([
                'status' => $status,
                'reason' => $reason
            ]);
        } catch (Exception $e) {
            error_log("License log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get license logs with pagination and filters
     */
    public function getLicenseLogs($limit = 50, $offset = 0, $statusFilter = null, $dateFrom = null, $dateTo = null) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Add status filter
            if ($statusFilter && in_array($statusFilter, ['active', 'suspended', 'expired'])) {
                $whereConditions[] = "status = :status";
                $params['status'] = $statusFilter;
            }
            
            // Add date filters
            if ($dateFrom) {
                $whereConditions[] = "created_at >= :date_from";
                $params['date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $whereConditions[] = "created_at <= :date_to";
                $params['date_to'] = $dateTo . ' 23:59:59'; // Include end of day
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    id,
                    status,
                    reason,
                    created_at,
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as formatted_date,
                    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago,
                    CASE 
                        WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' minutes ago')
                        WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' hours ago')
                        WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 7 THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' days ago')
                        ELSE DATE_FORMAT(created_at, '%M %d, %Y at %H:%i')
                    END as time_ago
                FROM license_logs 
                {$whereClause}
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("License logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of license logs with filters
     */
    public function getLicenseLogsCount($statusFilter = null, $dateFrom = null, $dateTo = null) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Add status filter
            if ($statusFilter && in_array($statusFilter, ['active', 'suspended', 'expired'])) {
                $whereConditions[] = "status = :status";
                $params['status'] = $statusFilter;
            }
            
            // Add date filters
            if ($dateFrom) {
                $whereConditions[] = "created_at >= :date_from";
                $params['date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $whereConditions[] = "created_at <= :date_to";
                $params['date_to'] = $dateTo . ' 23:59:59';
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "SELECT COUNT(*) as total FROM license_logs {$whereClause}";
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['total']);
        } catch (Exception $e) {
            error_log("License logs count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check license and redirect if inactive
     */
    public function checkLicenseAndRedirect() {
        $licenseStatus = $this->getLicenseStatus();
        
        if (!$licenseStatus['is_active']) {
            $this->handleInactiveLicense($licenseStatus);
        }
    }
    
    /**
     * Handle inactive license
     */
    private function handleInactiveLicense($licenseStatus) {
        // Set appropriate HTTP status
        if ($licenseStatus['status'] === 'suspended') {
            http_response_code(503); // Service Unavailable
        } elseif ($licenseStatus['status'] === 'expired') {
            http_response_code(402); // Payment Required
        } else {
            http_response_code(503);
        }
        
        // Show appropriate message
        $this->showLicenseMessage($licenseStatus);
        exit;
    }
    
    /**
     * Show license status message
     */
    private function showLicenseMessage($licenseStatus) {
        $title = '';
        $message = '';
        $icon = '';
        
        switch ($licenseStatus['status']) {
            case 'suspended':
                $title = 'Service Suspended';
                $message = 'Your account has been suspended. Please contact support for assistance.';
                $icon = '⏸️';
                break;
            case 'expired':
                $title = 'License Expired';
                $message = 'Your license has expired. Please renew your subscription to continue using the service.';
                $icon = '⏰';
                break;
            default:
                $title = 'Service Unavailable';
                $message = 'The service is currently unavailable. Please try again later.';
                $icon = '❌';
        }
        
        if (!empty($licenseStatus['reason'])) {
            $message .= '<br><br><strong>Reason:</strong> ' . htmlspecialchars($licenseStatus['reason']);
        }
        
        if ($licenseStatus['expires_at']) {
            $message .= '<br><strong>Expires:</strong> ' . date('F j, Y \a\t g:i A', strtotime($licenseStatus['expires_at']));
        }
        
        echo $this->getLicensePageHTML($icon, $title, $message);
    }
    
    /**
     * Get license page HTML
     */
    private function getLicensePageHTML($icon, $title, $message) {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon { font-size: 80px; margin-bottom: 20px; }
        h1 { color: #374151; margin-bottom: 20px; font-size: 28px; }
        .message { color: #6b7280; line-height: 1.6; margin-bottom: 30px; }
        .contact {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .contact h3 { color: #374151; margin-bottom: 10px; }
        .contact p { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">' . $icon . '</div>
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="message">' . $message . '</div>
        <div class="contact">
            <h3>Need Help?</h3>
            <p>Contact our support team at <strong>support@yantralogic.com</strong><br>
            or call <strong>+1 (555) 123-4567</strong></p>
        </div>
    </div>
</body>
</html>';
    }
}

/**
 * Global function to check license
 */
function checkApplicationLicense() {
    $licenseManager = new LicenseManager();
    
    // Perform auto-expiry check on every application access
    $licenseManager->performAutoExpiryCheck();
    
    // Then check if license is active and redirect if not
    $licenseManager->checkLicenseAndRedirect();
}

/**
 * Global function to get license status
 */
function getApplicationLicenseStatus() {
    $licenseManager = new LicenseManager();
    return $licenseManager->getLicenseStatus();
}
?>
