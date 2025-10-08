<?php
/**
 * PDF Cache Cleanup Script
 * Removes old cached PDF files to prevent disk space issues
 */

$cacheDir = __DIR__ . '/../uploads/temp_pdfs/';
$maxAge = 24 * 60 * 60; // 24 hours in seconds
$deletedCount = 0;
$totalSize = 0;

if (!is_dir($cacheDir)) {
    echo "Cache directory does not exist: {$cacheDir}\n";
    exit(0);
}

$files = glob($cacheDir . '*.pdf');

foreach ($files as $file) {
    if (is_file($file)) {
        $fileAge = time() - filemtime($file);
        $fileSize = filesize($file);
        
        if ($fileAge > $maxAge) {
            if (unlink($file)) {
                $deletedCount++;
                $totalSize += $fileSize;
                echo "Deleted: " . basename($file) . " (age: " . round($fileAge / 3600, 1) . " hours)\n";
            }
        }
    }
}

echo "\nCleanup completed:\n";
echo "Files deleted: {$deletedCount}\n";
echo "Space freed: " . round($totalSize / 1024 / 1024, 2) . " MB\n";

// Optionally, you can add this to a cron job:
// 0 2 * * * /usr/bin/php /path/to/your/project/tools/cleanup_pdf_cache.php
?>
