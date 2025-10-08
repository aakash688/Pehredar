<?php
// schema/salary_records.php
return [
    'salary_records' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'user_id' => 'INT NOT NULL COMMENT "Foreign key to users table"',
            'month' => 'INT NOT NULL COMMENT "Month of salary (1-12)"',
            'year' => 'INT NOT NULL COMMENT "Year of salary"',
            'base_salary' => 'DECIMAL(10,2) NOT NULL COMMENT "Monthly base salary"',
            'total_working_days' => 'INT NOT NULL COMMENT "Total working days in the month"',
            'attendance_present_days' => 'INT NOT NULL COMMENT "Days employee was present"',
            'attendance_absent_days' => 'INT NOT NULL COMMENT "Days employee was absent"',
            'attendance_holiday_days' => 'INT NOT NULL COMMENT "Holiday days"',
            'attendance_double_shift_days' => 'INT NOT NULL COMMENT "Double shift days"',
            'attendance_multiplier_total' => 'DECIMAL(5,2) NOT NULL COMMENT "Total attendance multiplier"',
            'calculated_salary' => 'DECIMAL(10,2) NOT NULL COMMENT "Calculated salary based on attendance"',
            'additional_bonuses' => 'DECIMAL(10,2) DEFAULT 0 COMMENT "Additional bonuses"',
            'deductions' => 'DECIMAL(10,2) DEFAULT 0 COMMENT "Salary deductions"',
            'advance_salary_deducted' => 'DECIMAL(10,2) DEFAULT 0 COMMENT "Advance salary deductions"',
            'final_salary' => 'DECIMAL(10,2) NOT NULL COMMENT "Final salary after calculations"',
            'auto_generated' => 'BOOLEAN DEFAULT TRUE COMMENT "Whether salary was auto-generated"',
            'manually_modified' => 'BOOLEAN DEFAULT FALSE COMMENT "Whether salary was manually edited"',
            'disbursement_status' => 'ENUM("pending", "disbursed") DEFAULT "pending" COMMENT "Disbursement status"',
            'disbursed_by' => 'INT COMMENT "User ID who disbursed the salary"',
            'disbursed_at' => 'TIMESTAMP NULL COMMENT "Disbursement timestamp"',
            'modified_by' => 'INT COMMENT "User ID who modified the record"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY unique_user_month_year (user_id, month, year)',
            'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
            'FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL',
            'FOREIGN KEY (disbursed_by) REFERENCES users(id) ON DELETE SET NULL'
        ]
    ]
]; 