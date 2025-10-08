<?php
// Schema for tags table
define('TAGS_SCHEMA', [
    ['name' => 'id', 'type' => 'int(10) unsigned', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
    ['name' => 'name', 'type' => 'varchar(50)', 'null' => false, 'key' => 'UNI'],
]); 