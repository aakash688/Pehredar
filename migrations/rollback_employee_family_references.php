<?php
/**
 * Rollback Migration: Drop employee_family_references table
 * Date: 2025-01-27
 * Description: Drops the employee_family_references table
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    $table_name = 'employee_family_references';
    
    // Check if table exists before dropping
    $check_sql = "SHOW TABLES LIKE '{$table_name}'";
    $result = $db->query($check_sql)->fetch();
    
    if (!$result) {
        echo "✓ Table {$table_name} does not exist. Nothing to rollback.\n";
        exit(0);
    }
    
    // Drop table
    $drop_sql = "DROP TABLE IF EXISTS `{$table_name}`";
    $db->getPdo()->exec($drop_sql);
    
    echo "✓ Rollback completed successfully: {$table_name} table dropped\n";
    
    // Log rollback
    $log_file = __DIR__ . '/../logs/rollback_' . date('Y-m-d_H-i-s') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = "Rollback: Drop {$table_name} table\n";
    $log_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_content .= "SQL: " . $drop_sql . "\n";
    $log_content .= "Status: SUCCESS\n\n";
    
    file_put_contents($log_file, $log_content);
    
} catch (Exception $e) {
    echo "✗ Rollback failed: " . $e->getMessage() . "\n";
    
    // Log error
    $log_file = __DIR__ . '/../logs/rollback_error_' . date('Y-m-d_H-i-s') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = "Rollback: Drop {$table_name} table\n";
    $log_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_content .= "Error: " . $e->getMessage() . "\n";
    $log_content .= "Status: FAILED\n\n";
    
    file_put_contents($log_file, $log_content);
    
    exit(1);
}
