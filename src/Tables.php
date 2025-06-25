<?php

namespace Automattic\WP_CLI\SQLite;

use WP_CLI;
use WP_SQLite_Driver;
use WP_SQLite_Translator;

class Tables {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_SQLite_Driver|WP_SQLite_Translator
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
		$tables = array();
		foreach ( $this->driver->query( 'SHOW TABLES' ) as $row ) {
			$tables[] = array_values( (array) $row )[0];
		}

		// With the legacy driver, we need to exclude system tables
		// and make sure the table names are alphabetically sorted.
		if ( $this->driver instanceof WP_SQLite_Translator ) {
			$tables_to_exclude = array( '_mysql_data_types_cache', 'sqlite_sequence' );
			$tables            = array_diff( $tables, $tables_to_exclude );
			sort( $tables );
		}

		if ( empty( $tables ) ) {
			WP_CLI::error( 'No tables found in the database.' );
		}

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );

		if ( 'csv' === $format ) {
			WP_CLI::line( implode( ',', $tables ) );
		} elseif ( 'json' === $format ) {
			WP_CLI::line( json_encode( array_values( $tables ) ) );
		} else {
			foreach ( $tables as $table ) {
				WP_CLI::line( $table );
			}
		}
	}
}
