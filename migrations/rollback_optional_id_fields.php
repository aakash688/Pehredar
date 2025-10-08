<?php
/**
 * Rollback Migration: Fix Optional ID Fields
 * Date: 2025-01-27
 * Description: Rollback migration for optional ID fields fix
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    echo "=== Rollback Migration: Optional ID Fields Fix ===\n\n";
    
    echo "WARNING: This rollback will remove UNIQUE constraints from optional ID fields.\n";
    echo "This may allow duplicate values in the future.\n\n";
    
    // Remove UNIQUE constraints (if they exist)
    $optional_fields = ['passport_number', 'voter_id_number', 'pf_number', 'esic_number', 'uan_number'];
    
    foreach ($optional_fields as $field) {
        try {
            // Check if UNIQUE constraint exists
            $result = $db->query("SHOW INDEX FROM users WHERE Non_unique = 0 AND Column_name = '$field'");
            if ($result->fetch()) {
                $db->query("ALTER TABLE users DROP INDEX $field");
                echo "✓ Removed UNIQUE constraint for $field\n";
            } else {
                echo "✓ No UNIQUE constraint found for $field\n";
            }
        } catch (Exception $e) {
            echo "✗ Error removing UNIQUE constraint for $field: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Rollback completed ===\n";
    echo "Note: Data cleanup (empty strings to NULL) cannot be easily rolled back.\n";
    echo "If you need to restore empty strings, you would need to manually update the data.\n";
    
} catch (Exception $e) {
    echo "✗ Rollback failed: " . $e->getMessage() . "\n";
    exit(1);
}
