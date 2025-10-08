<?php
/**
 * Auto License Check Bootstrap
 * Include this file at the top of every page to automatically check and expire licenses
 * 
 * Usage: require_once 'auto_license_check.php';
 */

// Prevent multiple inclusions
if (defined('AUTO_LICENSE_CHECK_LOADED')) {
    return;
}
define('AUTO_LICENSE_CHECK_LOADED', true);

// Only run if we have the license manager
if (file_exists(__DIR__ . '/helpers/license_manager.php')) {
    require_once __DIR__ . '/helpers/license_manager.php';
    
    try {
        $licenseManager = new LicenseManager();
        
        // Perform auto-expiry check on every page load
        $licenseManager->performAutoExpiryCheck();
        
        // Optional: Check if license is active and redirect if not
        // Uncomment the line below if you want automatic redirection on expired licenses
        // $licenseManager->checkLicenseAndRedirect();
        
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Auto license check error: " . $e->getMessage());
    }
}
?>
