<?php
// schema/activity_photos.php
return [
    'activity_photos' => [
        'columns' => [
            'id' => 'INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'activity_id' => 'INT(11) UNSIGNED NOT NULL',
            'uploaded_by_user_id' => 'INT(11) UNSIGNED NOT NULL',
            'image_url' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT DEFAULT NULL',
            'is_approved' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE',
            // uploaded_by_user_id can reference either a `users` (admin) or a `clients_users` (client)
            // so we will manage this relationship at the application level without a direct FK.
            'INDEX (uploaded_by_user_id)'
        ]
    ]
]; 