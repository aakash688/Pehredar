<?php
// schema/invoices.php
return [
    'invoices' => [
        'columns' => [
            'id' => 'INT(11) AUTO_INCREMENT',
            'client_id' => 'INT(11) NOT NULL',
            'month' => 'VARCHAR(7) NOT NULL COMMENT "Format: YYYY-MM"',
            'amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'is_gst_applicable' => 'TINYINT(1) DEFAULT 1 COMMENT "1=GST Applicable, 0=GST Not Applicable"',
            'gst_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'total_with_gst' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'status' => 'ENUM("pending", "paid") DEFAULT "pending"',
            'generation_type' => 'ENUM("auto", "manual", "modified") DEFAULT "auto"',
            'paid_at' => 'DATETIME NULL',
            'payment_method' => 'VARCHAR(50) NULL',
            'payment_notes' => 'TEXT NULL',
            'tds_amount' => 'DECIMAL(12,2) DEFAULT 0.00 COMMENT "TDS amount deducted"',
            'amount_received' => 'DECIMAL(12,2) DEFAULT 0.00 COMMENT "Actual amount received"',
            'short_balance' => 'DECIMAL(12,2) DEFAULT 0.00 COMMENT "Short balance amount"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY (client_id, month)',
            'FOREIGN KEY (client_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE'
        ]
    ]
]; 