<?php
/**
 * Migration: Add Service Charges fields to society_onboarding_data table
 * 
 * This migration adds two new columns:
 * - service_charges_enabled: TINYINT(1) to indicate if service charges are applicable
 * - service_charges_percentage: DECIMAL(5,2) to store the percentage value
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    echo "Starting migration: Add Service Charges fields to society_onboarding_data...\n";
    
    // Check if columns already exist
    $checkColumns = $db->query("SHOW COLUMNS FROM society_onboarding_data LIKE 'service_charges_enabled'")->fetch();
    if ($checkColumns) {
        echo "Column 'service_charges_enabled' already exists. Skipping migration.\n";
        exit(0);
    }
    
    // Add the new columns
    $sql = "
        ALTER TABLE society_onboarding_data
        ADD COLUMN service_charges_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER compliance_status,
        ADD COLUMN service_charges_percentage DECIMAL(5,2) NULL DEFAULT NULL AFTER service_charges_enabled
    ";
    
    $db->query($sql);
    
    echo "✅ Successfully added service_charges_enabled and service_charges_percentage columns to society_onboarding_data table.\n";
    
    // Verify the columns were added
    $columns = $db->query("SHOW COLUMNS FROM society_onboarding_data")->fetchAll();
    $newColumns = array_filter($columns, function($col) {
        return in_array($col['Field'], ['service_charges_enabled', 'service_charges_percentage']);
    });
    
    echo "✅ Verification: Added columns:\n";
    foreach ($newColumns as $column) {
        echo "   - {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Default']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
