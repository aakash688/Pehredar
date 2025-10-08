<?php

class ConnectionPool {
    private static $instance = null;
    private $connections = [];
    private $maxConnections = 10;
    private $currentConnections = 0;
    private $host = 'localhost';
    private $port = '3306';
    private $username = 'root';
    private $password = '';
    private $database = 'gaurdadmin';
    private $charset = 'utf8mb4';

    private function __construct() {
        // Private constructor for singleton pattern
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Try to get an existing connection from the pool
        if (!empty($this->connections)) {
            return array_pop($this->connections);
        }

        // Create a new connection if pool is not full
        if ($this->currentConnections < $this->maxConnections) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
                $pdo = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
                
                $this->currentConnections++;
                return $pdo;
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }

        // If pool is full, wait and retry
        usleep(10000); // Wait 10ms
        return $this->getConnection();
    }

    public function releaseConnection($connection) {
        if ($connection instanceof PDO && count($this->connections) < $this->maxConnections) {
            $this->connections[] = $connection;
        } else {
            $this->currentConnections--;
        }
    }

    public function closeAllConnections() {
        $this->connections = [];
        $this->currentConnections = 0;
    }

    public function getConnectionCount() {
        return $this->currentConnections;
    }

    public function getPoolSize() {
        return count($this->connections);
    }
}

?>

