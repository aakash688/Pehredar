<?php
// schema/supervisor_site_visits.php
return [
	'supervisor_site_visits' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'supervisor_id' => 'BIGINT UNSIGNED NOT NULL',
			'location_id' => 'INT NOT NULL',
			'checkin_at' => 'DATETIME NOT NULL',
			'checkout_at' => 'DATETIME NULL',
			'duration_minutes' => 'INT NULL',
			'checkin_latitude' => 'DECIMAL(9,6) NULL',
			'checkin_longitude' => 'DECIMAL(9,6) NULL',
			'checkout_latitude' => 'DECIMAL(9,6) NULL',
			'checkout_longitude' => 'DECIMAL(9,6) NULL',
			'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
			'updated_at' => 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'INDEX (supervisor_id, location_id)',
			'INDEX (checkin_at)',
			'INDEX (checkout_at)',
			'FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE',
			'FOREIGN KEY (location_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE'
		]
	]
];



