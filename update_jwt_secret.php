<?php
/**
 * Safe JWT Secret Updater
 * This script helps you update the JWT secret safely
 */

// Load current config
$configFile = __DIR__ . '/config-local.php';
if (!file_exists($configFile)) {
    die("❌ config-local.php not found. Please run the installer first.\n");
}

$config = include($configFile);

echo "=== JWT Secret Updater ===\n\n";
echo "Current JWT Secret: " . substr($config['jwt']['secret'], 0, 20) . "...\n\n";

// Generate new secret
$newSecret = bin2hex(random_bytes(32));
echo "New JWT Secret: " . $newSecret . "\n\n";

echo "⚠️  WARNING: This will invalidate ALL existing login sessions!\n";
echo "All users (web and mobile) will need to login again.\n\n";

echo "Do you want to proceed? (y/N): ";
$input = trim(fgets(STDIN));

if (strtolower($input) === 'y' || strtolower($input) === 'yes') {
    // Update the config
    $config['jwt']['secret'] = $newSecret;
    
    // Generate new config content
    $configContent = "<?php
// Auto-generated configuration file
// Updated on: " . date('Y-m-d H:i:s') . "

return " . var_export($config, true) . ";
";
    
    if (file_put_contents($configFile, $configContent)) {
        echo "✅ JWT secret updated successfully!\n";
        echo "✅ All existing sessions are now invalid.\n";
        echo "✅ Users will need to login again.\n\n";
        
        echo "Next steps:\n";
        echo "1. Test web login: http://localhost/project/test/\n";
        echo "2. Test mobile API login\n";
        echo "3. Notify users about re-login requirement\n";
    } else {
        echo "❌ Failed to update config file. Please check permissions.\n";
    }
} else {
    echo "❌ Update cancelled.\n";
}

echo "\nDone!\n";
