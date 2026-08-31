<?php

namespace Automattic\WP_CLI\SQLite;

use PDO;
use WP_MySQL_On_SQLite;

class SQLiteDriverFactory {
	/**
	 * Create an instance of the SQLite driver.
	 *
	 * @return WP_MySQL_On_SQLite
	 */
	public static function create_driver() {
		$db_name = defined( 'DB_NAME' ) && '' !== DB_NAME ? DB_NAME : 'database_name_here';

		$database_dsn = sprintf(
			'mysql-on-sqlite:path=%s;dbname=%s',
			str_replace( ';', ';;', FQDB ),
			str_replace( ';', ';;', $db_name )
		);
		$driver       = new WP_MySQL_On_SQLite( $database_dsn );
		$driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
		return $driver;
	}
}
