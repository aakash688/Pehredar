<?php
// migrations/create_deduction_master_tables.php
require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    // Create deduction_master table
    $createDeductionMasterTable = "
        CREATE TABLE IF NOT EXISTS deduction_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deduction_name VARCHAR(100) NOT NULL COMMENT 'Name of the deduction type',
            deduction_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Short code for the deduction',
            description TEXT COMMENT 'Description of the deduction',
            is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether deduction is active',
            created_by INT COMMENT 'User ID who created the deduction',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->query($createDeductionMasterTable);
    echo "✓ Created deduction_master table\n";
    
    // Create salary_deductions table
    $createSalaryDeductionsTable = "
        CREATE TABLE IF NOT EXISTS salary_deductions (
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
    echo "✓ Created salary_deductions table\n";
    
    // Insert some sample deduction types
    $sampleDeductions = [
        ['VCS', 'VCS', 'Vehicle Cleaning Service'],
        ['UNIFORM', 'UNIF', 'Uniform Charges'],
        ['SHOES', 'SHOES', 'Shoes/Footwear'],
        ['ID_CARD', 'ID', 'ID Card Charges'],
        ['TRAINING', 'TRAIN', 'Training Charges']
    ];
    
    $insertSample = "
        INSERT IGNORE INTO deduction_master (deduction_name, deduction_code, description, is_active, created_by) 
        VALUES (?, ?, ?, 1, 1)
    ";
    
    foreach ($sampleDeductions as $deduction) {
        $db->query($insertSample, $deduction);
    }
    echo "✓ Inserted sample deduction types\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Deduction Master tables created with sample data.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
