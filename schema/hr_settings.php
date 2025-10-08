<?php
// schema/hr_settings.php
return [
    'hr_settings' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'general_multiplier' => 'DECIMAL(5,2) NOT NULL DEFAULT 1.00',
            'overtime_multiplier' => 'DECIMAL(5,2) NOT NULL DEFAULT 1.50',
            'holiday_multiplier' => 'DECIMAL(5,2) NOT NULL DEFAULT 2.00',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ]
]; 