<?php
// Schema for team_performance table
define('TEAM_PERFORMANCE_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'team_id', 'type' => 'int(11)', 'null' => false, 'key' => 'MUL'],
    ['name' => 'total_guards', 'type' => 'int(11)', 'null' => true, 'default' => '0'],
    ['name' => 'incidents_reported', 'type' => 'int(11)', 'null' => true, 'default' => '0'],
    ['name' => 'attendance_percentage', 'type' => 'decimal(5,2)', 'null' => true, 'default' => '0.00'],
    ['name' => 'performance_score', 'type' => 'decimal(5,2)', 'null' => true, 'default' => '0.00'],
    ['name' => 'month', 'type' => 'date', 'null' => false],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 