<?php
// schema/notifications.php - Notifications tracking table

return [
    'table_name' => 'notifications',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "User who will receive the notification"',
        'title' => 'VARCHAR(255) NOT NULL COMMENT "Notification title"',
        'message' => 'TEXT NOT NULL COMMENT "Notification message content"',
        'notification_type' => 'VARCHAR(100) NOT NULL COMMENT "Type of notification"',
        'priority' => 'ENUM("low", "medium", "high", "urgent") DEFAULT "medium"',
        'category' => 'ENUM("payroll", "advance", "bonus", "deduction", "system", "general") DEFAULT "general"',
        'is_read' => 'BOOLEAN DEFAULT FALSE COMMENT "Has notification been read"',
        'is_sent' => 'BOOLEAN DEFAULT FALSE COMMENT "Has notification been sent"',
        'sent_at' => 'TIMESTAMP NULL COMMENT "When notification was sent"',
        'read_at' => 'TIMESTAMP NULL COMMENT "When notification was read"',
        'expires_at' => 'TIMESTAMP NULL COMMENT "When notification expires"',
        'related_table' => 'VARCHAR(100) NULL COMMENT "Related database table"',
        'related_id' => 'INT NULL COMMENT "Related record ID"',
        'action_url' => 'VARCHAR(500) NULL COMMENT "URL for notification action"',
        'action_text' => 'VARCHAR(100) NULL COMMENT "Text for action button"',
        'delivery_method' => 'ENUM("email", "sms", "in_app", "all") DEFAULT "in_app"',
        'email_sent' => 'BOOLEAN DEFAULT FALSE COMMENT "Email notification sent"',
        'sms_sent' => 'BOOLEAN DEFAULT FALSE COMMENT "SMS notification sent"',
        'email_sent_at' => 'TIMESTAMP NULL COMMENT "When email was sent"',
        'sms_sent_at' => 'TIMESTAMP NULL COMMENT "When SMS was sent"',
        'retry_count' => 'INT DEFAULT 0 COMMENT "Number of retry attempts"',
        'last_retry_at' => 'TIMESTAMP NULL COMMENT "Last retry attempt"',
        'error_message' => 'TEXT NULL COMMENT "Error message if delivery failed"',
        'metadata' => 'JSON NULL COMMENT "Additional notification metadata"',
        'created_by' => 'INT NULL COMMENT "User who created the notification"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_user_unread (user_id, is_read)',
        'INDEX idx_user_date (user_id, created_at)',
        'INDEX idx_type (notification_type)',
        'INDEX idx_category (category)',
        'INDEX idx_priority (priority)',
        'INDEX idx_sent (is_sent)',
        'INDEX idx_related (related_table, related_id)',
        'INDEX idx_expires (expires_at)',
        'INDEX idx_delivery_status (email_sent, sms_sent)',
        'INDEX idx_retry (retry_count, last_retry_at)'
    ]
];
?>