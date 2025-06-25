<?php

namespace Automattic\WP_CLI\SQLite;

use WP_SQLite_Connection;
use WP_SQLite_Driver;
use WP_SQLite_Translator;

class SQLiteDriverFactory {
	/**
	 * Create an instance of the SQLite driver.
	 *
	 * @return WP_SQLite_Driver|WP_SQLite_Translator
	 */
	public static function create_driver() {
		$new_driver_supported = class_exists( WP_SQLite_Driver::class );
		$new_driver_enabled   = $new_driver_supported && defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;

		if ( $new_driver_enabled ) {
			$connection = new WP_SQLite_Connection(
				array(
					'path' => FQDB,
				)
			);
			return new WP_SQLite_Driver( $connection, DB_NAME );
		}

		return new WP_SQLite_Translator();
	}
}
