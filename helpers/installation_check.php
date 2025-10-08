<?php
/**
 * Installation Check Helper
 * Include this file at the beginning of any entry point to ensure the application is installed
 */

// Function to check if application is installed
function checkInstallation($config = null) {
    // Load config if not provided
    if ($config === null) {
        $configFile = dirname(__DIR__) . '/config.php';
        if (file_exists($configFile)) {
            $config = include($configFile);
        } else {
            $config = ['installed' => false];
        }
    }
    
    // Check if installed
    if (!isset($config['installed']) || $config['installed'] !== true) {
        // Get the current script path relative to the installation root
        $currentScript = $_SERVER['SCRIPT_NAME'];
        
        // Don't redirect if we're already in the install directory
        if (strpos($currentScript, '/install/') !== false) {
            return $config;
        }
        
        // Redirect to installation page
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Calculate the base path
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        
        // Remove '/mobileappapis' or other subdirectories if present
        $basePath = preg_replace('#/(mobileappapis|actions|helpers|UI)(/.*)?$#', '', $scriptDir);
        
        $installUrl = $protocol . '://' . $host . $basePath . '/install/';
        
        // For API endpoints, return JSON error instead of redirecting
        if (isApiRequest()) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Application not installed',
                'message' => 'Please complete the installation process first.',
                'install_url' => $installUrl
            ]);
            exit;
        }
        
        // For web pages, redirect to installer
        header('Location: ' . $installUrl);
        exit('Application not installed. Please run the installer at: ' . $installUrl);
    }
    
    return $config;
}

// Function to detect if this is an API request
function isApiRequest() {
    // Check if the request expects JSON
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($acceptHeader, 'application/json') !== false) {
        return true;
    }
    
    // Check if the request path indicates an API endpoint
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/mobileappapis/') !== false || 
        strpos($scriptName, '/actions/') !== false) {
        return true;
    }
    
    // Check if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    }
    
    return false;
}

// Auto-check if this file is included (not required)
// This allows for both manual checking and automatic checking
if (!defined('SKIP_AUTO_INSTALLATION_CHECK')) {
    // Get the config from the parent scope if it exists
    if (isset($config)) {
        checkInstallation($config);
    }
}
