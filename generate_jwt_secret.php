<?php
/**
 * JWT Secret Generator
 * Run this to generate a new secure JWT secret
 */

echo "=== JWT Secret Generator ===\n\n";

// Generate a secure random secret (64 characters)
$newSecret = bin2hex(random_bytes(32));

echo "New JWT Secret: " . $newSecret . "\n\n";

echo "To update your config:\n";
echo "1. Open config-local.php\n";
echo "2. Find the 'jwt' section\n";
echo "3. Replace the 'secret' value with:\n\n";
echo "'secret' => '" . $newSecret . "',\n\n";

echo "⚠️  WARNING: This will invalidate all existing login sessions!\n";
echo "All users (web and mobile) will need to login again.\n\n";

echo "Press Enter to continue...";
fgets(STDIN);

// Optionally update the config file directly
$configFile = __DIR__ . '/config-local.php';
if (file_exists($configFile)) {
    $config = file_get_contents($configFile);
    
    // Find and replace the secret
    $pattern = "/'secret' => '[^']*'/";
    $replacement = "'secret' => '" . $newSecret . "'";
    $updatedConfig = preg_replace($pattern, $replacement, $config);
    
    if ($updatedConfig !== $config) {
        if (file_put_contents($configFile, $updatedConfig)) {
            echo "✅ JWT secret updated in config-local.php\n";
        } else {
            echo "❌ Failed to update config file. Please update manually.\n";
        }
    }
} else {
    echo "❌ config-local.php not found\n";
}

echo "\nDone! All existing sessions are now invalid.\n";
