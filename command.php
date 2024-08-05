<?php
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

WP_CLI::add_command( 'sqlite', 'Sqlite_Command', array(
	'before_invoke' => function() {
		// Load the SQLite driver
		$min_version = '7.4';
		if ( version_compare( PHP_VERSION, $min_version, '<' ) ) {
			WP_CLI::error( "The `wp server` command requires PHP {$min_version} or newer." );
		}
	}
) );