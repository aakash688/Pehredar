<?php
// schema/attendance_audit.php
return [
    'attendance_audit' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'attendance_id' => 'INT NOT NULL',
            'changed_by' => 'INT NOT NULL',
            'old_attendance_master_id' => 'INT NULL',
            'new_attendance_master_id' => 'INT NULL',
            'change_timestamp' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'source' => "ENUM('web', 'mobile') NOT NULL",
            'reason_for_change' => 'TEXT NULL',
            'change_details' => 'TEXT NULL COMMENT "JSON encoded details for shift changes or other complex changes"'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE',
            'FOREIGN KEY (old_attendance_master_id) REFERENCES attendance_master(id) ON DELETE SET NULL',
            'FOREIGN KEY (new_attendance_master_id) REFERENCES attendance_master(id) ON DELETE SET NULL'
        ]
    ]
];
?> 