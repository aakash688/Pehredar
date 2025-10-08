<?php
// schema/attendance_records.php
return [
	'attendance_records' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'employee_id' => 'BIGINT UNSIGNED NOT NULL',
			'marked_by' => 'BIGINT UNSIGNED NOT NULL',
			'date' => 'DATE NOT NULL',
			'status' => "ENUM('Present','Absent','Leave','Half-Day','WFH') NOT NULL",
			'submitted' => 'TINYINT(1) NOT NULL DEFAULT 0',
			'submitted_at' => 'DATETIME NULL',
			'location_id' => 'INT NULL',
			'group_id' => 'INT NULL',
			'client_id' => 'INT NULL',
			'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
			'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'UNIQUE KEY uniq_emp_date (employee_id, date)',
			'INDEX (marked_by, date)',
			'FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE',
			'FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE',
			'FOREIGN KEY (location_id) REFERENCES society_onboarding_data(id) ON DELETE SET NULL'
		]
	]
];


