<?php
// CLI: php tools/seed_supervisor_data.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../helpers/database.php';

$pdo = get_db_connection();

echo "Seeding baseline supervisor data...\n";

// create sample locations if none
$count = $pdo->query("SELECT COUNT(*) AS c FROM society_onboarding_data")->fetch();
if ((int)$count['c'] === 0) {
    $stmt = $pdo->prepare("INSERT INTO society_onboarding_data (society_name, street_address, city, district, state, pin_code, address, latitude, longitude, onboarding_date, qr_code) VALUES (?,?,?,?,?,?,?,?,?,CURDATE(),?)");
    for ($i=1; $i<=3; $i++) {
        $stmt->execute(["Site $i", 'Addr', 'City', 'District', 'State', '000000', 'Full Addr', 28.600000+$i/1000, 77.200000+$i/1000, 'QR'. $i]);
    }
    echo "[OK] Seeded locations\n";
}

// create a supervisor if none
$sup = $pdo->query("SELECT id FROM users WHERE user_type IN ('Supervisor','Site Supervisor') LIMIT 1")->fetch();
if (!$sup) {
    $stmt = $pdo->prepare("INSERT INTO users (first_name, surname, date_of_birth, gender, mobile_number, email_id, address, permanent_address, date_of_joining, user_type, salary, bank_account_number, ifsc_code, bank_name, password, mobile_access) VALUES ('John','Doe','1990-01-01','Male','9999999999','john@example.com','A','A','2020-01-01','Supervisor',0,'0','NA','NA',?,1)");
    $stmt->execute([password_hash('password', PASSWORD_BCRYPT)]);
    echo "[OK] Seeded supervisor user (phone 9999999999 / password)\n";
}

// assign supervisor to sites
$supervisorId = $pdo->query("SELECT id FROM users WHERE user_type IN ('Supervisor','Site Supervisor') ORDER BY id ASC LIMIT 1")->fetchColumn();
$sites = $pdo->query("SELECT id FROM society_onboarding_data ORDER BY id ASC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
$ins = $pdo->prepare("INSERT IGNORE INTO supervisor_site_assignments (supervisor_id, site_id) VALUES (?,?)");
foreach ($sites as $sid) { $ins->execute([$supervisorId, $sid]); }
echo "[OK] Assigned supervisor to sample sites\n";

echo "Seeding complete.\n";







