<?php
/**
 * Advanced Query Optimizer
 * 
 * Additional optimizations for remaining performance bottlenecks
 */

class QueryOptimizer {
    private $db;
    private $cache;
    
    public function __construct() {
        require_once __DIR__ . '/database.php';
        require_once __DIR__ . '/CacheManager.php';
        $this->db = new Database();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Optimized salary records query with proper joins and caching
     */
    public function getOptimizedSalaryRecords($limit = 50, $offset = 0) {
        $cacheKey = "salary_records_optimized_{$limit}_{$offset}";
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "
            SELECT 
                sr.id, sr.user_id, sr.month, sr.year, sr.base_salary, 
                sr.calculated_salary, sr.final_salary, sr.created_at,
                u.first_name, u.surname, u.user_type,
                COALESCE(ase.remaining_balance, 0) as advance_remaining,
                COALESCE(ase.monthly_deduction_amount, 0) as advance_deduction
            FROM salary_records sr
            INNER JOIN users u ON sr.user_id = u.id
            LEFT JOIN advance_salary_enhanced ase ON u.id = ase.user_id AND ase.status = 'active'
            ORDER BY sr.created_at DESC, sr.year DESC, sr.month DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $results = $stmt->fetchAll();
        
        $this->cache->set($cacheKey, $results, 900); // 15 minutes
        return $results;
    }
    
    /**
     * Optimized ticket details with single query instead of multiple
     */
    public function getOptimizedTicketDetails($ticketId) {
        $cacheKey = "ticket_details_{$ticketId}";
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Single query to get ticket, comments, and attachments
        $sql = "
            SELECT 
                t.*,
                s.society_name,
                u.first_name as user_first_name,
                u.surname as user_surname,
                cu.name as client_name,
                (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id) as comment_count,
                (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count
            FROM tickets t
            LEFT JOIN society_onboarding_data s ON t.society_id = s.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN clients_users cu ON t.user_id = cu.id
            WHERE t.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            // Get comments and attachments in separate optimized queries
            $commentsSQL = "
                SELECT 
                    tc.*,
                    COALESCE(u.first_name, cu.name, 'Unknown') as commenter_name,
                    (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.comment_id = tc.id) as attachment_count
                FROM ticket_comments tc
                LEFT JOIN users u ON tc.user_id = u.id
                LEFT JOIN clients_users cu ON tc.user_id = cu.id
                WHERE tc.ticket_id = ?
                ORDER BY tc.created_at ASC
                LIMIT 50
            ";
            
            $stmt = $this->db->prepare($commentsSQL);
            $stmt->execute([$ticketId]);
            $ticket['comments'] = $stmt->fetchAll();
            
            $this->cache->set($cacheKey, $ticket, 300); // 5 minutes
        }
        
        return $ticket;
    }
    
    /**
     * Optimized user search with proper indexing
     */
    public function searchUsersOptimized($searchTerm, $userType = null, $limit = 20) {
        $cacheKey = "user_search_" . md5($searchTerm . ($userType ?: '') . $limit);
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $params = [];
        $whereClauses = [];
        
        if (!empty($searchTerm)) {
            $whereClauses[] = "(first_name LIKE ? OR surname LIKE ? OR email_id LIKE ? OR mobile_number LIKE ?)";
            $searchParam = "%{$searchTerm}%";
            $params = array_fill(0, 4, $searchParam);
        }
        
        if ($userType) {
            $whereClauses[] = "user_type = ?";
            $params[] = $userType;
        }
        
        $whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";
        
        $sql = "
            SELECT id, first_name, surname, email_id, mobile_number, user_type, 
                   profile_photo, created_at, mobile_access
            FROM users
            {$whereSQL}
            ORDER BY first_name, surname
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        $this->cache->set($cacheKey, $results, 600); // 10 minutes
        return $results;
    }
    
    /**
     * Optimized attendance aggregation
     */
    public function getAttendanceStatsOptimized($userId, $month, $year) {
        $cacheKey = "attendance_stats_{$userId}_{$year}_{$month}";
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN am.code = 'P' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN am.code = 'A' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN am.code = 'H' THEN 1 ELSE 0 END) as holiday_days,
                SUM(CASE WHEN am.code = 'DS' THEN 1 ELSE 0 END) as double_shift_days,
                SUM(COALESCE(am.multiplier, 1)) as total_multiplier
            FROM attendance a
            LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
            WHERE a.user_id = ? 
              AND MONTH(a.attendance_date) = ? 
              AND YEAR(a.attendance_date) = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        $stats = $stmt->fetch();
        
        $this->cache->set($cacheKey, $stats, 3600); // 1 hour
        return $stats;
    }
    
    /**
     * Batch process for avoiding N+1 queries
     */
    public function batchLoadUserProfiles($userIds) {
        if (empty($userIds)) return [];
        
        $cacheKey = "batch_profiles_" . md5(implode(',', $userIds));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "
            SELECT id, first_name, surname, email_id, mobile_number, 
                   user_type, profile_photo, address
            FROM users 
            WHERE id IN ({$placeholders})
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($userIds);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Index by user ID for easy lookup
        $indexed = [];
        foreach ($results as $user) {
            $indexed[$user['id']] = $user;
        }
        
        $this->cache->set($cacheKey, $indexed, 900); // 15 minutes
        return $indexed;
    }
    
    /**
     * Clear cache for specific data types
     */
    public function invalidateCache($type, $id = null) {
        switch ($type) {
            case 'salary':
                $this->cache->clear('queries');
                break;
            case 'user':
                if ($id) {
                    $this->cache->delete("user_search_*");
                    $this->cache->delete("batch_profiles_*");
                }
                break;
            case 'ticket':
                if ($id) {
                    $this->cache->delete("ticket_details_{$id}");
                }
                break;
        }
    }
}
?>
