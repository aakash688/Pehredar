<?php
// schema/shift_master.php
return [
    'shift_master' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'shift_name' => 'VARCHAR(100) NOT NULL',
            'start_time' => 'TIME NOT NULL',
            'end_time' => 'TIME NOT NULL',
            'description' => 'TEXT NULL',
            'is_active' => 'TINYINT(1) DEFAULT 1',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY unique_shift_name (shift_name)'
        ]
    ]
];
?> 