<?php
// Schema for teams table
define('TEAMS_SCHEMA', [
    ['name' => 'id', 'type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'team_name', 'type' => 'varchar(100)', 'null' => false],
    ['name' => 'description', 'type' => 'text', 'null' => true],
    ['name' => 'created_by', 'type' => 'bigint(20) unsigned', 'null' => false, 'key' => 'MUL'],
    ['name' => 'created_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()'],
    ['name' => 'updated_at', 'type' => 'timestamp', 'null' => true, 'default' => 'current_timestamp()', 'extra' => 'on update current_timestamp()'],
]); 