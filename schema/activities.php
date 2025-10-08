<?php
// schema/activities.php
return [
    'activities' => [
        'columns' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
            'society_id' => 'INT NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'scheduled_date' => 'DATETIME NOT NULL',
            'location' => 'VARCHAR(255) DEFAULT NULL',
            'tags' => 'VARCHAR(255) NULL',
            'status' => "ENUM('Upcoming', 'Ongoing', 'Completed') NOT NULL",
            'created_by' => 'INT NOT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'FOREIGN KEY (society_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE',
            'INDEX (status)',
            'FULLTEXT KEY `fulltext_search` (title, description, tags)'
        ]
    ]
]; 