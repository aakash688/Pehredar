<?php
// schema/notification_preferences.php - User notification preferences table

return [
    'table_name' => 'notification_preferences',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "User ID"',
        'notification_type' => 'VARCHAR(100) NOT NULL COMMENT "Type of notification"',
        'is_enabled' => 'BOOLEAN DEFAULT TRUE COMMENT "Is this notification type enabled"',
        'delivery_method' => 'ENUM("email", "sms", "in_app", "all") DEFAULT "in_app" COMMENT "How to deliver notification"',
        'frequency' => 'ENUM("immediate", "daily", "weekly", "monthly") DEFAULT "immediate"',
        'quiet_hours_start' => 'TIME NULL COMMENT "Start of quiet hours"',
        'quiet_hours_end' => 'TIME NULL COMMENT "End of quiet hours"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'UNIQUE KEY unique_user_notification (user_id, notification_type)'
    ],
    'indexes' => [
        'INDEX idx_user_id (user_id)',
        'INDEX idx_notification_type (notification_type)',
        'INDEX idx_enabled (is_enabled)',
        'INDEX idx_delivery_method (delivery_method)'
    ],
    'default_data' => [
        // Default notification preferences for common types
        ['notification_type' => 'advance_granted', 'is_enabled' => true, 'delivery_method' => 'all'],
        ['notification_type' => 'advance_deducted', 'is_enabled' => true, 'delivery_method' => 'in_app'],
        ['notification_type' => 'bonus_added', 'is_enabled' => true, 'delivery_method' => 'all'],
        ['notification_type' => 'deduction_applied', 'is_enabled' => true, 'delivery_method' => 'all'],
        ['notification_type' => 'salary_generated', 'is_enabled' => true, 'delivery_method' => 'in_app'],
        ['notification_type' => 'salary_disbursed', 'is_enabled' => true, 'delivery_method' => 'all'],
        ['notification_type' => 'policy_change', 'is_enabled' => true, 'delivery_method' => 'all'],
        ['notification_type' => 'system_maintenance', 'is_enabled' => true, 'delivery_method' => 'in_app']
    ]
];
?>