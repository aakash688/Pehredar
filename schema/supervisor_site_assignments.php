<?php
// schema/supervisor_site_assignments.php
return [
    'supervisor_site_assignments' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'supervisor_id' => 'INT NOT NULL',
            'site_id' => 'INT NOT NULL',
            'assigned_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'last_updated' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'UNIQUE KEY (supervisor_id, site_id)',
            'FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE',
            'FOREIGN KEY (site_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE'
        ]
    ]
]; 