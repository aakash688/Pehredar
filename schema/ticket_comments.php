<?php
// schema/ticket_comments.php
return [
    'ticket_comments' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'ticket_id' => 'INT(11) UNSIGNED NOT NULL',
            'user_id' => 'INT(11) UNSIGNED NOT NULL',
            'user_type' => 'VARCHAR(50) NULL',
            'comment' => 'TEXT NOT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE'
        ]
    ]
]; 