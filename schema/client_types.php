<?php
// Schema for client_types table
define('CLIENT_TYPES_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'type_name', 'type' => 'varchar(100)', 'null' => false],
    ['name' => 'description', 'type' => 'text', 'null' => true],
    ['name' => 'name', 'type' => 'varchar(255)', 'null' => false, 'key' => 'UNI'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 