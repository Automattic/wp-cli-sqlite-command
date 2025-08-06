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
		$new_driver_enabled = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;

		if ( $new_driver_enabled ) {
			$connection = new WP_SQLite_Connection(
				array(
					'path' => FQDB,
				)
			);
			if ( defined( 'DB_NAME' ) && '' !== DB_NAME ) {
				$db_name = DB_NAME;
			} else {
				$db_name = 'database_name_here';
			}
			return new WP_SQLite_Driver( $connection, $db_name );
		}

		return new WP_SQLite_Translator();
	}
}
