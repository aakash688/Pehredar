<?php
// schema/clients_users.php
return [
    'clients_users' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT',
            'society_id' => 'INT(11) NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'phone' => 'VARCHAR(20) NOT NULL',
            'email' => 'VARCHAR(255) NOT NULL',
            'username' => 'VARCHAR(100) NOT NULL',
            'password_hash' => 'VARCHAR(255) NOT NULL',
            'password_salt' => 'VARCHAR(255) NOT NULL',
            'profile_photo' => 'VARCHAR(255) NULL',
            'is_primary' => 'TINYINT(1) DEFAULT 0',
            'fcm_token' => 'TEXT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY (email)',
            'UNIQUE KEY (username)',
            'FOREIGN KEY (society_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE'
        ]
    ]
]; 