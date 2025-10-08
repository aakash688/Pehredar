<?php
// schema/deduction_types.php - Deduction types configuration table

return [
    'table_name' => 'deduction_types',
    'columns' => [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'name' => 'VARCHAR(100) NOT NULL UNIQUE COMMENT "Deduction type name"',
        'description' => 'TEXT NULL COMMENT "Description of deduction type"',
        'is_recurring' => 'BOOLEAN DEFAULT FALSE COMMENT "Is this a recurring deduction"',
        'default_amount' => 'DECIMAL(12,2) NULL COMMENT "Default deduction amount"',
        'is_percentage' => 'BOOLEAN DEFAULT FALSE COMMENT "Is amount a percentage of salary"',
        'max_amount' => 'DECIMAL(12,2) NULL COMMENT "Maximum deduction amount"',
        'category' => 'ENUM("administrative", "disciplinary", "statutory", "voluntary", "other") DEFAULT "other"',
        'requires_approval' => 'BOOLEAN DEFAULT TRUE COMMENT "Requires admin approval"',
        'is_active' => 'BOOLEAN DEFAULT TRUE COMMENT "Is deduction type active"',
        'created_by' => 'INT NOT NULL COMMENT "Admin who created the type"',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'constraints' => [
        'FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
    ],
    'indexes' => [
        'INDEX idx_name (name)',
        'INDEX idx_category (category)',
        'INDEX idx_active (is_active)',
        'INDEX idx_recurring (is_recurring)'
    ],
    'default_data' => [
        ['name' => 'Late Fees', 'description' => 'Fees for late arrival or attendance issues', 'category' => 'disciplinary', 'default_amount' => 500.00],
        ['name' => 'Uniform Cost', 'description' => 'Cost deduction for uniforms provided', 'category' => 'administrative', 'default_amount' => 1000.00],
        ['name' => 'Welfare Fund', 'description' => 'Contribution to employee welfare fund', 'category' => 'voluntary', 'default_amount' => 200.00, 'is_recurring' => true],
        ['name' => 'Damage Charges', 'description' => 'Charges for equipment or property damage', 'category' => 'disciplinary', 'default_amount' => 0.00],
        ['name' => 'PF Deduction', 'description' => 'Provident Fund deduction', 'category' => 'statutory', 'is_percentage' => true, 'default_amount' => 12.00, 'is_recurring' => true],
        ['name' => 'ESI Deduction', 'description' => 'Employee State Insurance deduction', 'category' => 'statutory', 'is_percentage' => true, 'default_amount' => 0.75, 'is_recurring' => true]
    ]
];
?>