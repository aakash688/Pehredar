<?php
// schema/audit_logs.php - Comprehensive audit trail table

return [
    'table_name' => 'audit_logs',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'user_id' => 'INT NOT NULL COMMENT "User who performed the action"',
        'action_type' => 'VARCHAR(100) NOT NULL COMMENT "Type of action performed"',
        'table_name' => 'VARCHAR(100) NOT NULL COMMENT "Table affected by the action"',
        'record_id' => 'INT NULL COMMENT "ID of the affected record"',
        'old_values' => 'JSON NULL COMMENT "Previous values before change"',
        'new_values' => 'JSON NULL COMMENT "New values after change"',
        'description' => 'TEXT NULL COMMENT "Human readable description of action"',
        'module' => 'VARCHAR(100) NOT NULL COMMENT "Module/section where action occurred"',
        'severity' => 'ENUM("low", "medium", "high", "critical") DEFAULT "medium"',
        'ip_address' => 'VARCHAR(45) NULL COMMENT "IP address of the user"',
        'user_agent' => 'TEXT NULL COMMENT "User agent string"',
        'session_id' => 'VARCHAR(255) NULL COMMENT "Session identifier"',
        'request_method' => 'VARCHAR(10) NULL COMMENT "HTTP method used"',
        'request_url' => 'TEXT NULL COMMENT "URL that was accessed"',
        'response_status' => 'INT NULL COMMENT "HTTP response status"',
        'execution_time' => 'DECIMAL(8,4) NULL COMMENT "Execution time in seconds"',
        'success' => 'BOOLEAN DEFAULT TRUE COMMENT "Was the action successful"',
        'error_message' => 'TEXT NULL COMMENT "Error message if action failed"',
        'additional_data' => 'JSON NULL COMMENT "Additional contextual data"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_user_action (user_id, action_type)',
        'INDEX idx_table_record (table_name, record_id)',
        'INDEX idx_created_at (created_at)',
        'INDEX idx_module (module)',
        'INDEX idx_severity (severity)',
        'INDEX idx_success (success)',
        'INDEX idx_action_type (action_type)',
        'INDEX idx_ip_address (ip_address)',
        'INDEX idx_user_date (user_id, created_at)'
    ]
];
?>