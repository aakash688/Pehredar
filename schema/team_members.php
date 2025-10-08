<?php
// Schema for team_members table
define('TEAM_MEMBERS_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'team_id', 'type' => 'int(11)', 'null' => false, 'key' => 'MUL'],
    ['name' => 'user_id', 'type' => 'bigint(20) unsigned', 'null' => false, 'key' => 'MUL'],
    ['name' => 'location_id', 'type' => 'int(11)', 'null' => true, 'key' => 'MUL'],
    ['name' => 'role', 'type' => "enum('Supervisor','Guard')", 'null' => false],
    ['name' => 'assigned_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
]); 