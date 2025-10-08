<?php
// actions/dashboard_v2_controller.php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
	$action = $_GET['action'] ?? '';
	$month = (int)($_GET['month'] ?? date('n', strtotime('-1 month')));
	$year = (int)($_GET['year'] ?? date('Y', strtotime('-1 month')));
	$monthFormatted = sprintf('%04d-%02d', $year, $month);

	$db = new Database();

	switch ($action) {
		case 'summary': {
			// Payroll summary
			$payroll = $db->query("SELECT 
				COALESCE(SUM(base_salary),0) AS gross,
				COALESCE(SUM(deductions),0) AS statutory,
				COALESCE(SUM(advance_salary_deducted),0) AS advance,
				COALESCE(SUM(final_salary),0) AS net,
				COALESCE(SUM(CASE WHEN disbursement_status='disbursed' THEN final_salary ELSE 0 END),0) AS disbursed_amount,
				COALESCE(SUM(CASE WHEN disbursement_status='pending' THEN final_salary ELSE 0 END),0) AS pending_amount,
				COALESCE(SUM(CASE WHEN disbursement_status='disbursed' THEN 1 ELSE 0 END),0) AS disbursed_count,
				COALESCE(SUM(CASE WHEN disbursement_status='pending' THEN 1 ELSE 0 END),0) AS pending_count
			FROM salary_records WHERE month = ?", [$monthFormatted])->fetch();

			// Advances overview
			$advances = $db->query("SELECT 
				COALESCE(SUM(CASE WHEN status='active' THEN remaining_balance ELSE 0 END),0) AS outstanding,
				COALESCE(SUM(amount),0) AS total_amount,
				COALESCE(AVG(CASE WHEN status='active' THEN monthly_deduction END),0) AS avg_monthly,
				COALESCE(SUM(CASE WHEN status='active' THEN 1 ELSE 0 END),0) AS active_count
			FROM advance_payments", [])->fetch();

			$response['success'] = true;
			$response['data'] = [
				'payroll' => $payroll,
				'advances' => $advances
			];
			break;
		}

		case 'top_advances': {
			$rows = $db->query("SELECT ap.id, ap.employee_id, ap.request_number, ap.remaining_balance, ap.monthly_deduction, u.first_name, u.surname
				FROM advance_payments ap
				LEFT JOIN users u ON ap.employee_id = u.id
				WHERE ap.status='active'
				ORDER BY ap.remaining_balance DESC
				LIMIT 10")->fetchAll();
			$response['success'] = true;
			$response['data'] = $rows;
			break;
		}

		case 'attendance_stats': {
			$rows = $db->query("SELECT COALESCE(am.code,'UNK') AS code, COUNT(a.id) AS cnt
				FROM attendance a
				LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
				WHERE MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?
				GROUP BY am.code
				ORDER BY cnt DESC", [$month, $year])->fetchAll();
			$response['success'] = true;
			$response['data'] = $rows;
			break;
		}

		case 'sites': {
			// Try best-effort to fetch site coordinates from a probable table
			$sites = [];
			try {
				$sites = $db->query("SELECT id, name, latitude AS lat, longitude AS lng FROM clients WHERE latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 500")->fetchAll();
			} catch (Exception $e1) {
				try {
					$sites = $db->query("SELECT id, name, lat, lng FROM clients WHERE lat IS NOT NULL AND lng IS NOT NULL LIMIT 500")->fetchAll();
				} catch (Exception $e2) {
					$sites = [];
				}
			}
			$response['success'] = true;
			$response['data'] = $sites;
			break;
		}

		case 'alerts': {
			$alerts = [];
			// Negative net salary
			$neg = $db->query("SELECT id, user_id, final_salary FROM salary_records WHERE month = ? AND final_salary < 0 LIMIT 20", [$monthFormatted])->fetchAll();
			if (!empty($neg)) $alerts[] = ['type' => 'negative_net', 'items' => $neg];
			$response['success'] = true;
			$response['data'] = $alerts;
			break;
		}

		case 'attendance_daily': {
			$date = $_GET['date'] ?? date('Y-m-d');
			// Totals by code for selected date
			$rows = $db->query("SELECT COALESCE(am.code,'UNK') AS code, COUNT(a.id) AS cnt
				FROM attendance a
				LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
				WHERE a.attendance_date = ?
				GROUP BY am.code ORDER BY cnt DESC", [$date])->fetchAll();
			// Present employees (limited)
			$present = [];
			try {
				$present = $db->query("SELECT u.id, u.first_name, u.surname
					FROM attendance a
					JOIN attendance_master am ON am.id = a.attendance_master_id AND am.code='P'
					JOIN users u ON u.id = a.user_id
					WHERE a.attendance_date = ?
					ORDER BY u.first_name, u.surname
					LIMIT 50", [$date])->fetchAll();
			} catch (Exception $e) { $present = []; }
			$response['success'] = true;
			$response['data'] = ['date' => $date, 'totals' => $rows, 'present' => $present];
			break;
		}

		case 'site_summary': {
			$siteId = (int)($_GET['site_id'] ?? 0);
			$days = (int)($_GET['days'] ?? 7);
			if ($siteId <= 0) throw new Exception('Invalid site_id');
			$start = date('Y-m-d', strtotime('-'.max(1,$days).' days'));
			$end = date('Y-m-d');
			// Attendance summary for site last N days
			$att = [];
			try {
				$att = $db->query("SELECT COALESCE(am.code,'UNK') AS code, COUNT(a.id) AS cnt
					FROM attendance a
					LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
					WHERE a.site_id = ? AND a.attendance_date BETWEEN ? AND ?
					GROUP BY am.code ORDER BY cnt DESC", [$siteId, $start, $end])->fetchAll();
			} catch (Exception $e) { $att = []; }
			// Recent tickets (optional)
			$tickets = [];
			try {
				$tickets = $db->query("SELECT id, title, status, created_at
					FROM tickets WHERE site_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
					ORDER BY created_at DESC LIMIT 10", [$siteId, $days])->fetchAll();
			} catch (Exception $e) { $tickets = []; }
			// Recent activities (optional)
			$activities = [];
			try {
				$activities = $db->query("SELECT id, user_id, tag, created_at
					FROM activities WHERE site_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
					ORDER BY created_at DESC LIMIT 10", [$siteId, $days])->fetchAll();
			} catch (Exception $e) { $activities = []; }
			$response['success'] = true;
			$response['data'] = ['attendance' => $att, 'tickets' => $tickets, 'activities' => $activities];
			break;
		}

		case 'team_performance': {
			// Team-wise attendance counts for a date (or month if date not provided)
			$date = $_GET['date'] ?? '';
			$rows = [];
			try {
				if ($date) {
					$rows = $db->query("SELECT t.id AS team_id, t.name AS team_name,
						SUM(CASE WHEN am.code='P' THEN 1 ELSE 0 END) AS present,
						SUM(CASE WHEN am.code='A' THEN 1 ELSE 0 END) AS absent,
						SUM(CASE WHEN am.code='HL' THEN 1 ELSE 0 END) AS holiday,
						SUM(CASE WHEN am.code='DBL' THEN 1 ELSE 0 END) AS dbl
					FROM teams t
					LEFT JOIN team_members tm ON tm.team_id = t.id
					LEFT JOIN attendance a ON a.user_id = tm.user_id AND a.attendance_date = ?
					LEFT JOIN attendance_master am ON am.id = a.attendance_master_id
					GROUP BY t.id, t.name
					ORDER BY present DESC", [$date])->fetchAll();
				} else {
					$rows = $db->query("SELECT t.id AS team_id, t.name AS team_name,
						SUM(CASE WHEN am.code='P' THEN 1 ELSE 0 END) AS present,
						SUM(CASE WHEN am.code='A' THEN 1 ELSE 0 END) AS absent,
						SUM(CASE WHEN am.code='HL' THEN 1 ELSE 0 END) AS holiday,
						SUM(CASE WHEN am.code='DBL' THEN 1 ELSE 0 END) AS dbl
					FROM teams t
					LEFT JOIN team_members tm ON tm.team_id = t.id
					LEFT JOIN attendance a ON a.user_id = tm.user_id AND MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?
					LEFT JOIN attendance_master am ON am.id = a.attendance_master_id
					GROUP BY t.id, t.name
					ORDER BY present DESC", [$month, $year])->fetchAll();
				}
			} catch (Exception $e) { $rows = []; }
			$response['success'] = true;
			$response['data'] = $rows;
			break;
		}
 
		default: {
			throw new Exception('Invalid action');
		}
	}
} catch (Exception $e) {
	http_response_code(400);
	$response['message'] = $e->getMessage();
}

echo json_encode($response);
