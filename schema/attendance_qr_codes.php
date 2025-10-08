<?php
// Schema for attendance_qr_codes table
define('ATTENDANCE_QR_CODES_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'society_id', 'type' => 'int(11)', 'null' => false, 'key' => 'MUL'],
    ['name' => 'qr_code_hash', 'type' => 'varchar(255)', 'null' => false, 'key' => 'UNI'],
    ['name' => 'qr_code_image', 'type' => 'varchar(255)', 'null' => true],
    ['name' => 'is_active', 'type' => 'tinyint(1)', 'null' => true, 'default' => '1'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 