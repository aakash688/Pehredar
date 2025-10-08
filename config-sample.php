<?php
/**
 * Sample Configuration File
 * This file serves as a template for the configuration
 * The actual configuration will be created during installation as config-local.php
 */

// Prevent direct access to this file
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Access denied');
}

// Database configuration as an array
return [
    'installed' => false, // Will be set to true after installation
    'base_url' => 'http://localhost/your-app', // Set your main base URL here
    
    // Primary database server
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'your_database', 
        'user' => 'your_username',
        'pass' => 'your_password'
    ],
    
    // Fallback database servers (try in order if primary fails)
    'db_fallbacks' => [
        // You can add backup servers here if you have them
        // Example:
        // [
        //     'host' => 'backup-server.com',
        //     'port' => 3306,
        //     'dbname' => 'your_database',
        //     'user' => 'your_username',
        //     'pass' => 'your_password'
        // ]
    ],
    
    // Connection settings
    'connection' => [
        'timeout' => 10,           // Connection timeout in seconds
        'retry_attempts' => 3,     // Number of retry attempts
        'retry_delay' => 2,        // Delay between retries in seconds
        'enable_fallbacks' => true, // Enable fallback servers
        'cache_connection_test' => 300 // Cache connection test results for 5 minutes
    ],
    'jwt' => [
        'secret' => 'ChangeThisToASecureRandomString',
        'expires_in_hours' => 24
    ],
    
    'admin_panel' => [
        'notification_url' => 'https://gadmin.yantralogic.com/apis/install-endpoint.php',
        'enabled' => true,
        'timeout' => 30
    ],
    
    'client_api' => [
        'remote_clientapi_endpoint_url' => 'https://gadmin.yantralogic.com/apis/Client/ClientAPI.php',
        'enabled' => true,
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 2
    ]
];
