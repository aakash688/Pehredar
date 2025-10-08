<?php
// schema/bonus_records.php - Bonus records management table

return [
    'table_name' => 'bonus_records',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "Employee receiving bonus"',
        'bonus_type' => 'VARCHAR(100) NOT NULL COMMENT "Type of bonus (Diwali, Performance, etc.)"',
        'bonus_category' => 'ENUM("fixed", "percentage") NOT NULL COMMENT "Fixed amount or percentage based"',
        'amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Bonus amount"',
        'percentage' => 'DECIMAL(5,2) NULL COMMENT "Percentage if category is percentage"',
        'base_amount' => 'DECIMAL(12,2) NULL COMMENT "Base amount for percentage calculation"',
        'month' => 'VARCHAR(7) NOT NULL COMMENT "Month for bonus (YYYY-MM format)"',
        'year' => 'INT NOT NULL COMMENT "Year for bonus"',
        'description' => 'TEXT NULL COMMENT "Bonus description or reason"',
        'is_bulk_applied' => 'BOOLEAN DEFAULT FALSE COMMENT "Applied via bulk operation"',
        'bulk_operation_id' => 'VARCHAR(50) NULL COMMENT "Bulk operation identifier"',
        'created_by' => 'INT NOT NULL COMMENT "Admin who created the bonus"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_user_month (user_id, month)',
        'INDEX idx_bonus_type (bonus_type)',
        'INDEX idx_month_year (month, year)',
        'INDEX idx_bulk_operation (bulk_operation_id)',
        'INDEX idx_created_by (created_by)'
    ]
];
?>