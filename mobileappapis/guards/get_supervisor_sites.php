<?php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// mobileappapis/guards/get_supervisor_sites.php
// API endpoint to get sites assigned to a supervisor

require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';

// Create Database instance
$db = new Database();
$response = ['success' => false];

try {
    // Get supervisor ID from query parameter
    $supervisor_id = isset($_GET['supervisor_id']) ? (int)$_GET['supervisor_id'] : 0;
    
    if ($supervisor_id <= 0) {
        throw new Exception("Invalid supervisor ID");
    }
    
    // Get all sites assigned to this supervisor
    $sites_query = "SELECT s.* 
                   FROM society_onboarding_data s
                   JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
                   WHERE ssa.supervisor_id = ?";
                   
    $sites = $db->query($sites_query, [$supervisor_id])->fetchAll();
    
    $response = [
        'success' => true,
        'sites' => $sites
    ];
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 