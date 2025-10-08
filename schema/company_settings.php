<?php
// schema/company_settings.php
return [
    'company_settings' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'company_name' => 'VARCHAR(255) NOT NULL',
            'gst_number' => 'VARCHAR(50) DEFAULT NULL',
            'street_address' => 'VARCHAR(255) DEFAULT NULL',
            'city' => 'VARCHAR(100) DEFAULT NULL',
            'state' => 'VARCHAR(100) DEFAULT NULL',
            'pincode' => 'VARCHAR(20) DEFAULT NULL',
            'email' => 'VARCHAR(255) DEFAULT NULL',
            'phone_number' => 'VARCHAR(20) DEFAULT NULL',
            'secondary_phone' => 'VARCHAR(20) DEFAULT NULL',
            'logo_path' => 'VARCHAR(255) DEFAULT NULL',
            'favicon_path' => 'VARCHAR(255) DEFAULT NULL',
            'signature_image' => 'VARCHAR(255) DEFAULT NULL',
            'bank_name' => 'VARCHAR(255) DEFAULT NULL',
            'bank_account_number' => 'VARCHAR(50) DEFAULT NULL',
            'bank_ifsc_code' => 'VARCHAR(20) DEFAULT NULL',
            'bank_branch' => 'VARCHAR(255) DEFAULT NULL',
            'bank_account_type' => 'VARCHAR(50) DEFAULT NULL',
            'invoice_notes' => 'TEXT DEFAULT NULL',
            'invoice_terms' => 'TEXT DEFAULT NULL',
            'primary_color' => 'VARCHAR(10) DEFAULT NULL',
            'watermark_image_path' => 'VARCHAR(255) DEFAULT NULL',
            'service_charges_enabled' => 'TINYINT(1) DEFAULT 0 COMMENT "1=Service charges enabled, 0=Disabled"',
            'service_charges_percentage' => 'DECIMAL(5,2) DEFAULT 10.00 COMMENT "Service charges percentage (e.g., 10.00 for 10%)"',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ]
]; 