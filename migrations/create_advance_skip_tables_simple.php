<?php
/**
 * Migration: Create Advance Skip Request Tables (Simple Version)
 * Creates tables for managing advance salary skip requests without foreign keys
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    echo "Creating advance skip request tables (simple version)...\n";
    
    // Create advance_skip_requests table without foreign keys
    $createSkipRequestsTable = "
        CREATE TABLE IF NOT EXISTS advance_skip_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            advance_payment_id INT NOT NULL COMMENT 'Reference to advance_payments table',
            skip_month VARCHAR(7) NOT NULL COMMENT 'Month to skip (YYYY-MM)',
            reason TEXT NOT NULL COMMENT 'Reason for skip request',
            requested_by INT NOT NULL COMMENT 'Employee who requested the skip',
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            approved_by INT NULL COMMENT 'Admin who approved/rejected',
            approved_at TIMESTAMP NULL COMMENT 'When request was approved',
            rejected_at TIMESTAMP NULL COMMENT 'When request was rejected',
            cancelled_at TIMESTAMP NULL COMMENT 'When request was cancelled',
            approval_notes TEXT NULL COMMENT 'Notes from approver',
            rejection_reason TEXT NULL COMMENT 'Reason for rejection',
            cancellation_reason TEXT NULL COMMENT 'Reason for cancellation',
            cancelled_by INT NULL COMMENT 'Who cancelled the request',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_skip_month (advance_payment_id, skip_month),
            INDEX idx_advance_payment (advance_payment_id),
            INDEX idx_status (status),
            INDEX idx_skip_month (skip_month),
            INDEX idx_requested_by (requested_by),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->query($createSkipRequestsTable);
    echo "✓ Created advance_skip_requests table\n";
    
    // Create advance_skip_records table without foreign keys
    $createSkipRecordsTable = "
        CREATE TABLE IF NOT EXISTS advance_skip_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            advance_payment_id INT NOT NULL COMMENT 'Reference to advance_payments table',
            skip_month VARCHAR(7) NOT NULL COMMENT 'Month that was skipped (YYYY-MM)',
            skip_request_id INT NOT NULL COMMENT 'Reference to advance_skip_requests table',
            monthly_deduction_amount DECIMAL(12,2) NOT NULL COMMENT 'Amount that would have been deducted',
            reason TEXT NOT NULL COMMENT 'Reason for the skip',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_skip_record (advance_payment_id, skip_month),
            INDEX idx_advance_payment (advance_payment_id),
            INDEX idx_skip_month (skip_month),
            INDEX idx_skip_request (skip_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->query($createSkipRecordsTable);
    echo "✓ Created advance_skip_records table\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Advance skip request system is now ready to use.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

