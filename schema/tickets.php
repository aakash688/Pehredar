<?php
// schema/tickets.php
return [
    'tickets' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'society_id' => 'INT(11) NOT NULL',
            'user_id' => 'INT(11) UNSIGNED NOT NULL',
            'user_type' => 'VARCHAR(50) NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'status' => "ENUM('Open','In Progress','Closed','On Hold') NOT NULL DEFAULT 'Open'",
            'priority' => "ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium'",
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (society_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE',
            // Note: user_id can reference `users` or `clients_users`, so a direct FK is tricky.
            // We will manage this relation at the application level.
            'INDEX (status)',
            'INDEX (priority)'
        ]
    ]
]; 