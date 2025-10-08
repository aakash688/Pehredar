<?php
// GET /api/supervisor/locations or /locations?id=6
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/api_helpers.php';

try {
    $user = sup_get_authenticated_user();
    $pdo = sup_get_db();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        // Return single location details
        $stmt = $pdo->prepare("SELECT s.id, s.society_name as name, s.address, s.city, s.state, s.pin_code,
                                      s.latitude, s.longitude, s.qr_code
                                 FROM society_onboarding_data s
                                WHERE s.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Location not found']);
            exit;
        }
        // Fetch client contacts
        $cstmt = $pdo->prepare("SELECT id, name, phone, email, username, is_primary
                                  FROM clients_users WHERE society_id = ? ORDER BY is_primary DESC, name ASC");
        $cstmt->execute([$id]);
        $contacts = $cstmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'location' => $row, 'contacts' => $contacts]);
        exit;
    }

    // List locations assigned to supervisor
    $stmt = $pdo->prepare("SELECT s.id, s.society_name as name, s.address, s.latitude, s.longitude, s.qr_code
                             FROM supervisor_site_assignments a 
                             JOIN society_onboarding_data s ON s.id = a.site_id 
                            WHERE a.supervisor_id = ? 
                         ORDER BY s.society_name ASC");
    $stmt->execute([$user->id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'locations' => $rows]);
} catch (Throwable $e) {
    @error_log('[SUPERVISOR_API][LOCATIONS] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}


