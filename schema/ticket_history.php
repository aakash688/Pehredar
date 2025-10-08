<?php
// schema/ticket_history.php
return [
    'ticket_history' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'ticket_id' => 'INT(11) UNSIGNED NOT NULL',
            'user_id' => 'INT(11) UNSIGNED NOT NULL',
            'activity_type' => "ENUM('CREATED', 'STATUS_CHANGED', 'PRIORITY_CHANGED', 'COMMENT_ADDED', 'ASSIGNED') NOT NULL",
            'details' => 'VARCHAR(255) NULL',
            'old_value' => 'VARCHAR(255) NULL',
            'new_value' => 'VARCHAR(255) NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE'
            // We don't add a user_id FK because it could be in `users` or `clients_users`.
        ]
    ]
]; 