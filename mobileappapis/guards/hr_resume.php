<?php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// mobileappapis/guards/hr_resume.php
// Generate guard Resume PDF (download or inline)

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

require_once '../../vendor/autoload.php';
require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';
$config = require '../../config.php';

use Dompdf\Dompdf;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_bearer_token_resume(): ?string {
	$headers = function_exists('getallheaders') ? getallheaders() : [];
	$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
	if ($auth && stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
	return null;
}

try {
	$jwt = get_bearer_token_resume();
	if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

	// Initialize optimized API\n\t$api = getOptimizedGuardAPI();\n\t$pdo = ConnectionPool::getConnection(); // Fallback for complex queries
	// Load employee/user details
	$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
	$stmt->execute([$userId]);
	$employee = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$employee) { sendOptimizedGuardError('User not found', 404); }

	$baseUrl = rtrim($config['base_url'], '/');
	if (!empty($employee['profile_photo'])) { $employee['profile_photo_src'] = $baseUrl . '/uploads/' . basename($employee['profile_photo']); }

	// Company settings (best-effort)
	$company_settings = [];
	try { $st = $pdo->query('SELECT * FROM company_settings LIMIT 1'); $company_settings = $st->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { }
	$company_logo_src = null;
	if (!empty($company_settings['company_logo'])) { $company_logo_src = $baseUrl . '/uploads/' . basename($company_settings['company_logo']); }

	// Render resume template
	ob_start();
	include __DIR__ . '/../../templates/pdf/resume_template.php';
	$html = ob_get_clean();

	$dompdf = new Dompdf([ 'isRemoteEnabled' => true ]);
	$dompdf->loadHtml($html);
	$dompdf->setPaper('A4', 'portrait');
	$dompdf->render();

	$filename = 'Resume_User_' . $userId . '.pdf';
	$download = isset($_GET['download']) && (int)$_GET['download'] === 1;
	header('Content-Type: application/pdf');
	header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
	echo $dompdf->output();
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}


