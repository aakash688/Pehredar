<?php
/**
 * Optimized Guard API Helper
 * 
 * High-performance helper functions for Guard mobile APIs
 * Uses connection pooling, intelligent caching, and optimized queries
 * 
 * IMPORTANT: All functions maintain EXACT same output format as original APIs
 */

require_once __DIR__ . '/../../helpers/ConnectionPool.php';
require_once __DIR__ . '/../../helpers/CacheManager.php';
require_once __DIR__ . '/../../helpers/CachedDatabase.php';

if (!class_exists('OptimizedGuardAPI')) {
class OptimizedGuardAPI {
    private $cache;
    private $db;
    
    public function __construct() {
        $this->cache = CacheManager::getInstance();
        $this->db = new CachedDatabase();
    }
    
    /**
     * Get guard authentication data with caching
     */
    public function getGuardAuth($identifier) {
        $cacheKey = 'guard_auth_' . md5($identifier);
        
        // Try cache first (reduced to 1 minute TTL for auth data due to password changes)
        $cached = $this->cache->get($cacheKey, 'guards');
        if ($cached !== null) {
            return $cached;
        }
        
        // Determine if identifier is email or mobile
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email_id' : 'mobile_number';
        
        $sql = "SELECT id, first_name, surname, password, user_type, mobile_number, 
                       email_id, mobile_access 
                FROM users 
                WHERE $field = ? AND user_type = 'Guard' 
                LIMIT 1";
        
        // Reduced cache time for auth data (1 minute instead of 5)
        $guard = $this->db->cachedQuerySingle($sql, [$identifier], 60);
        
        if ($guard) {
            $this->cache->set($cacheKey, $guard, 60, 'guards');
        }
        
        return $guard;
    }
    
    /**
     * Get guard profile data with caching
     */
    public function getGuardProfile($userId) {
        $cacheKey = 'guard_profile_' . $userId;
        
        $cached = $this->cache->get($cacheKey, 'guards');
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'Guard' LIMIT 1";
        $profile = $this->db->cachedQuerySingle($sql, [$userId], 900);
        
        if ($profile) {
            $this->cache->set($cacheKey, $profile, 900, 'guards');
        }
        
        return $profile;
    }
    
    /**
     * Get guard activities with caching and pagination
     */
    public function getGuardActivities($userId, $page = 1, $limit = 10, $filters = []) {
        $cacheKey = 'guard_activities_' . $userId . '_' . $page . '_' . $limit . '_' . md5(serialize($filters));
        
        // Check cache for recent data
        $cached = $this->cache->get($cacheKey, 'activities');
        if ($cached !== null) {
            return $cached;
        }
        
        $offset = ($page - 1) * $limit;
        $where = ['a.society_id IN (SELECT DISTINCT r.society_id FROM roster r WHERE r.guard_id = ?)'];
        $params = [$userId];
        
        // Apply filters
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $where[] = 'DATE(a.scheduled_date) BETWEEN ? AND ?';
            $params[] = $filters['date_start'];
            $params[] = $filters['date_end'];
        } elseif (!empty($filters['date_start'])) {
            $where[] = 'DATE(a.scheduled_date) >= ?';
            $params[] = $filters['date_start'];
        } elseif (!empty($filters['date_end'])) {
            $where[] = 'DATE(a.scheduled_date) <= ?';
            $params[] = $filters['date_end'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['q'])) {
            $where[] = '(a.title LIKE ? OR a.description LIKE ?)';
            $searchTerm = '%' . $filters['q'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM activities a WHERE $whereClause";
        // Temporarily use direct database connection
        $pdo = ConnectionPool::getConnection();
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult['total'] ?? 0;
        
        // Get activities
        $sql = "SELECT a.*, s.society_name, 
                       CASE 
                         WHEN a.scheduled_date > NOW() THEN 'Upcoming'
                         WHEN a.scheduled_date <= NOW() AND a.status != 'Completed' THEN 'Ongoing'
                         ELSE a.status
                       END as computed_status
                FROM activities a
                LEFT JOIN society_onboarding_data s ON a.society_id = s.id
                WHERE $whereClause
                ORDER BY a.scheduled_date DESC, a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        // Temporarily use direct database connection
        $pdo = ConnectionPool::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [
            'activities' => $activities,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        
        // Cache for 5 minutes
        $this->cache->set($cacheKey, $result, 300, 'activities');
        
        return $result;
    }
    
    /**
     * Get guard attendance history with caching
     */
    public function getGuardAttendanceHistory($userId, $page = 1, $limit = 10, $filters = []) {
        $cacheKey = 'guard_attendance_' . $userId . '_' . $page . '_' . $limit . '_' . md5(serialize($filters));
        
        $cached = $this->cache->get($cacheKey, 'attendance');
        if ($cached !== null) {
            return $cached;
        }
        
        $offset = ($page - 1) * $limit;
        
        // Build dynamic query based on available columns
        $where = ['a.user_id = ?'];
        $params = [$userId];
        
        if (!empty($filters['code'])) {
            $where[] = 'am.code = ?';
            $params[] = strtoupper($filters['code']);
        }
        
        if (!empty($filters['date'])) {
            $where[] = 'a.attendance_date = ?';
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $where[] = 'a.attendance_date BETWEEN ? AND ?';
            $params[] = $filters['date_start'];
            $params[] = $filters['date_end'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM attendance a 
                     LEFT JOIN attendance_master am ON a.attendance_master_id = am.id 
                     WHERE $whereClause";
        $totalResult = $this->db->cachedQuerySingle($countSql, $params, 600);
        $total = $totalResult['total'] ?? 0;
        
        // Get attendance records with flexible column selection
        $sql = "SELECT a.*, am.code, am.description as status_description, am.multiplier,
                       a.attendance_date as date_display,
                       a.shift_start as check_in_display,
                       a.shift_end as check_out_display,
                       s.society_name, sh.shift_name
                FROM attendance a
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                LEFT JOIN society_onboarding_data s ON a.society_id = s.id
                LEFT JOIN shift_master sh ON a.shift_id = sh.id
                WHERE $whereClause
                ORDER BY a.attendance_date DESC, a.id DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $attendance = $this->db->cachedQueryMultiple($sql, $params, 600);
        
        $result = [
            'attendance' => $attendance,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        
        // Cache for 10 minutes
        $this->cache->set($cacheKey, $result, 600, 'attendance');
        
        return $result;
    }
    
    /**
     * Get current attendance session for check-in/out
     */
    public function getCurrentAttendanceSession($userId) {
        $cacheKey = 'guard_current_session_' . $userId;
        
        // Short cache for current session (1 minute)
        $cached = $this->cache->get($cacheKey, 'attendance');
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT a.*, am.code, am.description, s.society_name
                FROM attendance a
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                LEFT JOIN society_onboarding_data s ON a.society_id = s.id
                WHERE a.user_id = ? 
                  AND a.attendance_date = CURDATE()
                  AND (a.shift_end IS NULL OR am.code != 'P')
                ORDER BY a.id DESC 
                LIMIT 1";
        
        $session = $this->db->cachedQuerySingle($sql, [$userId], 60);
        
        if ($session) {
            $this->cache->set($cacheKey, $session, 60, 'attendance');
        }
        
        return $session;
    }
    
    /**
     * Get guard salary slips with caching
     */
    public function getGuardSalarySlips($userId, $page = 1, $limit = 10, $filters = []) {
        $cacheKey = 'guard_salary_slips_' . $userId . '_' . $page . '_' . $limit . '_' . md5(serialize($filters));
        
        $cached = $this->cache->get($cacheKey, 'salary');
        if ($cached !== null) {
            return $cached;
        }
        
        $offset = ($page - 1) * $limit;
        $where = ['user_id = ?'];
        $params = [$userId];
        
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(pay_period) = ?';
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['month'])) {
            $where[] = 'MONTH(pay_period) = ?';
            $params[] = $filters['month'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM salary_records WHERE $whereClause";
        $totalResult = $this->db->cachedQuerySingle($countSql, $params, 3600);
        $total = $totalResult['total'] ?? 0;
        
        // Get salary slips
        $sql = "SELECT * FROM salary_records 
                WHERE $whereClause 
                ORDER BY pay_period DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $slips = $this->db->cachedQueryMultiple($sql, $params, 3600);
        
        $result = [
            'salary_slips' => $slips,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        
        // Cache for 1 hour (salary data doesn't change frequently)
        $this->cache->set($cacheKey, $result, 3600, 'salary');
        
        return $result;
    }
    
    /**
     * Clear user-specific caches when data changes
     */
    public function clearGuardCache($userId, $identifier = null) {
        // Clear profile and activity caches
        $patterns = [
            'guard_profile_' . $userId,
            'guard_activities_' . $userId,
            'guard_attendance_' . $userId,
            'guard_current_session_' . $userId,
            'guard_salary_slips_' . $userId
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->delete($pattern, 'guards');
            $this->cache->delete($pattern, 'activities');
            $this->cache->delete($pattern, 'attendance');
            $this->cache->delete($pattern, 'salary');
        }
        
        // Clear auth cache by identifier if provided
        if ($identifier) {
            $authCacheKey = 'guard_auth_' . md5($identifier);
            $this->cache->delete($authCacheKey, 'guards');
        }
        
        // If no identifier provided, try to get user's email/mobile and clear auth cache
        if (!$identifier) {
            try {
                $pdo = ConnectionPool::getConnection();
                $stmt = $pdo->prepare("SELECT email_id, mobile_number FROM users WHERE id = ? AND user_type = 'Guard' LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Clear auth cache for both email and mobile (user could login with either)
                    if (!empty($user['email_id'])) {
                        $emailAuthKey = 'guard_auth_' . md5($user['email_id']);
                        $this->cache->delete($emailAuthKey, 'guards');
                    }
                    if (!empty($user['mobile_number'])) {
                        $mobileAuthKey = 'guard_auth_' . md5($user['mobile_number']);
                        $this->cache->delete($mobileAuthKey, 'guards');
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to clear auth cache: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get cached guard roster assignments
     */
    public function getGuardRosterAssignments($userId) {
        $cacheKey = 'guard_roster_' . $userId;
        
        $cached = $this->cache->get($cacheKey, 'guards');
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT r.*, s.society_name, site.site_name
                FROM roster r
                LEFT JOIN society_onboarding_data s ON r.society_id = s.id
                LEFT JOIN sites site ON r.site_id = site.id
                WHERE r.guard_id = ?
                ORDER BY r.shift_date DESC";
        
        $assignments = $this->db->cachedQueryMultiple($sql, [$userId], 1800);
        
        if ($assignments) {
            $this->cache->set($cacheKey, $assignments, 1800, 'guards');
        }
        
        return $assignments;
    }
}
}

// Singleton instance
if (!function_exists('getOptimizedGuardAPI')) {
function getOptimizedGuardAPI() {
    static $instance = null;
    if ($instance === null) {
        $instance = new OptimizedGuardAPI();
    }
    return $instance;
}

/**
 * Send optimized JSON response with compression
 */
function sendOptimizedGuardResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    
    // Enable gzip compression for faster responses
    if (!headers_sent() && function_exists('gzencode')) {
        $json = json_encode($data);
        if (strlen($json) > 1024) { // Only compress larger responses
            header('Content-Encoding: gzip');
            echo gzencode($json);
            return;
        }
    }
    
    echo json_encode($data);
}

/**
 * Send optimized error response
 */
function sendOptimizedGuardError($message, $statusCode = 400) {
    sendOptimizedGuardResponse(['error' => $message], $statusCode);
    exit;
}

/**
 * Get bearer token (optimized version)
 */
function getOptimizedBearerToken() {
    static $token = null;
    if ($token !== null) return $token;
    
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
        return $token;
    }
    
    return null;
}
}
?>
