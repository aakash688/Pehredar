<?php
/**
 * Migration: Create employee_family_references table
 * Date: 2025-01-27
 * Description: Creates the employee_family_references table for storing family reference information
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    // Load schema
    $schema = include __DIR__ . '/../schema/employee_family_references.php';
    $table_name = 'employee_family_references';
    $table_schema = $schema[$table_name];
    
    // Build CREATE TABLE statement
    $columns = [];
    foreach ($table_schema['columns'] as $column_name => $definition) {
        $columns[] = "`{$column_name}` {$definition}";
    }
    
    $constraints = [];
    foreach ($table_schema['constraints'] as $constraint) {
        $constraints[] = $constraint;
    }
    
    $create_sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (\n";
    $create_sql .= "    " . implode(",\n    ", $columns);
    if (!empty($constraints)) {
        $create_sql .= ",\n    " . implode(",\n    ", $constraints);
    }
    $create_sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    // Execute migration
    $db->getPdo()->exec($create_sql);
    
    echo "✓ Migration completed successfully: {$table_name} table created\n";
    
    // Log migration
    $log_file = __DIR__ . '/../logs/migration_' . date('Y-m-d_H-i-s') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = "Migration: Create {$table_name} table\n";
    $log_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_content .= "SQL: " . $create_sql . "\n";
    $log_content .= "Status: SUCCESS\n\n";
    
    file_put_contents($log_file, $log_content);
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    
    // Log error
    $log_file = __DIR__ . '/../logs/migration_error_' . date('Y-m-d_H-i-s') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = "Migration: Create {$table_name} table\n";
    $log_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_content .= "Error: " . $e->getMessage() . "\n";
    $log_content .= "Status: FAILED\n\n";
    
    file_put_contents($log_file, $log_content);
    
    exit(1);
}
