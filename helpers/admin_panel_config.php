<?php
/**
 * Admin Panel Configuration Helper
 * Provides easy access to admin panel settings
 */

/**
 * Get admin panel configuration
 * @return array Admin panel configuration
 */
function getAdminPanelConfig() {
    $config = require __DIR__ . '/../config.php';
    return $config['admin_panel'] ?? [
        'notification_url' => 'https://gadmin.yantralogic.com/apis/install-endpoint.php',
        'enabled' => true,
        'timeout' => 30
    ];
}

/**
 * Get admin panel notification URL
 * @return string Notification URL
 */
function getAdminPanelNotificationUrl() {
    $adminConfig = getAdminPanelConfig();
    return $adminConfig['notification_url'] ?? 'https://gadmin.yantralogic.com/apis/install-endpoint.php';
}

/**
 * Check if admin panel notifications are enabled
 * @return bool True if enabled, false otherwise
 */
function isAdminPanelEnabled() {
    $adminConfig = getAdminPanelConfig();
    return $adminConfig['enabled'] ?? true;
}

/**
 * Get admin panel timeout
 * @return int Timeout in seconds
 */
function getAdminPanelTimeout() {
    $adminConfig = getAdminPanelConfig();
    return $adminConfig['timeout'] ?? 30;
}
?>
