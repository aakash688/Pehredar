<?php

// Initialize database schema
require_once 'Database/SchemaManager.php';

try {
    $schemaManager = new SchemaManager();
    $result = $schemaManager->syncSchema();
    
    if ($result['success']) {
        error_log("Database schema initialized successfully");
    } else {
        error_log("Database schema initialization failed: " . $result['message']);
    }
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}

?>

