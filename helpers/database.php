<?php
// helpers/database.php

// Load the ConnectionPool class
require_once __DIR__ . '/ConnectionPool.php';

/**
 * Gets a database connection from the connection pool.
 * This function now uses connection pooling to dramatically reduce
 * the number of database connections and solve the "max connections per hour" issue.
 * 
 * @return PDO The PDO database connection object.
 */
function get_db_connection() {
    try {
        return ConnectionPool::getConnection();
    } catch (Exception $e) {
        error_log("Failed to get connection from pool: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Legacy function - kept for backward compatibility
 * Now redirects to the connection pool
 * 
 * @deprecated Use get_db_connection() instead
 * @return PDO The PDO database connection object.
 */
function create_db_connection() {
    return get_db_connection();
}

/**
 * Database wrapper class for simplified database operations
 */
class Database {
    private $pdo;
    
    /**
     * Constructor - establishes database connection
     */
    public function __construct() {
        $this->pdo = get_db_connection();
    }
    
    /**
     * Expose the PDO object for advanced operations like transactions
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Execute a query with parameters
     * 
     * @param string $sql SQL query to execute
     * @param array $params Parameters for the query
     * @return PDOStatement The statement object
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Prepare a SQL statement
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * Get the ID of the last inserted row
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}

/**
 * Creates a table in the database if it does not already exist.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $tableName The name of the table to create.
 * @param array $schema The schema definition for the table.
 */
function create_table(PDO $pdo, $tableName, array $schema) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
        // Log instead of echoing to avoid polluting JSON responses
        error_log("INFO: Table `{$tableName}` already exists.");
    } catch (Exception $e) {
        // Table does not exist, so create it.
        $sql = "CREATE TABLE `{$tableName}` (";
        $columns = [];
        foreach ($schema['columns'] as $colName => $colDef) {
            $columns[] = "`{$colName}` {$colDef}";
        }
        $sql .= implode(', ', $columns);

        if (!empty($schema['constraints'])) {
            $sql .= ', ' . implode(', ', $schema['constraints']);
        }
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
        // Log instead of echoing to avoid polluting JSON responses
        error_log("SUCCESS: Created table `{$tableName}`.");
    }
} 

/**
 * Calculate client status based on contract expiry date
 * @param string|null $contract_expiry_date The contract expiry date in Y-m-d format
 * @return array Returns array with status and expiry_date info
 */
function calculate_client_status($contract_expiry_date) {
    // Initialize return array
    $status_info = [
        'status' => 'Unknown',
        'status_class' => 'text-secondary',
        'expiry_date' => null
    ];

    // If no contract expiry date is set
    if (empty($contract_expiry_date)) {
        return $status_info;
    }

    try {
        // Convert dates to DateTime objects for comparison
        $today = new DateTime('today');
        $expiry_date = new DateTime($contract_expiry_date);
        $ninety_days_from_now = (new DateTime('today'))->modify('+90 days');

        // Store formatted expiry date
        $status_info['expiry_date'] = $expiry_date->format('Y-m-d');

        // Calculate the status based on date comparison
        if ($expiry_date < $today) {
            // Contract has expired
            $status_info['status'] = 'Expired';
            $status_info['status_class'] = 'text-danger';
        } elseif ($expiry_date <= $ninety_days_from_now) {
            // Contract will expire within 90 days
            $status_info['status'] = 'About to Expire';
            $status_info['status_class'] = 'text-warning';
        } else {
            // Contract is ongoing (more than 90 days remaining)
            $status_info['status'] = 'Ongoing';
            $status_info['status_class'] = 'text-success';
        }
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Error calculating client status: " . $e->getMessage());
    }

    return $status_info;
} 