<?php
// Schema for societies table
define('SOCIETIES_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'name', 'type' => 'varchar(255)', 'null' => false],
    ['name' => 'address', 'type' => 'text', 'null' => true],
    ['name' => 'qr_code_data', 'type' => 'varchar(255)', 'null' => true, 'key' => 'UNI'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 