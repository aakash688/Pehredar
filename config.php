<?php
/**
 * Configuration Loader
 * This file checks if the application is installed and loads the appropriate configuration
 */

// Prevent direct access to this file
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Access denied');
}

// Define the path to the local configuration file
$localConfigFile = __DIR__ . '/config-local.php';

// Check if the application is installed
if (file_exists($localConfigFile)) {
    // Load the configuration from the local config file (created during installation)
    $config = include($localConfigFile);

    // Verify that the config has the installed flag
    if (!isset($config['installed']) || $config['installed'] !== true) {
        // Configuration exists but not properly installed
        $config['installed'] = false;
    }
} else {
    // Application not installed - return default config with installed flag set to false
    $config = [
        'installed' => false,
        'base_url' => '',
        'db' => [
            'host' => '',
            'port' => 3306,
            'dbname' => '',
            'user' => '',
            'pass' => ''
        ],
        'db_fallbacks' => [],
        'connection' => [
            'timeout' => 10,
            'retry_attempts' => 3,
            'retry_delay' => 2,
            'enable_fallbacks' => true,
            'cache_connection_test' => 300
        ],
        'jwt' => [
            'secret' => '',
            'expires_in_hours' => 24
        ],
        'admin_panel' => [
            'notification_url' => 'http://localhost/project/adminpannel/apis/install-endpoint.php',
            //'notification_url' => 'https://gadmin.yantralogic.com/apis/install-endpoint.php',
            'enabled' => true,
            'timeout' => 30
        ],
        'client_api' => [
            'remote_clientapi_endpoint_url' => 'http://localhost/project/adminpannel/apis/Client/ClientAPI.php',
            //'remote_clientapi_endpoint_url' => 'https://gadmin.yantralogic.com/apis/Client/ClientAPI.php',
            'enabled' => true,
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 2
        ]
    ];
}

return $config;
