<?php

namespace Automattic\WP_CLI\SQLite;

use PDO;
use WP_MySQL_On_SQLite;
use WP_SQLite_Connection;
use WP_SQLite_Driver;
use WP_SQLite_Translator;

class SQLiteDriverFactory {
	/**
	 * Create an instance of the SQLite driver.
	 *
	 * @return WP_MySQL_On_SQLite|WP_SQLite_Driver|WP_SQLite_Translator
	 */
	public static function create_driver() {
		$db_name = defined( 'DB_NAME' ) && '' !== DB_NAME ? DB_NAME : 'database_name_here';

		if ( class_exists( WP_MySQL_On_SQLite::class ) ) {
			$database_dsn = sprintf(
				'mysql-on-sqlite:path=%s;dbname=%s',
				str_replace( ';', ';;', FQDB ),
				str_replace( ';', ';;', $db_name )
			);
			$driver       = new WP_MySQL_On_SQLite( $database_dsn );
			$driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
			return $driver;
		}

		$new_driver_enabled = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;
		if ( ! $new_driver_enabled ) {
			return new WP_SQLite_Translator();
		}

		$connection = new WP_SQLite_Connection(
			array(
				'path' => FQDB,
			)
		);
		return new WP_SQLite_Driver( $connection, $db_name );
	}
}
