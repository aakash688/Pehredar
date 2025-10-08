<?php
// schema/jwt_blacklist.php
return [
	'jwt_blacklist' => [
		'columns' => [
			'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'jti' => 'VARCHAR(64) NOT NULL',
			'expires_at' => 'DATETIME NOT NULL',
			'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
		],
		'constraints' => [
			'PRIMARY KEY (id)',
			'UNIQUE KEY uniq_jti (jti)',
			'INDEX (expires_at)'
		]
	]
];







