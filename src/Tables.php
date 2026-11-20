<?php

namespace Automattic\WP_CLI\SQLite;

use PDO;
use WP_CLI;
use WP_MySQL_On_SQLite;

class Tables {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	protected $driver;

	public function __construct() {
		SQLiteDatabaseIntegrationLoader::load_plugin();
		$this->driver = SQLiteDriverFactory::create_driver();
	}

	/**
	 * Lists all tables in the SQLite database.
	 *
	 * @param array $assoc_args Associative array of options.
	 * @return void
	 */
	public function run( $assoc_args = [] ) {
		// Get all tables
		$result = $this->driver->query( 'SHOW TABLES' );
		$tables = $result->fetchAll( PDO::FETCH_COLUMN );

		if ( empty( $tables ) ) {
			WP_CLI::error( 'No tables found in the database.' );
		}

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );

		if ( 'csv' === $format ) {
			WP_CLI::line( implode( ',', $tables ) );
		} elseif ( 'json' === $format ) {
			WP_CLI::line( json_encode( $tables ) );
		} else {
			foreach ( $tables as $table ) {
				WP_CLI::line( $table );
			}
		}
	}
}
