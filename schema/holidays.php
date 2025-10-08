<?php
// schema/holidays.php
return [
    'holidays' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'holiday_date' => 'DATE NOT NULL',
            'name' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY (holiday_date)'
        ]
    ]
]; 