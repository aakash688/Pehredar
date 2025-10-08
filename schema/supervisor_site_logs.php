<?php
// schema/supervisor_site_logs.php
return [
	'supervisor_site_logs' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'supervisor_id' => 'BIGINT UNSIGNED NOT NULL',
			'location_id' => 'INT NOT NULL',
			'action' => "ENUM('checkin','checkout') NOT NULL",
			'timestamp' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
			'latitude' => 'DECIMAL(9,6) NULL',
			'longitude' => 'DECIMAL(9,6) NULL',
			'active_session' => 'TINYINT(1) NOT NULL DEFAULT 0'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'INDEX (supervisor_id, active_session)',
			'INDEX (location_id, timestamp)',
			'FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE',
			'FOREIGN KEY (location_id) REFERENCES society_onboarding_data(id) ON DELETE CASCADE'
		]
	]
];


