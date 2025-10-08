<?php
// schema/ticket_assignees.php
return [
	'ticket_assignees' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'ticket_id' => 'INT UNSIGNED NOT NULL',
			'user_id' => 'BIGINT UNSIGNED NOT NULL'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'UNIQUE KEY uniq_ticket_user (ticket_id, user_id)',
			'FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE',
			'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
		]
	]
];


