<?php
// Cache cleanup script for PDF generation optimization
// Cleans up old cached files to prevent disk space issues

echo "PDF Cache Cleanup Script\n";
echo "========================\n\n";

$startTime = microtime(true);
$totalCleaned = 0;
$totalSize = 0;

// Cache directories to clean
$cacheDirectories = [
    __DIR__ . '/../cache/photos' => 21600, // 6 hours for photo cache
    __DIR__ . '/../cache/employees' => 1800, // 30 minutes for employee cache
    __DIR__ . '/../uploads/temp_pdfs' => 3600, // 1 hour for PDF cache
];

foreach ($cacheDirectories as $directory => $maxAge) {
    $dirName = basename($directory);
    echo "Cleaning {$dirName} cache...\n";
    
    if (!is_dir($directory)) {
        echo "  Directory does not exist: {$directory}\n";
        continue;
    }
    
    $files = glob($directory . '/*');
    $dirCleaned = 0;
    $dirSize = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $age = time() - filemtime($file);
            $size = filesize($file);
            
            if ($age > $maxAge) {
                if (unlink($file)) {
                    $dirCleaned++;
                    $dirSize += $size;
                    echo "  Deleted: " . basename($file) . " (age: {$age}s, size: " . formatBytes($size) . ")\n";
                }
            }
        }
    }
    
    $totalCleaned += $dirCleaned;
    $totalSize += $dirSize;
    
    echo "  {$dirName}: Cleaned {$dirCleaned} files (" . formatBytes($dirSize) . ")\n\n";
}

$duration = round((microtime(true) - $startTime) * 1000, 2);

echo "Cleanup Summary:\n";
echo "================\n";
echo "Total files cleaned: {$totalCleaned}\n";
echo "Total space freed: " . formatBytes($totalSize) . "\n";
echo "Execution time: {$duration}ms\n";

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

// Log the cleanup activity
error_log("PDF Cache Cleanup: Cleaned {$totalCleaned} files, freed " . formatBytes($totalSize) . " in {$duration}ms");
?>
