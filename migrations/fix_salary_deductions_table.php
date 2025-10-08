<?php
// migrations/fix_salary_deductions_table.php
require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();

    echo "Fixing salary_deductions table structure...\n";

    // First, backup the existing table (rename it)
    $db->query("RENAME TABLE salary_deductions TO salary_deductions_old");
    echo "✓ Renamed existing salary_deductions table to salary_deductions_old\n";

    // Create the new salary_deductions table with the correct structure
    $createSalaryDeductionsTable = "
        CREATE TABLE salary_deductions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            salary_record_id INT NOT NULL COMMENT 'Foreign key to salary_records table',
            deduction_master_id INT NOT NULL COMMENT 'Foreign key to deduction_master table',
            deduction_amount DECIMAL(10,2) NOT NULL COMMENT 'Amount of deduction',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_salary_deduction (salary_record_id, deduction_master_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $db->query($createSalaryDeductionsTable);
    echo "✓ Created new salary_deductions table with correct structure\n";

    // Add foreign key constraints
    $db->query("
        ALTER TABLE salary_deductions 
        ADD CONSTRAINT fk_salary_deductions_salary_record 
        FOREIGN KEY (salary_record_id) REFERENCES salary_records(id) ON DELETE CASCADE
    ");
    echo "✓ Added foreign key constraint for salary_record_id\n";

    $db->query("
        ALTER TABLE salary_deductions 
        ADD CONSTRAINT fk_salary_deductions_deduction_master 
        FOREIGN KEY (deduction_master_id) REFERENCES deduction_master(id) ON DELETE CASCADE
    ");
    echo "✓ Added foreign key constraint for deduction_master_id\n";

    echo "\n✅ Migration completed successfully!\n";
    echo "The salary_deductions table now has the correct structure for the deduction master system.\n";
    echo "The old table has been renamed to salary_deductions_old for backup.\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    
    // Try to restore the old table if something went wrong
    try {
        $db->query("RENAME TABLE salary_deductions_old TO salary_deductions");
        echo "✓ Restored original salary_deductions table\n";
    } catch (Exception $e2) {
        echo "❌ Failed to restore original table: " . $e2->getMessage() . "\n";
    }
}
?>
