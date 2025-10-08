<?php
require_once __DIR__ . '/../helpers/database.php';

header('Content-Type: application/json');
if (!is_authenticated()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $db = new Database();
    
    // Get request parameters
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $page = isset($data['page']) ? intval($data['page']) : 1;
    $limit = isset($data['limit']) ? intval($data['limit']) : 10;
    $search = isset($data['search']) ? trim($data['search']) : '';
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build query based on search
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = " WHERE type_name LIKE ? OR description LIKE ? ";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM client_types" . $whereClause;
    $totalItems = $db->query($countQuery, $params)->fetch()['total'];
    
    // Get paginated client types
    $query = "SELECT * FROM client_types" . $whereClause . " ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $clientTypes = $db->query($query, $params)->fetchAll();
    
    // Get statistics
    $totalTypes = $db->query('SELECT COUNT(*) as cnt FROM client_types')->fetch()['cnt'];
    $totalClients = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data')->fetch()['cnt'];
    $avgClientsPerType = $db->query('SELECT AVG(cnt) as avg_cnt FROM (SELECT COUNT(*) as cnt FROM society_onboarding_data GROUP BY client_type_id) as sub')->fetch()['avg_cnt'] ?? 0;
    $avgClientsPerType = $avgClientsPerType ? number_format($avgClientsPerType, 2) : '0.00';
    
    $stats = [
        'totalTypes' => $totalTypes,
        'totalClients' => $totalClients,
        'avgClientsPerType' => $avgClientsPerType
    ];
    
    // Calculate pagination info
    $totalPages = ceil($totalItems / $limit);
    $pagination = [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalItems' => $totalItems,
        'itemsPerPage' => $limit,
        'hasNextPage' => $page < $totalPages,
        'hasPrevPage' => $page > 1
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $clientTypes,
        'stats' => $stats,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
} 