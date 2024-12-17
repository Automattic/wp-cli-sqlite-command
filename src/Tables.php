<?php

namespace Automattic\WP_CLI\SQLite;

use WP_CLI;
use PDO;
use WP_SQLite_Translator;

class Tables {

	protected $translator;

	public function __construct() {
		$this->translator = new WP_SQLite_Translator();
	}

	/**
	 * Get the PDO instance.
	 *
	 * @return PDO
	 */
	protected function get_pdo() {
		return $this->translator->get_pdo();
	}

	/**
	 * Lists all tables in the SQLite database.
	 *
	 * @param array $assoc_args Associative array of options.
	 * @return void
	 */
	public function run( $assoc_args = [] ) {
		$pdo = $this->get_pdo();

		// Get all tables
		$stmt   = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" );
		$tables = $stmt->fetchAll( PDO::FETCH_COLUMN );

		// Remove system tables
		$tables_to_exclude = array( '_mysql_data_types_cache' );
		$tables            = array_diff( $tables, $tables_to_exclude );

		if ( empty( $tables ) ) {
			WP_CLI::error( 'No tables found in the database.' );
		}

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );

		if ( 'csv' === $format ) {
			WP_CLI::line( implode( ',', $tables ) );
		} else {
			foreach ( $tables as $table ) {
				WP_CLI::line( $table );
			}
		}
	}
}
