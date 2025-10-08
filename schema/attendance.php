<?php
// Schema for attendance table
define('ATTENDANCE_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'user_id', 'type' => 'bigint(20) unsigned', 'null' => false, 'key' => 'MUL'],
    ['name' => 'society_id', 'type' => 'int(11)', 'null' => false, 'key' => 'MUL'],
    ['name' => 'date', 'type' => 'date', 'null' => false],
    ['name' => 'check_in_time', 'type' => 'datetime', 'null' => true],
    ['name' => 'check_out_time', 'type' => 'datetime', 'null' => true],
    ['name' => 'status', 'type' => "enum('Present','Absent','Paid Leave','Unpaid Leave')", 'null' => false, 'default' => 'Absent'],
    ['name' => 'check_in_latitude', 'type' => 'decimal(9,6)', 'null' => true],
    ['name' => 'check_in_longitude', 'type' => 'decimal(9,6)', 'null' => true],
    ['name' => 'check_out_latitude', 'type' => 'decimal(9,6)', 'null' => true],
    ['name' => 'check_out_longitude', 'type' => 'decimal(9,6)', 'null' => true],
    ['name' => 'check_in_method', 'type' => "enum('Mobile App','Supervisor','Dashboard')", 'null' => false],
    ['name' => 'check_out_method', 'type' => "enum('Mobile App','Supervisor','Dashboard')", 'null' => true],
    ['name' => 'qr_scan_reference', 'type' => 'varchar(255)', 'null' => true],
    ['name' => 'remarks', 'type' => 'text', 'null' => true],
    ['name' => 'modified_by', 'type' => 'bigint(20) unsigned', 'null' => true, 'key' => 'MUL'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 