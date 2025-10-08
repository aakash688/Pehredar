<?php
/**
 * Database Connection Pool Manager
 * 
 * This class implements a singleton-based connection pool to manage database connections
 * efficiently and solve the "max connections per hour" issue on shared hosting.
 * 
 * Features:
 * - Persistent connections to reuse existing database connections
 * - Connection health monitoring with automatic reconnection
 * - Thread-safe singleton pattern for consistent connection reuse
 * - Connection timeout management
 * - Automatic cleanup of stale connections
 */

class ConnectionPool {
    private static $instance = null;
    private static $connection = null;
    private static $lastConnectionTime = null;
    private static $connectionTimeout = 3600; // 1 hour timeout
    private static $config = null;
    private static $lastConnectionTest = null;
    private static $connectionTestCache = null;
    private static $currentServerIndex = 0;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {}
    
    /**
     * Get the singleton instance of ConnectionPool
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get a database connection from the pool
     * 
     * @return PDO The database connection
     * @throws Exception If connection cannot be established
     */
    public static function getConnection() {
        // Load config if not already loaded
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config.php';
        }
        
        // Check if we need to create a new connection or reconnect
        if (self::shouldReconnect()) {
            self::createNewConnectionWithRetry();
        }
        
        // Verify connection is still alive
        if (!self::isConnectionAlive()) {
            self::createNewConnectionWithRetry();
        }
        
        return self::$connection;
    }
    
    /**
     * Check if we should create a new connection
     */
    private static function shouldReconnect() {
        // No connection exists
        if (self::$connection === null) {
            return true;
        }
        
        // Connection has timed out
        if (self::$lastConnectionTime !== null && 
            (time() - self::$lastConnectionTime) > self::$connectionTimeout) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the current connection is alive
     */
    private static function isConnectionAlive() {
        if (self::$connection === null) {
            return false;
        }
        
        try {
            // Simple query to test connection
            $stmt = self::$connection->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            error_log("Connection health check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new connection with retry logic and fallback servers
     */
    private static function createNewConnectionWithRetry() {
        $retryAttempts = isset(self::$config['connection']['retry_attempts']) ? 
            self::$config['connection']['retry_attempts'] : 3;
        $retryDelay = isset(self::$config['connection']['retry_delay']) ? 
            self::$config['connection']['retry_delay'] : 2;
        
        $servers = self::getAvailableServers();
        $lastException = null;
        
        foreach ($servers as $serverIndex => $serverConfig) {
            for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
                try {
                    error_log("ConnectionPool: Attempting connection to server {$serverIndex}, attempt {$attempt}");
                    self::createNewConnection($serverConfig);
                    self::$currentServerIndex = $serverIndex;
                    error_log("ConnectionPool: Successfully connected to server {$serverIndex}");
                    return;
                } catch (Exception $e) {
                    $lastException = $e;
                    error_log("ConnectionPool: Connection attempt {$attempt} failed for server {$serverIndex}: " . $e->getMessage());
                    
                    if ($attempt < $retryAttempts) {
                        sleep($retryDelay);
                    }
                }
            }
        }
        
        // All servers failed
        throw new Exception(
            "Unable to establish database connection to any server. " .
            "Last error: " . ($lastException ? $lastException->getMessage() : 'Unknown error') .
            ". This might be due to network connectivity issues. Please check your internet connection or try switching networks."
        );
    }
    
    /**
     * Get list of available database servers (primary + fallbacks)
     */
    private static function getAvailableServers() {
        $servers = [self::$config['db']];
        
        if (isset(self::$config['db_fallbacks']) && 
            isset(self::$config['connection']['enable_fallbacks']) && 
            self::$config['connection']['enable_fallbacks']) {
            $servers = array_merge($servers, self::$config['db_fallbacks']);
        }
        
        return $servers;
    }
    
    /**
     * Test network connectivity to a server
     */
    private static function testNetworkConnectivity($host, $port, $timeout = 5) {
        $cacheKey = "network_test_{$host}_{$port}";
        $cacheTime = isset(self::$config['connection']['cache_connection_test']) ? 
            self::$config['connection']['cache_connection_test'] : 300;
        
        // Check cache first
        if (self::$connectionTestCache && 
            isset(self::$connectionTestCache[$cacheKey]) && 
            (time() - self::$connectionTestCache[$cacheKey]['time']) < $cacheTime) {
            return self::$connectionTestCache[$cacheKey]['result'];
        }
        
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $result = $connection !== false;
        
        if ($connection) {
            fclose($connection);
        }
        
        // Cache the result
        if (!self::$connectionTestCache) {
            self::$connectionTestCache = [];
        }
        self::$connectionTestCache[$cacheKey] = [
            'result' => $result,
            'time' => time(),
            'error' => $result ? null : $errstr
        ];
        
        return $result;
    }
    
    /**
     * Create a new persistent database connection
     */
    private static function createNewConnection($serverConfig = null) {
        // Use provided server config or default to primary config
        $config = $serverConfig ?: self::$config['db'];
        $timeout = isset(self::$config['connection']['timeout']) ? 
            self::$config['connection']['timeout'] : 30;
        
        try {
            // Test network connectivity first
            if (!self::testNetworkConnectivity($config['host'], $config['port'], 5)) {
                throw new Exception("Network connectivity test failed for {$config['host']}:{$config['port']}");
            }
            
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                $config['host'],
                $config['port'],
                $config['dbname']
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $timeout,
                // CRITICAL: Enable persistent connections to reuse existing connections
                PDO::ATTR_PERSISTENT => true,
                // Connection-specific MySQL settings for optimization
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                // Reduce connection overhead
                PDO::MYSQL_ATTR_COMPRESS => true,
            ];
            
            // Create new persistent connection
            self::$connection = new PDO(
                $dsn, 
                $config['user'], 
                $config['pass'], 
                $options
            );
            
            // Test the connection immediately
            self::$connection->query('SELECT 1');
            
            self::$lastConnectionTime = time();
            
            error_log("ConnectionPool: New persistent connection established successfully");
            
        } catch (PDOException $e) {
            error_log("ConnectionPool: Failed to create connection - " . $e->getMessage());
            
            // Fallback to non-persistent connection if persistent fails
            try {
                $options[PDO::ATTR_PERSISTENT] = false;
                self::$connection = new PDO(
                    $dsn, 
                    $config['user'], 
                    $config['pass'], 
                    $options
                );
                
                self::$lastConnectionTime = time();
                error_log("ConnectionPool: Fallback non-persistent connection established");
                
            } catch (PDOException $fallbackException) {
                error_log("ConnectionPool: Both persistent and non-persistent connections failed");
                throw new Exception(
                    "Unable to establish database connection. " . 
                    "Primary error: " . $e->getMessage() . 
                    " | Fallback error: " . $fallbackException->getMessage()
                );
            }
        }
    }
    
    /**
     * Get connection statistics for monitoring
     */
    public static function getStats() {
        $servers = self::getAvailableServers();
        $currentServer = isset($servers[self::$currentServerIndex]) ? $servers[self::$currentServerIndex] : null;
        
        return [
            'has_connection' => self::$connection !== null,
            'connection_age' => self::$lastConnectionTime ? (time() - self::$lastConnectionTime) : null,
            'is_persistent' => self::$connection ? self::$connection->getAttribute(PDO::ATTR_PERSISTENT) : null,
            'server_info' => self::$connection ? self::$connection->getAttribute(PDO::ATTR_SERVER_INFO) : null,
            'current_server_index' => self::$currentServerIndex,
            'current_server_host' => $currentServer ? $currentServer['host'] : null,
            'total_servers' => count($servers),
            'connection_test_cache' => self::$connectionTestCache
        ];
    }
    
    /**
     * Get detailed connection diagnostics
     */
    public static function getDiagnostics() {
        $servers = self::getAvailableServers();
        $diagnostics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'current_connection' => self::getStats(),
            'server_tests' => []
        ];
        
        foreach ($servers as $index => $server) {
            $test = self::testNetworkConnectivity($server['host'], $server['port'], 5);
            $diagnostics['server_tests'][] = [
                'index' => $index,
                'host' => $server['host'],
                'port' => $server['port'],
                'reachable' => $test,
                'is_current' => $index === self::$currentServerIndex
            ];
        }
        
        return $diagnostics;
    }
    
    /**
     * Force close the current connection (for cleanup)
     */
    public static function closeConnection() {
        if (self::$connection !== null) {
            self::$connection = null;
            self::$lastConnectionTime = null;
            error_log("ConnectionPool: Connection closed manually");
        }
    }
    
    /**
     * Set connection timeout (for testing or specific requirements)
     */
    public static function setConnectionTimeout($seconds) {
        self::$connectionTimeout = $seconds;
    }
}
