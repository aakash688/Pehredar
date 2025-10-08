<?php
// schema/advance_deduction_history.php - Advance deduction history tracking table

return [
    'table_name' => 'advance_deduction_history',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'advance_id' => 'INT NOT NULL COMMENT "Reference to advance_salary_enhanced table"',
        'salary_record_id' => 'INT NULL COMMENT "Reference to salary_records table"',
        'deduction_amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Amount deducted in this transaction"',
        'interest_deducted' => 'DECIMAL(12,2) DEFAULT 0 COMMENT "Interest amount deducted"',
        'principal_deducted' => 'DECIMAL(12,2) NOT NULL COMMENT "Principal amount deducted"',
        'remaining_balance_before' => 'DECIMAL(12,2) NOT NULL COMMENT "Balance before this deduction"',
        'remaining_balance_after' => 'DECIMAL(12,2) NOT NULL COMMENT "Balance after this deduction"',
        'deduction_month' => 'VARCHAR(7) NOT NULL COMMENT "Month of deduction (YYYY-MM)"',
        'deduction_year' => 'INT NOT NULL COMMENT "Year of deduction"',
        'payment_number' => 'INT NOT NULL COMMENT "Payment sequence number"',
        'is_partial_payment' => 'BOOLEAN DEFAULT FALSE COMMENT "Is this a partial payment"',
        'adjustment_reason' => 'TEXT NULL COMMENT "Reason for any adjustment"',
        'processed_by' => 'INT NULL COMMENT "Admin who processed the deduction"',
        'processed_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT "When deduction was processed"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (advance_id) REFERENCES advance_salary_enhanced(id) ON DELETE CASCADE',
        'FOREIGN KEY (salary_record_id) REFERENCES salary_records(id) ON DELETE SET NULL',
        'FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_advance_month (advance_id, deduction_month)',
        'INDEX idx_advance_payment (advance_id, payment_number)',
        'INDEX idx_salary_record (salary_record_id)',
        'INDEX idx_deduction_month (deduction_month)',
        'INDEX idx_processed_by (processed_by)',
        'INDEX idx_processed_at (processed_at)'
    ]
];
?>