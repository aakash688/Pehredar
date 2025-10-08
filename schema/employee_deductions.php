<?php
// schema/employee_deductions.php - Individual employee deductions table

return [
    'table_name' => 'employee_deductions',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "Employee ID"',
        'deduction_type_id' => 'INT NOT NULL COMMENT "Type of deduction"',
        'amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Deduction amount"',
        'percentage' => 'DECIMAL(5,2) NULL COMMENT "Percentage if applicable"',
        'base_amount' => 'DECIMAL(12,2) NULL COMMENT "Base amount for percentage calculation"',
        'month' => 'VARCHAR(7) NOT NULL COMMENT "Month for deduction (YYYY-MM format)"',
        'year' => 'INT NOT NULL COMMENT "Year for deduction"',
        'reason' => 'TEXT NULL COMMENT "Reason for deduction"',
        'is_recurring' => 'BOOLEAN DEFAULT FALSE COMMENT "Is this a recurring deduction"',
        'is_bulk_applied' => 'BOOLEAN DEFAULT FALSE COMMENT "Applied via bulk operation"',
        'bulk_operation_id' => 'VARCHAR(50) NULL COMMENT "Bulk operation identifier"',
        'status' => 'ENUM("pending", "applied", "cancelled") DEFAULT "pending"',
        'applied_at' => 'TIMESTAMP NULL COMMENT "When deduction was applied"',
        'created_by' => 'INT NOT NULL COMMENT "Admin who created the deduction"',
        'approved_by' => 'INT NULL COMMENT "Admin who approved the deduction"',
        'approved_at' => 'TIMESTAMP NULL COMMENT "When deduction was approved"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(id) ON DELETE RESTRICT',
        'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
        'FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_user_month (user_id, month)',
        'INDEX idx_deduction_type (deduction_type_id)',
        'INDEX idx_month_year (month, year)',
        'INDEX idx_status (status)',
        'INDEX idx_bulk_operation (bulk_operation_id)',
        'INDEX idx_created_by (created_by)',
        'INDEX idx_user_type_month (user_id, deduction_type_id, month)',
        'UNIQUE KEY unique_user_type_month (user_id, deduction_type_id, month, is_recurring)'
    ]
];
?>