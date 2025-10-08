<?php
/**
 * Mobile App Configuration Schema
 * This file contains the database schema for mobile app settings
 */

return "
CREATE TABLE `mobile_app_config` (
  `sr` int(11) NOT NULL AUTO_INCREMENT,
  `Clientid` varchar(255) NOT NULL,
  `APIKey` varchar(255) NOT NULL,
  `App_logo_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sr`),
  UNIQUE KEY `unique_client_api` (`Clientid`, `APIKey`),
  KEY `idx_clientid` (`Clientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default mobile app configuration
INSERT INTO `mobile_app_config` (`Clientid`, `APIKey`, `App_logo_url`) VALUES
('default_client', 'default_api_key', 'assets/images/mobile-app-logo.png');
";
?>
