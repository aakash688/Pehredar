<?php
// schema/activity_assignees.php
return [
	'activity_assignees' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'activity_id' => 'INT UNSIGNED NOT NULL',
			'user_id' => 'BIGINT UNSIGNED NOT NULL'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'UNIQUE KEY uniq_activity_user (activity_id, user_id)',
			'FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE',
			'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
		]
	]
];


