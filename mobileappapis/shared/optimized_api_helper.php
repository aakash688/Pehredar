<?php
/**
 * Optimized Mobile API Helper
 * 
 * High-performance helper for mobile APIs with caching and optimization
 * for handling 3000-4000 active users per hour.
 */

require_once __DIR__ . '/../../helpers/ConnectionPool.php';
require_once __DIR__ . '/../../helpers/CacheManager.php';
require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/CachedDatabase.php';

class OptimizedMobileAPI {
    private $cache;
    private $db;
    
    public function __construct() {
        $this->cache = CacheManager::getInstance();
        $this->db = new CachedDatabase();
    }
    
    /**
     * Get user authentication data with caching
     */
    public function getUserAuth($identifier) {
        $cacheKey = 'auth_' . md5($identifier);
        
        // Try cache first (5 minute TTL for auth data)
        $cached = $this->cache->get($cacheKey, 'users');
        if ($cached !== null) {
            return $cached;
        }
        
        // Determine if identifier is email or mobile
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email_id' : 'mobile_number';
        
        // Optimized query with specific columns only
        $sql = "SELECT id, first_name, surname, password, user_type, mobile_number, 
                       email_id, mobile_access, society_id 
                FROM users 
                WHERE $field = ? 
                LIMIT 1";
        
        $user = $this->db->cachedQuerySingle($sql, [$identifier], 300);
        
        if ($user) {
            $this->cache->set($cacheKey, $user, 300, 'users');
        }
        
        return $user;
    }
    
    /**
     * Get user profile with caching
     */
    public function getUserProfile($userId) {
        $cacheKey = 'profile_' . $userId;
        
        $cached = $this->cache->getCachedUserData($userId, 'profile');
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT id, first_name, surname, email_id, mobile_number, user_type,
                       profile_photo, address, emergency_contact, emergency_contact_number
                FROM users 
                WHERE id = ? 
                LIMIT 1";
        
        $profile = $this->db->cachedQuerySingle($sql, [$userId], 900);
        
        if ($profile) {
            $this->cache->cacheUserData($userId, 'profile', $profile, 900);
        }
        
        return $profile;
    }
    
    /**
     * Get user activities with caching and pagination
     */
    public function getUserActivities($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $cacheKey = 'activities_' . $userId . '_' . $page . '_' . $limit;
        
        $cached = $this->cache->getCachedMobileApiResponse('activities', $userId);
        if ($cached !== null && $page === 1) {
            return $cached;
        }
        
        $sql = "SELECT a.id, a.title, a.description, a.status, a.priority, 
                       a.scheduled_date, a.created_at,
                       s.society_name
                FROM activities a
                LEFT JOIN society_onboarding_data s ON a.society_id = s.id
                JOIN activity_assignees aa ON a.id = aa.activity_id
                WHERE aa.user_id = ?
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $activities = $this->db->cachedQuery($sql, [$userId, $limit, $offset], 300);
        
        if ($page === 1) {
            $this->cache->cacheMobileApiResponse('activities', $userId, $activities, 300);
        }
        
        return $activities;
    }
    
    /**
     * Get user tickets with caching
     */
    public function getUserTickets($userId, $status = null, $limit = 20) {
        $params = [$userId, $limit];
        $cacheKey = 'tickets_' . $userId . '_' . ($status ?: 'all');
        
        $cached = $this->cache->getCachedMobileApiResponse('tickets', $userId);
        if ($cached !== null && !$status) {
            return $cached;
        }
        
        $statusCondition = $status ? " AND t.status = ?" : "";
        if ($status) {
            array_splice($params, -1, 0, $status);
        }
        
        $sql = "SELECT t.id, t.title, t.description, t.status, t.priority,
                       t.created_at, t.updated_at,
                       s.society_name
                FROM tickets t
                LEFT JOIN society_onboarding_data s ON t.society_id = s.id
                WHERE t.user_id = ? $statusCondition
                ORDER BY t.created_at DESC
                LIMIT ?";
        
        $tickets = $this->db->cachedQuery($sql, $params, 300);
        
        if (!$status) {
            $this->cache->cacheMobileApiResponse('tickets', $userId, $tickets, 300);
        }
        
        return $tickets;
    }
    
    /**
     * Get user attendance history with caching
     */
    public function getUserAttendance($userId, $startDate = null, $endDate = null, $limit = 30) {
        $startDate = $startDate ?: date('Y-m-01'); // Start of current month
        $endDate = $endDate ?: date('Y-m-d'); // Today
        
        $cacheKey = 'attendance_' . $userId . '_' . $startDate . '_' . $endDate;
        
        $cached = $this->cache->getCachedUserData($userId, 'attendance_' . date('Y-m'));
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT a.id, a.attendance_date, a.check_in_time, a.check_out_time,
                       am.code, am.description as status_description, am.multiplier,
                       s.society_name, sh.shift_name
                FROM attendance a
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                LEFT JOIN society_onboarding_data s ON a.society_id = s.id
                LEFT JOIN shift_master sh ON a.shift_id = sh.id
                WHERE a.user_id = ? 
                  AND a.attendance_date BETWEEN ? AND ?
                ORDER BY a.attendance_date DESC
                LIMIT ?";
        
        $attendance = $this->db->cachedQuery($sql, [$userId, $startDate, $endDate, $limit], 600);
        
        $this->cache->cacheUserData($userId, 'attendance_' . date('Y-m'), $attendance, 1800);
        
        return $attendance;
    }

    /**
     * Get client authentication data with caching (for mobile app login)
     */
    public function getClientAuth($identifier) {
        $cacheKey = 'client_auth_' . md5($identifier);
        
        // Try cache first (5 minute TTL for auth data)
        $cached = $this->cache->get($cacheKey, 'clients');
        if ($cached !== null) {
            return $cached;
        }
        
        // Use optimized query strategy: try username first, then email, then phone
        // This is faster than OR queries with multiple indexes
        
        $client = null;
        
        // Try username first (most common login method)
        $sql = "SELECT id, username, email, phone, password_hash, password_salt, 
                       name, society_id, is_primary
                FROM clients_users 
                WHERE username = ? 
                LIMIT 1";
        
        $client = $this->db->cachedQuerySingle($sql, [$identifier], 300);
        
        // If not found by username, try email
        if (!$client) {
            $sql = "SELECT id, username, email, phone, password_hash, password_salt, 
                           name, society_id, is_primary
                    FROM clients_users 
                    WHERE email = ? 
                    LIMIT 1";
            
            $client = $this->db->cachedQuerySingle($sql, [$identifier], 300);
        }
        
        // If still not found, try phone
        if (!$client) {
            $sql = "SELECT id, username, email, phone, password_hash, password_salt, 
                           name, society_id, is_primary
                    FROM clients_users 
                    WHERE phone = ? 
                    LIMIT 1";
            
            $client = $this->db->cachedQuerySingle($sql, [$identifier], 300);
        }
        
        if ($client) {
            $this->cache->set($cacheKey, $client, 300, 'clients');
        }
        
        return $client;
    }

    /**
     * Get client by ID with caching
     */
    public function getClientById($clientId) {
        $cacheKey = 'client_by_id_' . $clientId;
        
        // Try cache first (5 minute TTL for client data)
        $cached = $this->cache->get($cacheKey, 'clients');
        if ($cached !== null) {
            return $cached;
        }
        
        // Use optimized query with specific columns
        $sql = "SELECT id, username, email, phone, password_hash, password_salt, 
                       name, society_id, is_primary
                FROM clients_users 
                WHERE id = ? 
                LIMIT 1";
        
        $client = $this->db->cachedQuerySingle($sql, [$clientId], 300);
        
        if ($client) {
            $this->cache->set($cacheKey, $client, 300, 'clients');
        }
        
        return $client;
    }

    /**
     * Update client password
     */
    public function updateClientPassword($clientId, $newPasswordHash, $newSalt) {
        $sql = "UPDATE clients_users SET password_hash = ?, password_salt = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$newPasswordHash, $newSalt, $clientId]);
        
        if ($stmt->rowCount() > 0) {
            // Get client data to clear all possible auth cache entries
            $client = $this->getClientById($clientId);
            
            if ($client) {
                // Clear all possible auth cache keys for this client
                $this->cache->delete('client_by_id_' . $clientId, 'clients');
                $this->cache->delete('client_auth_' . md5($client['username']), 'clients');
                $this->cache->delete('client_auth_' . md5($client['email']), 'clients');
                $this->cache->delete('client_auth_' . md5($client['phone']), 'clients');
                $this->cache->delete('client_auth_' . md5($clientId), 'clients');
                
                // Clear all client-related caches entirely to be safe
                $this->cache->clear('clients');
                
                // Also clear any query caches that might contain old password data
                $this->cache->clear('queries');
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Get society details with caching
     */
    public function getSocietyDetails($societyId) {
        $cacheKey = 'society_' . $societyId;
        
        // Try cache first (1 hour TTL for society data)
        $cached = $this->cache->get($cacheKey, 'societies');
        if ($cached !== null) {
            return $cached;
        }
        
        // Use optimized query with specific columns and LIMIT 1
        $sql = "SELECT id, society_name 
                FROM society_onboarding_data 
                WHERE id = ? 
                LIMIT 1";
        
        $society = $this->db->cachedQuerySingle($sql, [$societyId], 3600);
        
        if ($society) {
            $this->cache->set($cacheKey, $society, 3600, 'societies');
        }
        
        return $society;
    }
    
    /**
     * Get optimized dashboard data
     */
    public function getDashboardStats($userId) {
        $cached = $this->cache->getCachedUserData($userId, 'dashboard');
        if ($cached !== null) {
            return $cached;
        }
        
        $currentMonth = date('Y-m');
        
        // Get multiple stats in a single query where possible
        $stats = [];
        
        // Attendance this month
        $sql = "SELECT COUNT(*) as total_days,
                       SUM(CASE WHEN am.code = 'P' THEN 1 ELSE 0 END) as present_days,
                       SUM(CASE WHEN am.code = 'A' THEN 1 ELSE 0 END) as absent_days
                FROM attendance a
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                WHERE a.user_id = ? AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
        
        $attendanceStats = $this->db->cachedQuerySingle($sql, [$userId, $currentMonth], 600);
        
        // Open tickets count
        $sql = "SELECT COUNT(*) as open_tickets 
                FROM tickets 
                WHERE user_id = ? AND status = 'Open'";
        
        $ticketStats = $this->db->cachedQuerySingle($sql, [$userId], 300);
        
        // Pending activities count
        $sql = "SELECT COUNT(*) as pending_activities
                FROM activities a
                JOIN activity_assignees aa ON a.id = aa.activity_id
                WHERE aa.user_id = ? AND a.status = 'Pending'";
        
        $activityStats = $this->db->cachedQuerySingle($sql, [$userId], 300);
        
        $stats = [
            'attendance' => $attendanceStats,
            'tickets' => $ticketStats,
            'activities' => $activityStats
        ];
        
        $this->cache->cacheUserData($userId, 'dashboard', $stats, 600);
        
        return $stats;
    }
    
    /**
     * Optimized response helper
     */
    public function sendOptimizedResponse($data, $statusCode = 200, $cacheHeaders = true) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        
        if ($cacheHeaders) {
            // Add cache headers for client-side caching
            header('Cache-Control: public, max-age=300'); // 5 minutes
            header('ETag: ' . md5(json_encode($data)));
        }
        
        // Compress output if possible
        if (function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
            strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            header('Content-Encoding: gzip');
            echo gzencode(json_encode($data));
        } else {
            echo json_encode($data);
        }
        
        exit;
    }
    
    /**
     * Clear user-specific caches
     */
    public function clearUserCache($userId) {
        $this->cache->invalidateUserCache($userId);
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        return $this->cache->getStats();
    }
}

/**
 * Global optimized API helper functions
 */
function getOptimizedAPI() {
    static $api = null;
    if ($api === null) {
        $api = new OptimizedMobileAPI();
    }
    return $api;
}

function sendOptimizedResponse($data, $statusCode = 200) {
    $api = getOptimizedAPI();
    $api->sendOptimizedResponse($data, $statusCode);
}

function sendOptimizedError($message, $statusCode = 400) {
    sendOptimizedResponse([
        'success' => false,
        'message' => $message,
        'timestamp' => time()
    ], $statusCode);
}
?>
