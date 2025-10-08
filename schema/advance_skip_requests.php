<?php
// schema/advance_skip_requests.php - Advance skip request management tables

return [
    'advance_skip_requests' => [
        'columns' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'advance_payment_id' => 'INT NOT NULL COMMENT "Reference to advance_payments table"',
            'skip_month' => 'VARCHAR(7) NOT NULL COMMENT "Month to skip (YYYY-MM)"',
            'reason' => 'TEXT NOT NULL COMMENT "Reason for skip request"',
            'requested_by' => 'INT NOT NULL COMMENT "Employee who requested the skip"',
            'status' => 'ENUM("pending", "approved", "rejected") DEFAULT "pending"',
            'approved_by' => 'INT NULL COMMENT "Admin who approved/rejected"',
            'approved_at' => 'TIMESTAMP NULL COMMENT "When request was approved"',
            'rejected_at' => 'TIMESTAMP NULL COMMENT "When request was rejected"',
            'approval_notes' => 'TEXT NULL COMMENT "Notes from approver"',
            'rejection_reason' => 'TEXT NULL COMMENT "Reason for rejection"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (advance_payment_id) REFERENCES advance_payments(id) ON DELETE CASCADE',
            'FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE',
            'FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL',
            'UNIQUE KEY unique_skip_month (advance_payment_id, skip_month)'
        ],
        'indexes' => [
            'INDEX idx_advance_payment (advance_payment_id)',
            'INDEX idx_status (status)',
            'INDEX idx_skip_month (skip_month)',
            'INDEX idx_requested_by (requested_by)',
            'INDEX idx_created_at (created_at)'
        ]
    ],
    
    'advance_skip_records' => [
        'columns' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'advance_payment_id' => 'INT NOT NULL COMMENT "Reference to advance_payments table"',
            'skip_month' => 'VARCHAR(7) NOT NULL COMMENT "Month that was skipped (YYYY-MM)"',
            'skip_request_id' => 'INT NOT NULL COMMENT "Reference to advance_skip_requests table"',
            'monthly_deduction_amount' => 'DECIMAL(12,2) NOT NULL COMMENT "Amount that would have been deducted"',
            'reason' => 'TEXT NOT NULL COMMENT "Reason for the skip"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (advance_payment_id) REFERENCES advance_payments(id) ON DELETE CASCADE',
            'FOREIGN KEY (skip_request_id) REFERENCES advance_skip_requests(id) ON DELETE CASCADE',
            'UNIQUE KEY unique_skip_record (advance_payment_id, skip_month)'
        ],
        'indexes' => [
            'INDEX idx_advance_payment (advance_payment_id)',
            'INDEX idx_skip_month (skip_month)',
            'INDEX idx_skip_request (skip_request_id)'
        ]
    ]
];
?>

