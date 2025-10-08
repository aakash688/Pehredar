<?php
// schema/employee_family_references.php
return [
    'employee_family_references' => [
        'columns' => [
            'id' => 'BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'employee_id' => 'BIGINT(20) UNSIGNED NOT NULL',
            'reference_index' => 'TINYINT(1) NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'relation' => 'VARCHAR(100) NOT NULL',
            'mobile_primary' => 'VARCHAR(20) NOT NULL',
            'mobile_secondary' => 'VARCHAR(20) NULL',
            'address' => 'TEXT NOT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'created_by' => 'BIGINT(20) UNSIGNED NULL',
            'updated_by' => 'BIGINT(20) UNSIGNED NULL'
        ],
        'constraints' => [
            'FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE',
            'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
            'FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL',
            'UNIQUE KEY unique_employee_reference (employee_id, reference_index)',
            'INDEX idx_employee_id (employee_id)',
            'INDEX idx_reference_index (reference_index)'
        ]
    ]
];