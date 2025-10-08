<?php
// ------------------------------------------------------------
//  mobileappapis/clients/tickets_get.php
// ------------------------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // CORS pre-flight
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/api_helpers.php';

// ---------- DB CONNECTION ---------------------------------------------------
$config = require __DIR__ . '/../../config.php';
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['dbname']
    );
    // Use optimized connection pool to solve "max connections per hour" issue
require_once __DIR__ . '/../../mobileappapis/shared/db_helper.php';

$pdo = get_api_db_connection_safe();
} catch (PDOException $e) {
    send_error_response('Database connection failed.', 500);
}

// ---------- AUTH ------------------------------------------------------------
$user_data  = get_authenticated_user_data();
$society_id = $user_data->society->id;

// Allow only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Invalid request method.', 405);
}

// ---------- INPUT: filters & pagination -------------------------------------
$status   = $_GET['status']   ?? null;
$priority = $_GET['priority'] ?? null;
$search   = $_GET['search']   ?? null;

// Accept either date_start/date_end OR start_date/end_date
$date_start = $_GET['date_start'] ?? $_GET['start_date'] ?? null;
$date_end   = $_GET['date_end']   ?? $_GET['end_date']   ?? null;

// Pagination (defaults)
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

// ---------- BUILD QUERY -----------------------------------------------------
$sql   = "SELECT id, title, status, priority, created_at, updated_at
          FROM tickets";
$where = ["society_id = ?"];
$args  = [$society_id];

if ($status)   { $where[] = "status   = ?"; $args[] = $status; }
if ($priority) { $where[] = "priority = ?"; $args[] = $priority; }

if ($date_start) {
    $where[] = "created_at >= ?";
    $args[]  = $date_start . ' 00:00:00';
}
if ($date_end) {
    $where[] = "created_at <= ?";
    $args[]  = $date_end . ' 23:59:59';
}

if ($search) {
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $like     = '%' . $search . '%';
    $args[]   = $like;
    $args[]   = $like;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY updated_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

// ---------- RUN QUERY -------------------------------------------------------
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$tickets = $stmt->fetchAll();

// ---------- COUNT TOTAL FOR PAGING -----------------------------------------
$countSql = "SELECT COUNT(*) AS total FROM tickets"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($args);
$totalRows = (int)$countStmt->fetchColumn();
$hasMore   = $offset + $limit < $totalRows;

// ---------- RESPONSE --------------------------------------------------------
send_json_response([
    'success'    => true,
    'page'       => $page,
    'limit'      => $limit,
    'total_rows' => $totalRows,
    'has_more'   => $hasMore,
    'tickets'    => $tickets,
]);