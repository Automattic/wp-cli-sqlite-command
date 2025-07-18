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
	 * [--enable-ast-driver]
	 * : Enables new AST driver for full MySQL compatibility.
	 *
	 * ## EXAMPLES
	 *      # Import the database from a file
	 *      $ wp sqlite import wordpress_dbase.sql
	 *      Success: Imported from 'import wordpress_dbase.sql'.
	 *
	 * @when after_wp_config_load
	 */
	public function import( $args, $assoc_args ) {
		$enable_ast_driver = isset( $assoc_args['enable-ast-driver'] );

		if ( $enable_ast_driver ) {
			if ( ! defined( 'WP_SQLITE_AST_DRIVER' ) || ! WP_SQLITE_AST_DRIVER ) {
				// @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				define( 'WP_SQLITE_AST_DRIVER', true );
				// @phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			}
		}

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a file to import.' );
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
	 * [--enable-ast-driver]
	 * : Enables new AST driver for full MySQL compatibility.
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
	 * @when after_wp_config_load
	 */
	public function export( $args, $assoc_args ) {
		$is_porcelain      = isset( $assoc_args['porcelain'] );
		$enable_ast_driver = isset( $assoc_args['enable-ast-driver'] );

		if ( $enable_ast_driver ) {
			if ( ! defined( 'WP_SQLITE_AST_DRIVER' ) || ! WP_SQLITE_AST_DRIVER ) {
				// @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				define( 'WP_SQLITE_AST_DRIVER', true );
				// @phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			}
		}

		if ( ! $is_porcelain ) {
			WP_CLI::success( 'Exporting database...' );
		}

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

	/**
	 * Lists the database tables.
	 *
	 * Defaults to all tables in the SQLite database.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 *
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - csv
	 * ---
	 *
	 * [--enable-ast-driver]
	 * : Enables new AST driver for full MySQL compatibility.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all tables in the database
	 *     $ wp sqlite tables
	 *     wp_commentmeta
	 *     wp_comments
	 *     wp_links
	 *     wp_options
	 *     wp_postmeta
	 *     wp_posts
	 *     wp_terms
	 *     wp_termmeta
	 *     wp_term_relationships
	 *     wp_term_taxonomy
	 *     wp_usermeta
	 *     wp_users
	 *
	 * @when after_wp_config_load
	 */
	public function tables( $args, $assoc_args ) {
		$enable_ast_driver = isset( $assoc_args['enable-ast-driver'] );

		if ( $enable_ast_driver ) {
			if ( ! defined( 'WP_SQLITE_AST_DRIVER' ) || ! WP_SQLITE_AST_DRIVER ) {
				// @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				define( 'WP_SQLITE_AST_DRIVER', true );
				// @phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			}
		}

		$tables = new Tables();
		$tables->run( $assoc_args );
	}
}
