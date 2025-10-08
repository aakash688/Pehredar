<?php
/**
 * Shared Database Helper for Mobile APIs
 * 
 * This file provides optimized database connection management for all mobile API endpoints.
 * It uses the ConnectionPool to ensure efficient connection reuse and solves the 
 * "max connections per hour" issue on shared hosting.
 */

require_once __DIR__ . '/../../helpers/ConnectionPool.php';

/**
 * Get optimized database connection for mobile APIs
 * 
 * @return PDO The database connection from the pool
 * @throws Exception If connection cannot be established
 */
function get_mobile_api_db_connection() {
    return ConnectionPool::getConnection();
}

/**
 * Create PDO connection with mobile API specific optimizations
 * This is a legacy function that now uses the connection pool
 * 
 * @return PDO The database connection
 */
function create_mobile_api_pdo() {
    return get_mobile_api_db_connection();
}

/**
 * Get database connection for mobile API with error handling
 * Returns a standardized error response if connection fails
 * 
 * @return PDO|null The database connection or null if failed
 */
function get_api_db_connection_safe() {
    try {
        return get_mobile_api_db_connection();
    } catch (Exception $e) {
        error_log("Mobile API DB Connection Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed.',
            'error_code' => 'DB_CONNECTION_FAILED'
        ]);
        exit();
    }
}
