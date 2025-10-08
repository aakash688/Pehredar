<?php
// mobileappapis/clients/activities_get.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';

// DB Connection
$config = require __DIR__ . '/../../config.php';
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['dbname']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    send_error_response('Database connection failed: ' . $e->getMessage(), 500);
}

// Auth: Ensures user is a client and gets their society_id
$user_data = get_authenticated_user_data();
$society_id = $user_data->society->id;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Invalid request method.', 405);
}

// Input: filters & pagination
$status     = $_GET['status']     ?? null;
$tags       = $_GET['tags']       ?? null;
$search     = $_GET['search']     ?? null;
$date_start = $_GET['date_start'] ?? $_GET['start_date'] ?? null;
$date_end   = $_GET['date_end']   ?? $_GET['end_date'] ?? null;

// Pagination
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

// Build Query
$sql   = "SELECT 
            a.id, 
            a.title, 
            a.description,
            a.status, 
            a.scheduled_date, 
            a.location, 
            a.created_at,
            (SELECT COUNT(*) FROM activity_photos ap WHERE ap.activity_id = a.id) as images_count,
            (SELECT GROUP_CONCAT(ap.image_url ORDER BY ap.id DESC) FROM activity_photos ap WHERE ap.activity_id = a.id) as latest_images_str
          FROM activities a";

$where = ["a.society_id = ?"];
$args  = [$society_id];

if ($status) { 
    $where[] = "a.status = ?"; 
    $args[] = $status; 
}

if ($date_start) {
    $where[] = "a.scheduled_date >= ?";
    $args[]  = $date_start . ' 00:00:00';
}
if ($date_end) {
    $where[] = "a.scheduled_date <= ?";
    $args[]  = $date_end . ' 23:59:59';
}
if ($search) {
    // Use LIKE search instead of FULLTEXT to avoid index dependency issues
    $where[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $args[]   = '%' . $search . '%';
    $args[]   = '%' . $search . '%';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY scheduled_date DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

// Run Query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    send_error_response('Database query failed: ' . $e->getMessage(), 500);
}

// Process activities to format image data
foreach ($activities as &$activity) {
    if (!empty($activity['latest_images_str'])) {
        $images = explode(',', $activity['latest_images_str']);
        $activity['latest_images'] = array_slice($images, 0, 2); // Take the first 2
    } else {
        $activity['latest_images'] = [];
    }
    unset($activity['latest_images_str']); // Clean up the intermediate field
    $activity['images_count'] = (int) ($activity['images_count'] ?? 0);
}
unset($activity);

// Count total for paging
try {
    $countSql = "SELECT COUNT(*) AS total FROM activities a" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($args);
    $totalRows = (int)$countStmt->fetchColumn();
    $hasMore   = $offset + $limit < $totalRows;
} catch (PDOException $e) {
    // If count fails, assume there might be more
    $totalRows = count($activities) + 1;
    $hasMore = true;
}

// Response
send_json_response([
    'success'    => true,
    'page'       => $page,
    'limit'      => $limit,
    'total_rows' => $totalRows,
    'has_more'   => $hasMore,
    'activities' => $activities,
]); 