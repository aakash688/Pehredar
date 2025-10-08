<?php
/**
 * Migration: Add watermark and service charges fields to company_settings table
 * Created: 2024
 * Description: Adds watermark_image_path, service_charges_enabled, and service_charges_percentage columns
 */

require_once __DIR__ . '/../helpers/database.php';

function addWatermarkServiceChargesFields() {
    $db = new Database();
    
    try {
        // Check if columns already exist
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'watermark_image_path'")->fetchAll();
        if (empty($columns)) {
            echo "Adding watermark_image_path column...\n";
            $db->query("ALTER TABLE company_settings ADD COLUMN watermark_image_path VARCHAR(255) DEFAULT NULL AFTER signature_image");
        } else {
            echo "watermark_image_path column already exists.\n";
        }
        
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_enabled'")->fetchAll();
        if (empty($columns)) {
            echo "Adding service_charges_enabled column...\n";
            $db->query("ALTER TABLE company_settings ADD COLUMN service_charges_enabled TINYINT(1) DEFAULT 0 COMMENT '1=Service charges enabled, 0=Disabled' AFTER watermark_image_path");
        } else {
            echo "service_charges_enabled column already exists.\n";
        }
        
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_percentage'")->fetchAll();
        if (empty($columns)) {
            echo "Adding service_charges_percentage column...\n";
            $db->query("ALTER TABLE company_settings ADD COLUMN service_charges_percentage DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Service charges percentage (e.g., 10.00 for 10%)' AFTER service_charges_enabled");
        } else {
            echo "service_charges_percentage column already exists.\n";
        }
        
        echo "Migration completed successfully!\n";
        
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        return false;
    }
    
    return true;
}

function rollbackWatermarkServiceChargesFields() {
    $db = new Database();
    
    try {
        // Remove columns if they exist
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_percentage'")->fetchAll();
        if (!empty($columns)) {
            echo "Removing service_charges_percentage column...\n";
            $db->query("ALTER TABLE company_settings DROP COLUMN service_charges_percentage");
        }
        
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_enabled'")->fetchAll();
        if (!empty($columns)) {
            echo "Removing service_charges_enabled column...\n";
            $db->query("ALTER TABLE company_settings DROP COLUMN service_charges_enabled");
        }
        
        $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'watermark_image_path'")->fetchAll();
        if (!empty($columns)) {
            echo "Removing watermark_image_path column...\n";
            $db->query("ALTER TABLE company_settings DROP COLUMN watermark_image_path");
        }
        
        echo "Rollback completed successfully!\n";
        
    } catch (Exception $e) {
        echo "Rollback failed: " . $e->getMessage() . "\n";
        return false;
    }
    
    return true;
}

// Run migration if called directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    echo "Running watermark and service charges migration...\n";
    
    if (isset($argv[1]) && $argv[1] === 'rollback') {
        rollbackWatermarkServiceChargesFields();
    } else {
        addWatermarkServiceChargesFields();
    }
}
?>
