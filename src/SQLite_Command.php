<?php

namespace Automattic\WP_CLI\SQLite;

use WP_CLI;
use WP_CLI_Command;

class SQLite_Command extends WP_CLI_Command {


	/**
	 * Imports the database to SQLite from an MySQL dump file or from STDIN.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The name of the SQL file to import. If '-', then reads from STDIN. If omitted, it will look for '{dbname}.sql'.
	 *
	 * ## EXAMPLES
	 *      # Import the database from a file
	 *      $ wp sqlite import wordpress_dbase.sql
	 *      Success: Imported from 'import wordpress_dbase.sql'.
	 *
	 * @when before_wp_load
	 */
	public function import( $args, $assoc_args ) {

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a file to import.' );
		}

		if ( ! Import::get_sqlite_plugin_version() ) {
			WP_CLI::error( 'The SQLite integration plugin is not installed or activated.' );
		}

		$import = new Import();
		$import->run( $args[0], $assoc_args );
	}


	/**
	 * Exports the database from SQLite to a file or to STDOUT.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to export. If '-', then outputs to STDOUT. If
	 *  omitted, it will be '{dbname}-{Y-m-d}-{random-hash}.sql'.
	 *
	 * [--tables=<tables>]
	 * : The tables to export. Separate multiple tables with a comma. If omitted, all tables will be exported.
	 *
	 * [--exclude_tables=<tables>]
	 * : The comma separated list of specific tables that should be skipped from exporting. Excluding this parameter will export all tables in the database.
	 *
	 * [--porcelain]
	 * : Output filename for the exported database.
	 *
	 * ## EXAMPLES
	 *  # Export the database to a file
	 *  $ wp sqlite export wordpress_dbase.sql
	 *  Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *  # Export the database to STDOUT
	 *  $ wp sqlite export -
	 *  -- Table structure for table wp_users
	 *  DROP TABLE IF EXISTS wp_users;
	 *  CREATE TABLE wp_users (
	 *  ...
	 *
	 *  # Export only specific tables
	 *  $ wp sqlite export --tables=wp_posts,wp_users
	 *  Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *  # Export all tables except specific tables
	 *  $ wp sqlite export --exclude_tables=wp_posts,wp_users
	 *  Success: Exported to 'wordpress_dbase.sql'.
	 *
	 * @when before_wp_load
	 */
	public function export( $args, $assoc_args ) {
		WP_CLI::success( 'Exporting database...' );
		$export = new Export();

		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- WordPress is not loaded.
			$hash        = substr( md5( mt_rand() ), 0, 7 );
			$result_file = sprintf( '%s-%s.sql', date( 'Y-m-d' ), $hash ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		}

		$export->run( $result_file, $assoc_args );
	}
}
