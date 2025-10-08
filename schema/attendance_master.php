<?php
// schema/attendance_master.php
return [
    'attendance_master' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'code' => 'VARCHAR(10) NOT NULL',
            'name' => 'VARCHAR(50) NOT NULL',
            'description' => 'TEXT',
            'multiplier' => 'DECIMAL(3,2) NOT NULL DEFAULT 1.0',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY (code)'
        ]
    ]
]; 