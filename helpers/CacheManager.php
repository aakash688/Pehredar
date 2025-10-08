<?php
/**
 * High-Performance Cache Manager
 * 
 * Implements multiple caching layers for industrial-level performance:
 * - File-based caching (compatible with shared hosting)
 * - Query result caching
 * - API response caching
 * - User session caching
 */

class CacheManager {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour
    private $enabled = true;
    
    private function __construct() {
        $this->cacheDir = __DIR__ . '/../cache';
        $this->initializeCache();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize cache directory structure
     */
    private function initializeCache() {
        $directories = [
            $this->cacheDir,
            $this->cacheDir . '/queries',
            $this->cacheDir . '/api',
            $this->cacheDir . '/users',
            $this->cacheDir . '/dashboard',
            $this->cacheDir . '/mobile'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Create .htaccess to prevent direct access
        $htaccess = $this->cacheDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $ttl = null, $category = 'general') {
        if (!$this->enabled) {
            return false;
        }
        
        $ttl = $ttl ?? $this->defaultTTL;
        $cacheFile = $this->getCacheFilePath($key, $category);
        
        // Ensure the directory exists
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                error_log("Failed to create cache directory: " . $cacheDir);
                return false;
            }
        }
        
        $cacheData = [
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl,
            'key' => $key
        ];
        
        try {
            $serialized = serialize($cacheData);
            file_put_contents($cacheFile, $serialized, LOCK_EX);
            return true;
        } catch (Exception $e) {
            error_log("Cache write error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve data from cache
     */
    public function get($key, $category = 'general') {
        if (!$this->enabled) {
            return null;
        }
        
        $cacheFile = $this->getCacheFilePath($key, $category);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        try {
            $serialized = file_get_contents($cacheFile);
            $cacheData = unserialize($serialized);
            
            // Check if expired
            if (time() > $cacheData['expires']) {
                unlink($cacheFile);
                return null;
            }
            
            return $cacheData['data'];
        } catch (Exception $e) {
            error_log("Cache read error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cache database query results
     */
    public function cacheQuery($sql, $params, $results, $ttl = 300) {
        $key = 'query_' . md5($sql . serialize($params));
        return $this->set($key, $results, $ttl, 'queries');
    }
    
    /**
     * Get cached query results
     */
    public function getCachedQuery($sql, $params) {
        $key = 'query_' . md5($sql . serialize($params));
        return $this->get($key, 'queries');
    }
    
    /**
     * Cache API responses
     */
    public function cacheApiResponse($endpoint, $params, $response, $ttl = 600) {
        $key = 'api_' . md5($endpoint . serialize($params));
        return $this->set($key, $response, $ttl, 'api');
    }
    
    /**
     * Get cached API response
     */
    public function getCachedApiResponse($endpoint, $params) {
        $key = 'api_' . md5($endpoint . serialize($params));
        return $this->get($key, 'api');
    }
    
    /**
     * Cache mobile API responses (shorter TTL for real-time data)
     */
    public function cacheMobileApiResponse($endpoint, $userId, $response, $ttl = 300) {
        $key = 'mobile_' . $endpoint . '_' . $userId;
        return $this->set($key, $response, $ttl, 'mobile');
    }
    
    /**
     * Get cached mobile API response
     */
    public function getCachedMobileApiResponse($endpoint, $userId) {
        $key = 'mobile_' . $endpoint . '_' . $userId;
        return $this->get($key, 'mobile');
    }
    
    /**
     * Cache dashboard widget data
     */
    public function cacheDashboardWidget($widgetName, $data, $ttl = 900) {
        $key = 'dashboard_' . $widgetName;
        return $this->set($key, $data, $ttl, 'dashboard');
    }
    
    /**
     * Get cached dashboard widget data
     */
    public function getCachedDashboardWidget($widgetName) {
        $key = 'dashboard_' . $widgetName;
        return $this->get($key, 'dashboard');
    }
    
    /**
     * Cache user-specific data
     */
    public function cacheUserData($userId, $dataType, $data, $ttl = 1800) {
        $key = 'user_' . $userId . '_' . $dataType;
        return $this->set($key, $data, $ttl, 'users');
    }
    
    /**
     * Get cached user data
     */
    public function getCachedUserData($userId, $dataType) {
        $key = 'user_' . $userId . '_' . $dataType;
        return $this->get($key, 'users');
    }
    
    /**
     * Delete specific cache item
     */
    public function delete($key, $category = 'general') {
        $cacheFile = $this->getCacheFilePath($key, $category);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }
    
    /**
     * Clear all cache or specific category
     */
    public function clear($category = null) {
        if ($category) {
            $dir = $this->cacheDir . '/' . $category;
            return $this->clearDirectory($dir);
        } else {
            return $this->clearDirectory($this->cacheDir);
        }
    }
    
    /**
     * Invalidate user-related caches
     */
    public function invalidateUserCache($userId) {
        $this->delete('user_' . $userId . '_profile', 'users');
        $this->delete('user_' . $userId . '_attendance', 'users');
        $this->delete('user_' . $userId . '_salary', 'users');
        $this->delete('mobile_activities_' . $userId, 'mobile');
        $this->delete('mobile_tickets_' . $userId, 'mobile');
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $stats = [
            'enabled' => $this->enabled,
            'cache_dir' => $this->cacheDir,
            'categories' => []
        ];
        
        $categories = ['queries', 'api', 'users', 'dashboard', 'mobile'];
        
        foreach ($categories as $category) {
            $dir = $this->cacheDir . '/' . $category;
            $stats['categories'][$category] = [
                'files' => 0,
                'size' => 0
            ];
            
            if (is_dir($dir)) {
                $files = glob($dir . '/*.cache');
                $stats['categories'][$category]['files'] = count($files);
                
                foreach ($files as $file) {
                    $stats['categories'][$category]['size'] += filesize($file);
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean expired cache files
     */
    public function cleanExpired() {
        $cleaned = 0;
        $categories = ['general', 'queries', 'api', 'users', 'dashboard', 'mobile'];
        
        foreach ($categories as $category) {
            $dir = $this->cacheDir . ($category !== 'general' ? '/' . $category : '');
            
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*.cache');
            
            foreach ($files as $file) {
                try {
                    $data = unserialize(file_get_contents($file));
                    if (isset($data['expires']) && time() > $data['expires']) {
                        unlink($file);
                        $cleaned++;
                    }
                } catch (Exception $e) {
                    // Remove corrupted cache files
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Generate cache file path
     */
    private function getCacheFilePath($key, $category) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $dir = $this->cacheDir . ($category !== 'general' ? '/' . $category : '');
        return $dir . '/' . $safeKey . '.cache';
    }
    
    /**
     * Clear directory recursively
     */
    private function clearDirectory($dir) {
        if (!is_dir($dir)) {
            return true;
        }
        
        $files = glob($dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Enable/disable caching
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }
    
    /**
     * Set default TTL
     */
    public function setDefaultTTL($ttl) {
        $this->defaultTTL = $ttl;
    }
}

/**
 * Cached Database Query Helper
 */
class CachedDatabase {
    private $db;
    private $cache;
    
    public function __construct() {
        require_once __DIR__ . '/database.php';
        $this->db = new Database();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Execute cached query
     */
    public function cachedQuery($sql, $params = [], $ttl = 300) {
        // Try to get from cache first
        $cached = $this->cache->getCachedQuery($sql, $params);
        if ($cached !== null) {
            return $cached;
        }
        
        // Execute query and cache result
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll();
        
        // Cache the results
        $this->cache->cacheQuery($sql, $params, $results, $ttl);
        
        return $results;
    }
    
    /**
     * Execute cached single row query
     */
    public function cachedQuerySingle($sql, $params = [], $ttl = 300) {
        $results = $this->cachedQuery($sql, $params, $ttl);
        return $results ? $results[0] : null;
    }
    
    /**
     * Execute a query directly (delegate to the Database instance)
     */
    public function query($sql, $params = []) {
        return $this->db->query($sql, $params);
    }
}
?>
