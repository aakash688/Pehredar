<?php
/**
 * Cached Database Class
 * 
 * Extends the base Database class with intelligent caching capabilities
 * for improved performance in mobile APIs
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/CacheManager.php';

if (!class_exists('CachedDatabase')) {
class CachedDatabase extends Database {
    private $cache;
    
    public function __construct() {
        parent::__construct();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Execute a cached query that returns a single row
     */
    public function cachedQuerySingle($sql, $params = [], $ttl = 300) {
        // Temporarily disable caching to fix immediate issues
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result;
        } catch (Exception $e) {
            error_log("CachedDatabase::cachedQuerySingle failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute a cached query that returns multiple rows
     */
    public function cachedQueryMultiple($sql, $params = [], $ttl = 300) {
        // Temporarily disable caching to fix immediate issues
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $results;
        } catch (Exception $e) {
            error_log("CachedDatabase::cachedQueryMultiple failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a cached query (alias for cachedQueryMultiple for compatibility)
     */
    public function cachedQuery($sql, $params = [], $ttl = 300) {
        return $this->cachedQueryMultiple($sql, $params, $ttl);
    }
    
    /**
     * Execute a query directly (inherit from parent Database class but make it explicit)
     */
    public function query($sql, $params = []) {
        return parent::query($sql, $params);
    }
    
    /**
     * Cache user-specific data
     */
    public function cacheUserData($userId, $type, $data, $ttl = 900) {
        $cacheKey = "user_{$type}_{$userId}";
        $this->cache->set($cacheKey, $data, $ttl, 'users');
    }
    
    /**
     * Get cached user data
     */
    public function getCachedUserData($userId, $type) {
        $cacheKey = "user_{$type}_{$userId}";
        return $this->cache->get($cacheKey, 'users');
    }
    
    /**
     * Cache mobile API responses
     */
    public function cacheMobileApiResponse($endpoint, $userId, $data, $ttl = 300) {
        $cacheKey = "mobile_api_{$endpoint}_{$userId}";
        $this->cache->set($cacheKey, $data, $ttl, 'mobile_api');
    }
    
    /**
     * Get cached mobile API response
     */
    public function getCachedMobileApiResponse($endpoint, $userId) {
        $cacheKey = "mobile_api_{$endpoint}_{$userId}";
        return $this->cache->get($cacheKey, 'mobile_api');
    }
    
    /**
     * Clear user-specific caches
     */
    public function clearUserCache($userId) {
        $this->cache->clear('users');
    }
    
    /**
     * Clear query caches
     */
    public function clearQueryCache() {
        $this->cache->clear('queries');
    }
    
    /**
     * Clear all caches
     */
    public function clearAllCaches() {
        $this->cache->clear();
    }
}
}
?>
