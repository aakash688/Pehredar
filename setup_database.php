<?php
// setup_database.php
// This script creates tables based on schema files in the schema/ directory.
// Run from CLI: php setup_database.php

// Suppress browser access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

echo "Database Setup Script\n";
echo "-----------------------\n";

try {
    // 1. Load Configuration
    $config = require __DIR__ . '/config.php';
    $dbConfig = $config['db'];
    echo "[INFO] Configuration loaded.\n";

    // 2. Connect to Database
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[INFO] Connected to MySQL server successfully.\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}`");
    $pdo->exec("USE `{$dbConfig['dbname']}`");
    echo "[INFO] Database '{$dbConfig['dbname']}' is ready.\n";

    // 3. Scan Schema Directory
    $schemaDir = __DIR__ . '/schema';
    $schemaFiles = glob($schemaDir . '/*.php');
    echo "[INFO] Found " . count($schemaFiles) . " schema files.\n";

    // Pass 1: Create all tables without constraints
    echo "\n--- Pass 1: Creating Tables ---\n";
    $schemas = [];
    foreach ($schemaFiles as $file) {
        $schemaData = require $file;
        if (!is_array($schemaData) || empty($schemaData)) {
            echo "[SKIP] Skipping invalid or empty schema file: " . basename($file) . "\n";
            continue;
        }

        $tableName = key($schemaData);
        $schemas[$tableName] = $schemaData[$tableName]; // Store for pass 2

        echo "\n[Processing] Table: `{$tableName}`...\n";

        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($stmt->rowCount() > 0) {
            echo "[INFO] Table `{$tableName}` already exists. Checking for missing columns...\n";
            
            // --- Column Migration Logic ---
            $schemaColumns = $schemas[$tableName]['columns'];
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $previousColumn = null;
            foreach ($schemaColumns as $colName => $colDef) {
                if (!in_array($colName, $existingColumns)) {
                    $alterSql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}";
                    if ($previousColumn) {
                        $alterSql .= " AFTER `{$previousColumn}`";
                    } else {
                        $alterSql .= " FIRST";
                    }
                    $pdo->exec($alterSql);
                    echo "[SUCCESS] Added missing column `{$colName}` to `{$tableName}`.\n";
                }
                $previousColumn = $colName;
            }
            continue;
        }

        $columns = $schemas[$tableName]['columns'];
        $sql = "CREATE TABLE `{$tableName}` (\n";
        $columnDefs = [];
        foreach ($columns as $name => $def) {
            $columnDefs[] = "  `{$name}` {$def}";
        }
        
        // Find and add PRIMARY KEY constraint directly in the CREATE statement
        $primaryKey = null;
        if (isset($schemas[$tableName]['constraints'])) {
            foreach ($schemas[$tableName]['constraints'] as $index => $constraint) {
                if (strpos(strtoupper($constraint), 'PRIMARY KEY') === 0) {
                    $primaryKey = $constraint;
                    // Remove it from the list so it's not processed in Pass 2
                    unset($schemas[$tableName]['constraints'][$index]); 
                    break;
                }
            }
        }
        
        if ($primaryKey) {
            $columnDefs[] = "  {$primaryKey}";
        }

        $sql .= implode(",\n", $columnDefs);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $pdo->exec($sql);
        echo "[SUCCESS] Table `{$tableName}` created successfully.\n";
    }

    // Pass 2: Add all remaining constraints to the tables
    echo "\n--- Pass 2: Adding Constraints ---\n";
    foreach ($schemas as $tableName => $schema) {
        if (!isset($schema['constraints']) || empty($schema['constraints'])) {
            continue;
        }

        echo "\n[Processing] Constraints for: `{$tableName}`...\n";

        foreach ($schema['constraints'] as $constraint) {
            try {
                $sql = "ALTER TABLE `{$tableName}` ADD {$constraint};";
                $pdo->exec($sql);
                echo "[SUCCESS] Added constraint to `{$tableName}`: {$constraint}\n";
            } catch (PDOException $e) {
                // Check for various "already exists" errors
                $errorCode = $e->errorInfo[1] ?? null;
                if ($errorCode == 1061 || // Duplicate key name
                    $errorCode == 1826 || // Duplicate foreign key
                    $errorCode == 1068) { // Multiple primary key defined
                     echo "[SKIP] Constraint likely already exists for `{$tableName}`.\n";
                }
                else {
                    // Re-throw other errors
                    throw $e;
                }
            }
        }
    }

    echo "\n--- Pass 3: Data Migration and Cleanup ---\n";
    try {
        echo "[INFO] Starting data cleanup for `users` table URL paths.\n";
        
        $bad_base_url = 'http://192.168.1.108/project/Gaurd/';
        $columns_to_fix = [
            'profile_photo', 
            'aadhar_card_scan', 
            'pan_card_scan', 
            'bank_passbook_scan', 
            'police_verification_document', 
            'ration_card_scan', 
            'light_bill_scan'
        ];

        $updated_count = 0;
        foreach ($columns_to_fix as $column) {
            $sql = "UPDATE `users` SET `{$column}` = REPLACE(`{$column}`, '{$bad_base_url}', '') WHERE `{$column}` LIKE '{$bad_base_url}%'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $updated_count += $stmt->rowCount();
        }

        if ($updated_count > 0) {
            echo "[SUCCESS] Cleaned up {$updated_count} incorrect URL(s) in the `users` table.\n";
        } else {
            echo "[INFO] No incorrect URLs found in `users` table to cleanup.\n";
        }

    } catch(PDOException $e) {
        echo "[WARNING] Data cleanup failed: " . $e->getMessage() . "\n";
    }

    echo "\nDatabase setup completed!\n";

} catch (PDOException $e) {
    echo "[ERROR] Database operation failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] An unexpected error occurred: " . $e->getMessage() . "\n";
    exit(1);
} 