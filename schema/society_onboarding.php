<?php
// schema/society_onboarding.php
return [
    'society_onboarding_data' => [
        'columns' => [
            'id' => 'INT(11) AUTO_INCREMENT',
            'society_name' => 'VARCHAR(255) NOT NULL',
            'client_type_id' => 'INT(11) NULL',
            'street_address' => 'TEXT NOT NULL',
            'city' => 'VARCHAR(100) NOT NULL',
            'district' => 'VARCHAR(100) NOT NULL',
            'state' => 'VARCHAR(100) NOT NULL',
            'pin_code' => 'VARCHAR(10) NOT NULL',
            'gst_number' => 'VARCHAR(50) DEFAULT NULL',
            'latitude' => 'DECIMAL(9,6) NOT NULL',
            'longitude' => 'DECIMAL(9,6) NOT NULL',
            'onboarding_date' => 'DATE NOT NULL',
            'contract_expiry_date' => 'DATE NULL',
            'compliance_status' => 'TINYINT(1) DEFAULT 0 COMMENT "0=Non-Compliant, 1=Compliant"',
            'service_charges_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT "0=Service charges disabled, 1=Service charges enabled"',
            'service_charges_percentage' => 'DECIMAL(5,2) NULL DEFAULT NULL COMMENT "Service charges percentage (e.g., 10.00 for 10%)"',
            'qr_code' => 'VARCHAR(255) NULL',
            'guards' => 'INT(11) DEFAULT 0',
            'guard_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'guard_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'dogs' => 'INT(11) DEFAULT 0',
            'dog_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'dog_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'armed_guards' => 'INT(11) DEFAULT 0',
            'armed_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'armed_guard_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'housekeeping' => 'INT(11) DEFAULT 0',
            'housekeeping_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'housekeeping_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'bouncers' => 'INT(11) DEFAULT 0',
            'bouncer_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'bouncer_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'site_supervisors' => 'INT(11) DEFAULT 0',
            'site_supervisor_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'site_supervisor_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'supervisors' => 'INT(11) DEFAULT 0',
            'supervisor_client_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'supervisor_employee_rate' => 'DECIMAL(10,2) DEFAULT 0.00',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)'
        ]
    ]
]; 