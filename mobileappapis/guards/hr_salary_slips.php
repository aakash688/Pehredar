<?php
// mobileappapis/guards/hr_salary_slips.php
// OPTIMIZED: Uses connection pooling, intelligent caching, and faster responses
// List and download/view salary slips for the authenticated guard

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

require_once '../../vendor/autoload.php';
require_once '../../config.php';
// Use optimized guard API helper for faster responses
require_once __DIR__ . '/../shared/optimized_guard_helper.php';

use Dompdf\Dompdf;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../../config.php';

try {
	$jwt = getOptimizedBearerToken();
	if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
	$decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
	$userId = (int)($decoded->data->id ?? 0);
	$userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
	if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

	// Initialize optimized API
	$api = getOptimizedGuardAPI();
	$pdo = ConnectionPool::getConnection();

	// If details=1 and id or month/year provided → render PDF
	$details = isset($_GET['details']) && (int)$_GET['details'] === 1;
	$download = isset($_GET['download']) && (int)$_GET['download'] === 1;
	$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$month = $_GET['month'] ?? null; // may be numeric or YYYY-MM
	$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

	if ($details && ($recordId > 0 || $month)) {
		if ($recordId > 0) {
			$stmt = $pdo->prepare('SELECT * FROM salary_records WHERE id = ? AND user_id = ? LIMIT 1');
			$stmt->execute([$recordId, $userId]);
		} else {
			// Handle both integer month and YYYY-MM string formats
			if (preg_match('/^\d{4}-\d{2}$/', (string)$month)) {
				// Month is already in YYYY-MM format, extract month number for query
				$monthNum = (int)substr($month, 5, 2);
				$yearNum = (int)substr($month, 0, 4);
			} else {
				// Month is numeric, use it directly
				$monthNum = (int)$month; 
				$yearNum = $year ? (int)$year : (int)date('Y');
				if ($monthNum < 1 || $monthNum > 12) { 
					http_response_code(400); 
					echo json_encode(['error' => 'Invalid month/year']); 
					exit; 
				}
			}
			
			// Try both formats: first as integer month, then as YYYY-MM string
			$stmt = $pdo->prepare('SELECT * FROM salary_records WHERE user_id = ? AND (month = ? OR month = ?) LIMIT 1');
			$monthStr = sprintf('%04d-%02d', $yearNum, $monthNum);
			$stmt->execute([$userId, $monthNum, $monthStr]);
		}
		$slip = $stmt->fetch(PDO::FETCH_ASSOC);
 		if (!$slip) { 
			error_log("HR Salary Slip: No slip found for user_id=$userId, month=" . ($month ?? 'null') . ", year=" . ($year ?? 'null'));
			http_response_code(404); 
			echo json_encode(['error' => 'Salary slip not found']); 
			exit; 
		}
 
 		// Load user for display
 		$u = $pdo->prepare('SELECT id, first_name, surname, user_type, mobile_number, email_id FROM users WHERE id = ?');
 		$u->execute([$userId]);
 		$emp = $u->fetch(PDO::FETCH_ASSOC) ?: [];

		// Get specific deduction details for this salary record (same as web UI)
		$deductionQuery = "
			SELECT 
				sd.deduction_master_id,
				sd.deduction_amount,
				dm.deduction_name,
				dm.deduction_code
			FROM salary_deductions sd
			JOIN deduction_master dm ON sd.deduction_master_id = dm.id
			WHERE sd.salary_record_id = ?
		";
		$deductions = $pdo->prepare($deductionQuery);
		$deductions->execute([$slip['id']]);
		$slip['deductions_detail'] = $deductions->fetchAll(PDO::FETCH_ASSOC);

		// Get statutory deductions breakdown (same as web UI)
		$salaryMonth = $slip['month'];
		$calcSalary = (float)($slip['calculated_salary'] ?? 0);
		$statutoryQuery = "
			SELECT name, is_percentage, value, affects_net, scope 
			FROM statutory_deductions 
			WHERE is_active = 1 AND active_from_month <= ? 
			ORDER BY id ASC
		";
		$statutory = $pdo->prepare($statutoryQuery);
		$statutory->execute([$salaryMonth]);
		$statutoryData = $statutory->fetchAll(PDO::FETCH_ASSOC);
		
		$breakdown = [];
		foreach ($statutoryData as $s) {
			$amt = $s['is_percentage'] ? ($calcSalary * ((float)$s['value']/100.0)) : (float)$s['value'];
			$breakdown[] = [
				'name' => $s['name'],
				'amount' => round($amt, 2),
				'note' => ''
			];
		}
		$slip['statutory_breakdown'] = $breakdown;

		// Debug logging for deductions
		error_log("HR Salary Slip: Deductions found: " . count($slip['deductions_detail']) . " for slip_id=" . ($slip['id'] ?? 'null'));
		error_log("HR Salary Slip: Deductions detail: " . json_encode($slip['deductions_detail']));
		error_log("HR Salary Slip: Statutory deductions found: " . count($slip['statutory_breakdown']) . " for slip_id=" . ($slip['id'] ?? 'null'));
		error_log("HR Salary Slip: Statutory breakdown: " . json_encode($slip['statutory_breakdown']));

		// Reuse the web generator for identical UI
		try {
			error_log("HR Salary Slip: Generating PDF for user_id=$userId, slip_id=" . ($slip['id'] ?? 'null'));
			require_once __DIR__ . '/../../helpers/SalarySlipPdfGenerator.php';
			$generator = new \Helpers\SalarySlipPdfGenerator($slip, $emp);
			$pdf = $generator->generate();
			header_remove('Content-Type');
			header('Content-Type: application/pdf');
			header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="Salary_Slip_' . ($slip['month'] ?? '') . '.pdf"');
			echo $pdf;
			exit;
		} catch (Exception $e) {
			error_log("HR Salary Slip PDF Generation Error: " . $e->getMessage());
			http_response_code(500);
			echo json_encode(['error' => 'Failed to generate salary slip PDF', 'details' => $e->getMessage()]);
			exit;
		}
	}

	// Otherwise: list slips for this user with pagination and optional year filter
	$page = max(1, (int)($_GET['page'] ?? 1));
	$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
	$offset = ($page - 1) * $limit;
	$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;

	$where = ['user_id = ?'];
	$params = [$userId];
	if ($filterYear) { 
		// Handle both integer year and YYYY-MM format
		$where[] = '(year = ? OR LEFT(month, 4) = ?)'; 
		$params[] = $filterYear;
		$params[] = (string)$filterYear; 
	}

	// Handle both integer month and YYYY-MM string formats
	$sql = 'SELECT id, month, year, base_salary, calculated_salary, deductions, final_salary, created_at FROM salary_records WHERE ' . implode(' AND ', $where) . ' ORDER BY year DESC, month DESC, created_at DESC LIMIT ? OFFSET ?';
	$stmt = $pdo->prepare($sql);
	$bind = 1; foreach ($params as $p) { $stmt->bindValue($bind++, $p); }
	$stmt->bindValue($bind++, $limit, PDO::PARAM_INT);
	$stmt->bindValue($bind++, $offset, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$baseUrl = rtrim($config['base_url'], '/');
	$items = array_map(function($r) use ($baseUrl) {
		$net = (float)($r['final_salary'] ?? 0);
		$gross = isset($r['calculated_salary']) ? (float)$r['calculated_salary'] : null;
		$ded = isset($r['deductions']) ? (float)$r['deductions'] : 0.0;
		
		// Handle both integer month and YYYY-MM string formats
		$monthValue = $r['month'];
		$yearValue = isset($r['year']) ? (int)$r['year'] : (int)date('Y');
		
		if (is_numeric($monthValue) && $monthValue >= 1 && $monthValue <= 12) {
			// Integer month format
			$monthNum = (int)$monthValue;
			$monthStr = sprintf('%04d-%02d', $yearValue, $monthNum);
			$downloadUrl = $baseUrl . '/mobileappapis/guards/hr_salary_slips.php?details=1&download=1&month=' . $monthNum . '&year=' . $yearValue;
		} else {
			// YYYY-MM string format
			$monthStr = (string)$monthValue;
			$yearValue = (int)substr($monthStr, 0, 4);
			$downloadUrl = $baseUrl . '/mobileappapis/guards/hr_salary_slips.php?details=1&download=1&month=' . rawurlencode($monthStr);
		}
		
		return [
			'id' => (int)$r['id'],
			'month' => $monthStr,
			'year' => $yearValue,
			'net_salary' => $net,
			'amount' => $net,
			'gross_salary' => $gross,
			'deductions' => $ded,
			'download_url' => $downloadUrl,
			'created_at' => $r['created_at'],
		];
	}, $rows ?: []);

	echo json_encode(['success' => true, 'page' => $page, 'limit' => $limit, 'items' => $items]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}


