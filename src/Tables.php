<?php

namespace Automattic\WP_CLI\SQLite;

use WP_CLI;
use PDO;
use WP_SQLite_Translator;

/**
 * Class Tables
 * Handles listing tables in the SQLite database.
 */
class Tables extends Base {

	protected $translator;
	protected $args      = array();
	protected $is_stdout = false;

	public function __construct() {
		$this->load_dependencies();
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
	 * @param string|null $pattern Optional wildcard pattern to filter tables.
	 * @param array $assoc_args Associative array of options.
	 * @return void
	 */
	public function run( $pattern = null, $assoc_args = [] ) {
		$pdo = $this->get_pdo();

		// Get all tables
		$stmt   = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" );
		$tables = $stmt->fetchAll( PDO::FETCH_COLUMN );

		// Filter tables if wildcard pattern is provided
		if ( $pattern ) {
			$sql_pattern = str_replace( '?', '_', $pattern );
			$sql_pattern = str_replace( '*', '%', $sql_pattern );

			$tables = array_filter(
				$tables,
				function ( $table ) use ( $pattern ) {
					return fnmatch( $pattern, $table, FNM_CASEFOLD );
				}
			);
		}

		// Remove system tables
		$tables_to_exclude = array( '_mysql_data_types_cache' );
		$tables            = array_diff( $tables, $tables_to_exclude );

		if ( empty( $tables ) ) {
			if ( $pattern ) {
				WP_CLI::error( 'No tables found matching: ' . $pattern );
			} else {
				WP_CLI::error( 'No tables found in the database.' );
			}
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
