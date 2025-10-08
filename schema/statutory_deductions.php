<?php
// schema/statutory_deductions.php - Statutory deductions configuration (non-editable via UI)

return [
    'table_name' => 'statutory_deductions',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'name' => 'VARCHAR(100) NOT NULL UNIQUE COMMENT "Statutory deduction name"',
        'is_percentage' => 'BOOLEAN NOT NULL DEFAULT FALSE COMMENT "If true, value is percentage of calculated salary"',
        'value' => 'DECIMAL(12,2) NOT NULL COMMENT "Percentage or fixed amount"',
        'affects_net' => 'BOOLEAN NOT NULL DEFAULT TRUE COMMENT "If false, shown on slip but not deducted from net (e.g., employer PF)"',
        'scope' => 'ENUM("employee","employer") NOT NULL DEFAULT "employee" COMMENT "Who pays this item"',
        'is_active' => 'BOOLEAN NOT NULL DEFAULT TRUE',
        'active_from_month' => 'VARCHAR(7) NOT NULL COMMENT "Activation month in YYYY-MM"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'indexes' => [
        'INDEX idx_active_from (active_from_month)',
        'INDEX idx_active (is_active)'
    ],
    'default_data' => [
        // Example defaults based on user sheet (active from Jul-25)
        ['name' => 'PF (Employer)', 'is_percentage' => true, 'value' => 3.00, 'affects_net' => false, 'scope' => 'employer', 'is_active' => true, 'active_from_month' => '2025-07'],
        ['name' => 'PF (Employee)', 'is_percentage' => true, 'value' => 3.00, 'affects_net' => true, 'scope' => 'employee', 'is_active' => true, 'active_from_month' => '2025-07'],
        ['name' => 'PT', 'is_percentage' => false, 'value' => 200.00, 'affects_net' => true, 'scope' => 'employee', 'is_active' => true, 'active_from_month' => '2025-07'],
        ['name' => 'ESIC', 'is_percentage' => false, 'value' => 500.00, 'affects_net' => true, 'scope' => 'employee', 'is_active' => true, 'active_from_month' => '2025-07'],
    ]
];
?> 