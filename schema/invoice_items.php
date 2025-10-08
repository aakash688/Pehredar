<?php
// schema/invoice_items.php
return [
    'invoice_items' => [
        'columns' => [
            'id' => 'INT(11) AUTO_INCREMENT',
            'invoice_id' => 'INT(11) NOT NULL',
            'employee_type' => 'VARCHAR(50) NOT NULL',
            'quantity' => 'INT(11) NOT NULL DEFAULT 0',
            'rate' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'total' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'constraints' => [
            'PRIMARY KEY (id)',
            'FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE'
        ]
    ]
]; 