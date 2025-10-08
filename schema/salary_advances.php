<?php
// schema/salary_advances.php

return [
    'salary_advances' => [
        'columns' => [
            'id' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT UNSIGNED NOT NULL',
            'amount' => 'DECIMAL(10, 2) NOT NULL',
            'remaining_amount' => 'DECIMAL(10, 2) NOT NULL',
            'status' => "ENUM('Active', 'Completed', 'Adjusted') DEFAULT 'Active'",
            'notes' => 'TEXT NULL',
            'created_by' => 'INT UNSIGNED NOT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'CONSTRAINT fk_salary_advances_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE',
            'CONSTRAINT fk_salary_advances_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE'
        ],
        'indexes' => [
            'idx_user_id' => 'INDEX (user_id)',
            'idx_created_by' => 'INDEX (created_by)',
            'idx_status' => 'INDEX (status)',
            'idx_created_at' => 'INDEX (created_at)'
        ]
    ]
]; 