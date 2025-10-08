<?php
// schema/users.php
return [
    'users' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'first_name' => 'VARCHAR(100) NOT NULL',
            'surname' => 'VARCHAR(100) NOT NULL',
            'date_of_birth' => 'DATE NOT NULL',
            'gender' => "ENUM('Male', 'Female', 'Other') NOT NULL",
            'mobile_number' => 'VARCHAR(20) NOT NULL',
            'email_id' => 'VARCHAR(255) NOT NULL',
            'address' => 'TEXT NOT NULL',
            'permanent_address' => 'TEXT NOT NULL',
            'aadhar_number' => 'VARCHAR(255) NULL',
            'pan_number' => 'VARCHAR(50) NULL',
            'voter_id_number' => 'VARCHAR(50) NULL',
            'passport_number' => 'VARCHAR(50) NULL',
            'highest_qualification' => 'VARCHAR(255) NULL',
            'esic_number' => 'VARCHAR(50) NULL',
            'uan_number' => 'VARCHAR(50) NULL',
            'pf_number' => 'VARCHAR(50) NULL',
            'date_of_joining' => 'DATE NOT NULL',
            'user_type' => "ENUM('Admin', 'Guard', 'Supervisor', 'Site Supervisor') NOT NULL",
            'salary' => 'DECIMAL(10,2) NOT NULL',
            'bank_account_number' => 'VARCHAR(50) NOT NULL',
            'ifsc_code' => 'VARCHAR(20) NOT NULL',
            'bank_name' => 'VARCHAR(255) NOT NULL',
            'profile_photo' => 'VARCHAR(255) NULL',
            'aadhar_card_scan' => 'VARCHAR(255) NULL',
            'pan_card_scan' => 'VARCHAR(255) NULL',
            'bank_passbook_scan' => 'VARCHAR(255) NULL',
            'police_verification_document' => 'VARCHAR(255) NULL',
            'ration_card_scan' => 'VARCHAR(255) NULL',
            'light_bill_scan' => 'VARCHAR(255) NULL',
            'voter_id_scan' => 'VARCHAR(255) NULL',
            'passport_scan' => 'VARCHAR(255) NULL',
            'web_access' => 'TINYINT(1) DEFAULT 0',
            'mobile_access' => 'TINYINT(1) DEFAULT 0',
            'password' => 'VARCHAR(255) NOT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'UNIQUE KEY unique_email (email_id)',
            'UNIQUE KEY unique_mobile (mobile_number)',
            'UNIQUE KEY unique_bank_account (bank_account_number)',
            // Removed unique constraints for optional fields
        ]
    ]
]; 