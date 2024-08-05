<?php
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$automattic_wpcli_sqlite_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $automattic_wpcli_sqlite_autoloader ) ) {
	require_once $automattic_wpcli_sqlite_autoloader;
}

WP_CLI::add_command(
	'sqlite',
	'\Automattic\WP_CLI\SQLite\SQLite_Command',
	array(
		'before_invoke' => function () {
			$min_version = '7.4';
			if ( version_compare( PHP_VERSION, $min_version, '<' ) ) {
				WP_CLI::error( "The `wp server` command requires PHP {$min_version} or newer." );
			}
		},
	)
);
