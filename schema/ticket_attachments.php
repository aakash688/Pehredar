<?php
// schema/ticket_attachments.php
return [
    'ticket_attachments' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'ticket_id' => 'INT(11) UNSIGNED NOT NULL',
            'comment_id' => 'INT(11) UNSIGNED NULL',
            'file_path' => 'VARCHAR(255) NOT NULL',
            'file_name' => 'VARCHAR(255) NOT NULL',
            'file_type' => 'VARCHAR(100) NOT NULL',
            'uploaded_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE',
            'FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE SET NULL'
        ]
    ]
]; 