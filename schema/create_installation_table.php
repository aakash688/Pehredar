<?php
/**
 * Create Installation Data Table
 */

require_once 'config.php';
$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $sql = "
    CREATE TABLE IF NOT EXISTS installation_data (
      id int(11) NOT NULL AUTO_INCREMENT,
      client_id varchar(50) DEFAULT NULL,
      installation_id varchar(100) DEFAULT NULL,
      api_key varchar(255) NOT NULL,
      base_url varchar(500) NOT NULL,
      db_name varchar(100) NOT NULL,
      db_user varchar(100) NOT NULL,
      db_pass varchar(255) DEFAULT NULL,
      admin_password varchar(255) DEFAULT NULL,
      installation_date datetime NOT NULL,
      status enum('active','suspended','expired') DEFAULT 'active',
      created_at timestamp NOT NULL DEFAULT current_timestamp(),
      updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (id),
      UNIQUE KEY api_key (api_key),
      KEY idx_installation_id (installation_id),
      KEY idx_client_id (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    echo "✅ Installation data table created successfully!\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'installation_data'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'installation_data' exists and is ready to use.\n";
    } else {
        echo "❌ Table creation failed.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

