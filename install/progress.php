<?php
/**
 * Installation Progress API
 * Provides real-time progress updates during installation
 */

session_start();

// Check if installation is in progress
if (!isset($_SESSION['install_progress'])) {
    http_response_code(404);
    echo json_encode(['error' => 'No installation in progress']);
    exit;
}

$progress = $_SESSION['install_progress'];

// Return progress data
header('Content-Type: application/json');
echo json_encode([
    'progress' => $progress['percentage'] ?? 0,
    'status' => $progress['status'] ?? 'running',
    'message' => $progress['message'] ?? 'Installing...',
    'log' => $progress['log'] ?? []
]);
?>
