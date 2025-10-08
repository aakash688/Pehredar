<?php
// Schema for leave_requests table
define('LEAVE_REQUESTS_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'user_id', 'type' => 'bigint(20) unsigned', 'null' => false, 'key' => 'MUL'],
    ['name' => 'leave_type', 'type' => "enum('Paid Leave','Unpaid Leave')", 'null' => false],
    ['name' => 'start_date', 'type' => 'date', 'null' => false],
    ['name' => 'end_date', 'type' => 'date', 'null' => false],
    ['name' => 'reason', 'type' => 'text', 'null' => false],
    ['name' => 'status', 'type' => "enum('Pending','Approved','Rejected')", 'null' => false, 'default' => 'Pending'],
    ['name' => 'approved_by', 'type' => 'bigint(20) unsigned', 'null' => true, 'key' => 'MUL'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 