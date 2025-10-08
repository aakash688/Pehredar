<?php
require_once __DIR__ . '/../helpers/database.php';

function create_roster_table($pdo) {
    $table_name = 'roster';

    // Drop the table if it exists to ensure clean recreation
    try {
        $pdo->exec("DROP TABLE IF EXISTS `$table_name`");
        echo "Existing table dropped.\n";
    } catch (PDOException $e) {
        echo "Error dropping table: " . $e->getMessage() . "\n";
    }

    $schema = "
    CREATE TABLE `$table_name` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `guard_id` INT(11) UNSIGNED NOT NULL,
        `society_id` INT(11) NOT NULL,
        `shift_id` INT(11) NOT NULL,
        `team_id` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        CONSTRAINT `fk_roster_guard` 
            FOREIGN KEY (`guard_id`) 
            REFERENCES `users` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        
        CONSTRAINT `fk_roster_society` 
            FOREIGN KEY (`society_id`) 
            REFERENCES `society_onboarding_data` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        
        CONSTRAINT `fk_roster_shift` 
            FOREIGN KEY (`shift_id`) 
            REFERENCES `shift_master` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        
        CONSTRAINT `fk_roster_team` 
            FOREIGN KEY (`team_id`) 
            REFERENCES `teams` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        
        UNIQUE KEY `unique_roster_entry` (`guard_id`, `society_id`, `shift_id`),
        
        -- Performance Optimization Indexes
        INDEX `idx_guard_name` (`guard_id`),
        INDEX `idx_team_id` (`team_id`),
        INDEX `idx_society_id` (`society_id`),
        INDEX `idx_shift_id` (`shift_id`),
        INDEX `idx_search_composite` (`guard_id`, `team_id`, `society_id`, `shift_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($schema);
        echo "Table '$table_name' created successfully.\n";
    } catch (PDOException $e) {
        echo "Error creating table: " . $e->getMessage() . "\n";
    }
}

function sync_existing_rosters($pdo) {
    try {
        // Check if there are existing rosters to migrate
        $existingRosters = $pdo->query("SELECT * FROM roster")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($existingRosters)) {
            echo "No existing rosters to migrate.\n";
            return;
        }

        // Validate and clean existing rosters
        $validationQueries = [
            "guard_id" => "SELECT id FROM users WHERE id = ?",
            "society_id" => "SELECT id FROM society_onboarding_data WHERE id = ?",
            "shift_id" => "SELECT id FROM shift_master WHERE id = ?",
            "team_id" => "SELECT id FROM teams WHERE id = ?"
        ];

        $cleanedRosters = [];
        foreach ($existingRosters as $roster) {
            $isValid = true;
            foreach ($validationQueries as $field => $query) {
                $stmt = $pdo->prepare($query);
                $stmt->execute([$roster[$field]]);
                if (!$stmt->fetch()) {
                    echo "Invalid {$field} for roster entry: " . json_encode($roster) . "\n";
                    $isValid = false;
                    break;
                }
            }
            
            if ($isValid) {
                $cleanedRosters[] = $roster;
            }
        }

        if (empty($cleanedRosters)) {
            echo "No valid rosters to migrate.\n";
            return;
        }

        // Prepare insert statement
        $insertStmt = $pdo->prepare(
            "INSERT INTO roster (guard_id, society_id, shift_id, team_id, created_at, updated_at) " .
            "VALUES (:guard_id, :society_id, :shift_id, :team_id, :created_at, :updated_at) " .
            "ON DUPLICATE KEY UPDATE " .
            "society_id = VALUES(society_id), " .
            "shift_id = VALUES(shift_id), " .
            "team_id = VALUES(team_id), " .
            "updated_at = VALUES(updated_at)"
        );

        // Begin transaction
        $pdo->beginTransaction();

        foreach ($cleanedRosters as $roster) {
            $insertStmt->execute([
                ':guard_id' => $roster['guard_id'],
                ':society_id' => $roster['society_id'],
                ':shift_id' => $roster['shift_id'],
                ':team_id' => $roster['team_id'],
                ':created_at' => $roster['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $roster['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }

        $pdo->commit();
        echo "Successfully migrated " . count($cleanedRosters) . " roster entries.\n";

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Error during roster migration: " . $e->getMessage() . "\n";
    }
}

// Main execution
try {
    $pdo = get_db_connection();
    
    // Create the table
    create_roster_table($pdo);
    
    // Sync existing rosters
    sync_existing_rosters($pdo);

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}
?>