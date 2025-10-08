<?php
// schema/attendance_new.php
return [
    'attendance' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'user_id' => 'INT NOT NULL',
            'society_id' => 'INT NULL',
            'attendance_master_id' => 'INT NOT NULL',
            'attendance_date' => 'DATE NOT NULL',
            'shift_id' => 'INT NULL',
            'shift_start' => 'TIME NULL',
            'shift_end' => 'TIME NULL',
            'marked_by' => 'INT NULL',
            'source' => "ENUM('web', 'mobile') DEFAULT 'web'",
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'last_modified_by' => 'INT NULL'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'FOREIGN KEY (attendance_master_id) REFERENCES attendance_master(id)',
            'FOREIGN KEY (society_id) REFERENCES society_onboarding_data(id)',
            'FOREIGN KEY (shift_id) REFERENCES shift_master(id) ON DELETE SET NULL'
            // No unique constraint on (user_id, attendance_date) to allow multiple entries per day
        ]
    ]
];
?> 