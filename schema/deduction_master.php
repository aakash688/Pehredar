<?php
// schema/deduction_master.php
return [
    '   ' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'deduction_name' => 'VARCHAR(100) NOT NULL COMMENT "Name of the deduction type"',
            'deduction_code' => 'VARCHAR(20) NOT NULL UNIQUE COMMENT "Short code for the deduction"',
            'description' => 'TEXT COMMENT "Description of the deduction"',
            'is_active' => 'BOOLEAN DEFAULT TRUE COMMENT "Whether deduction is active"',
            'created_by' => 'INT COMMENT "User ID who created the deduction"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY unique_deduction_code (deduction_code)',
            'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
        ]
    ],
    'salary_deductions' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'salary_record_id' => 'INT NOT NULL COMMENT "Foreign key to salary_records table"',
            'deduction_master_id' => 'INT NOT NULL COMMENT "Foreign key to deduction_master table"',
            'deduction_amount' => 'DECIMAL(10,2) NOT NULL COMMENT "Amount of deduction"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY unique_salary_deduction (salary_record_id, deduction_master_id)',
            'FOREIGN KEY (salary_record_id) REFERENCES salary_records(id) ON DELETE CASCADE',
            'FOREIGN KEY (deduction_master_id) REFERENCES deduction_master(id) ON DELETE CASCADE'
        ]
    ]
];
