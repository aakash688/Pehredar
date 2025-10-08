<?php
/**
 * Mobile API Connection Optimization Tool
 * 
 * This script automatically updates all mobile API files to use the optimized
 * connection pool instead of creating individual PDO connections.
 * 
 * This solves the "max connections per hour" issue by reusing connections.
 */

// Define the base directory for mobile APIs
$base_dir = __DIR__ . '/../mobileappapis';
$shared_dir = $base_dir . '/shared';

// Create shared directory if it doesn't exist
if (!is_dir($shared_dir)) {
    mkdir($shared_dir, 0755, true);
    echo "Created shared directory: $shared_dir\n";
}

// Pattern to find files that create new PDO connections
$connection_patterns = [
    // Match: $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
    '/\$pdo\s*=\s*new\s+PDO\s*\([^}]+?\);/s',
    // Match: try { $dsn = "mysql:... $pdo = new PDO... } catch
    '/try\s*\{\s*\$dsn\s*=\s*["\']mysql:[^}]+?catch\s*\([^}]+?\}/s'
];

// Replacement for new PDO connections
$optimized_connection = '// Use optimized connection pool to solve "max connections per hour" issue
require_once __DIR__ . \'/../../mobileappapis/shared/db_helper.php\';

$pdo = get_api_db_connection_safe();';

// Function to update a single file
function updateApiFile($filepath) {
    global $optimized_connection, $connection_patterns;
    
    $content = file_get_contents($filepath);
    $original_content = $content;
    $updated = false;
    
    // Check if file already uses the shared helper
    if (strpos($content, 'mobileappapis/shared/db_helper.php') !== false) {
        echo "SKIP: $filepath (already optimized)\n";
        return false;
    }
    
    // Check if file creates PDO connections
    foreach ($connection_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            // Replace the entire database connection block
            $content = preg_replace($pattern, $optimized_connection, $content);
            $updated = true;
            break;
        }
    }
    
    // Alternative approach: Look for specific patterns and replace them
    if (!$updated) {
        // Pattern 1: $config = require '../../config.php'; followed by PDO creation
        $pattern1 = '/\$config\s*=\s*require\s+[\'"][^\'"].*?config\.php[\'"];\s*\$dbConfig\s*=\s*\$config\[[\'"]\s*db\s*[\'"]?\];\s*[^$]*?\$dsn\s*=\s*["\']mysql:[^;]+;[^"\']*["\'];\s*try\s*\{[^}]*?\$pdo\s*=\s*new\s+PDO[^}]+?\}\s*catch[^}]+?\}/s';
        
        if (preg_match($pattern1, $content)) {
            $content = preg_replace($pattern1, $optimized_connection, $content);
            $updated = true;
        }
    }
    
    // Pattern 2: Simple new PDO creation
    if (!$updated && preg_match('/\$pdo\s*=\s*new\s+PDO\s*\(/', $content)) {
        // Find the entire try-catch block or just the assignment
        $lines = explode("\n", $content);
        $new_lines = [];
        $skip_until_catch = false;
        $replacement_added = false;
        
        foreach ($lines as $line) {
            if (preg_match('/\$pdo\s*=\s*new\s+PDO/', $line) || preg_match('/try\s*\{/', $line)) {
                if (!$replacement_added) {
                    $new_lines[] = $optimized_connection;
                    $replacement_added = true;
                }
                $skip_until_catch = true;
                continue;
            }
            
            if ($skip_until_catch && preg_match('/\}\s*catch\s*\([^}]+\}/', $line)) {
                $skip_until_catch = false;
                continue;
            }
            
            if (!$skip_until_catch) {
                $new_lines[] = $line;
            }
        }
        
        if ($replacement_added) {
            $content = implode("\n", $new_lines);
            $updated = true;
        }
    }
    
    if ($updated && $content !== $original_content) {
        // Create backup
        $backup_file = $filepath . '.backup.' . date('Y-m-d_H-i-s');
        file_put_contents($backup_file, $original_content);
        
        // Save the updated content
        file_put_contents($filepath, $content);
        echo "UPDATED: $filepath (backup created: " . basename($backup_file) . ")\n";
        return true;
    }
    
    echo "NO CHANGE: $filepath\n";
    return false;
}

// Find all PHP files in mobile API directories
function findApiFiles($directory) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getRealPath();
        }
    }
    
    return $files;
}

// Main execution
echo "=== Mobile API Connection Optimization Tool ===\n";
echo "This tool will update all mobile API files to use connection pooling\n";
echo "to solve the 'max connections per hour' MySQL error.\n\n";

$api_directories = ['clients', 'guards', 'supervisor'];
$total_updated = 0;

foreach ($api_directories as $dir) {
    $dir_path = $base_dir . '/' . $dir;
    if (!is_dir($dir_path)) {
        echo "SKIP: Directory $dir_path does not exist\n";
        continue;
    }
    
    echo "\n--- Processing $dir directory ---\n";
    $files = findApiFiles($dir_path);
    
    foreach ($files as $file) {
        if (updateApiFile($file)) {
            $total_updated++;
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total files updated: $total_updated\n";
echo "Connection pool optimization complete!\n";
echo "\nAll mobile API endpoints will now use persistent connections,\n";
echo "dramatically reducing database connection usage and solving the\n";
echo "'max connections per hour' error.\n";
?>
