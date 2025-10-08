<?php
// schema/advance_salary_enhanced.php - Enhanced advance salary tracking table

return [
    'table_name' => 'advance_salary_enhanced',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "Employee ID"',
        'advance_request_id' => 'VARCHAR(50) UNIQUE NOT NULL COMMENT "Unique advance request identifier"',
        'total_advance_amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Total advance amount granted"',
        'monthly_deduction_amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Monthly deduction amount"',
        'remaining_balance' => 'DECIMAL(12,2) NOT NULL COMMENT "Remaining advance balance"',
        'total_deducted' => 'DECIMAL(12,2) DEFAULT 0 COMMENT "Total amount deducted so far"',
        'start_date' => 'DATE NOT NULL COMMENT "Date when deductions start"',
        'expected_completion_date' => 'DATE NULL COMMENT "Expected completion date"',
        'actual_completion_date' => 'DATE NULL COMMENT "Actual completion date"',
        'status' => 'ENUM("active", "completed", "suspended", "cancelled") DEFAULT "active"',
        'grant_reason' => 'TEXT NULL COMMENT "Reason for advance grant"',
        'emergency_advance' => 'BOOLEAN DEFAULT FALSE COMMENT "Is this an emergency advance"',
        'repayment_months' => 'INT NOT NULL COMMENT "Number of months for repayment"',
        'interest_rate' => 'DECIMAL(5,2) DEFAULT 0 COMMENT "Interest rate if applicable"',
        'interest_amount' => 'DECIMAL(12,2) DEFAULT 0 COMMENT "Total interest amount"',
        'priority_level' => 'ENUM("low", "medium", "high", "urgent") DEFAULT "medium"',
        'approved_by' => 'INT NOT NULL COMMENT "Admin who approved the advance"',
        'approved_at' => 'TIMESTAMP NOT NULL COMMENT "When advance was approved"',
        'created_by' => 'INT NOT NULL COMMENT "Who created the advance request"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'suspended_at' => 'TIMESTAMP NULL COMMENT "When advance was suspended"',
        'suspension_reason' => 'TEXT NULL COMMENT "Reason for suspension"'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL',
        'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_user_status (user_id, status)',
        'INDEX idx_status (status)',
        'INDEX idx_start_date (start_date)',
        'INDEX idx_completion_date (expected_completion_date)',
        'INDEX idx_priority (priority_level)',
        'INDEX idx_approved_by (approved_by)',
        'INDEX idx_emergency (emergency_advance)',
        'INDEX idx_remaining_balance (remaining_balance)'
    ]
];
?>