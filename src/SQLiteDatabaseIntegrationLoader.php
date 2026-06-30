<?php
namespace Automattic\WP_CLI\SQLite;

use WP_CLI;

final class SQLiteDatabaseIntegrationLoader {

	/**
	 * Get the version of the SQLite integration plugin if it is installed
	 * and activated.
	 *
	 * @return false|string The version of the SQLite integration plugin or false if not found/activated.
	 */
	public static function get_plugin_version() {
		return defined( 'SQLITE_DRIVER_VERSION' ) ? SQLITE_DRIVER_VERSION : false;
	}

	/**
	 * Find the directory where the SQLite integration plugin is installed.
	 *
	 * @return string|null The directory where the SQLite integration plugin is installed or null if not found.
	 */
	protected static function get_plugin_directory() {
		$plugin_folders = [
			ABSPATH . '/wp-content/plugins/sqlite-database-integration',
			ABSPATH . '/wp-content/mu-plugins/sqlite-database-integration',
			'/internal/shared/sqlite-database-integration',
		];

		foreach ( $plugin_folders as $folder ) {
			if ( file_exists( $folder ) && is_dir( $folder ) ) {
				return $folder;
			}
		}

		return null;
	}

	/**
	 * Load the necessary classes from the SQLite integration plugin.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException
	 */
	public static function load_plugin() {
		$plugin_directory = self::get_plugin_directory();
		if ( ! $plugin_directory ) {
			WP_CLI::error( 'Could not locate the SQLite integration plugin.' );
		}

		$new_driver_enabled = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;
		$old_structure      = file_exists( $plugin_directory . '/php-polyfills.php' );

		if ( $old_structure ) {
			require_once $plugin_directory . '/php-polyfills.php';
		}
		require_once $plugin_directory . '/constants.php';

		if ( $new_driver_enabled && file_exists( $plugin_directory . '/wp-pdo-mysql-on-sqlite.php' ) ) {
			require_once $plugin_directory . '/wp-pdo-mysql-on-sqlite.php';
		} elseif ( $new_driver_enabled && file_exists( $plugin_directory . '/wp-includes/database/load.php' ) ) {
			require_once $plugin_directory . '/wp-includes/database/load.php';
		} elseif ( $new_driver_enabled ) {
			require_once $plugin_directory . '/version.php';
			require_once $plugin_directory . '/wp-includes/parser/class-wp-parser-grammar.php';
			require_once $plugin_directory . '/wp-includes/parser/class-wp-parser.php';
			require_once $plugin_directory . '/wp-includes/parser/class-wp-parser-node.php';
			require_once $plugin_directory . '/wp-includes/parser/class-wp-parser-token.php';
			require_once $plugin_directory . '/wp-includes/mysql/class-wp-mysql-token.php';
			require_once $plugin_directory . '/wp-includes/mysql/class-wp-mysql-lexer.php';
			require_once $plugin_directory . '/wp-includes/mysql/class-wp-mysql-parser.php';
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-connection.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-configurator.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-driver-exception.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-exception.php';
			require_once $plugin_directory . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-reconstructor.php';
		} else {
			require_once $plugin_directory . '/wp-includes/database/version.php';
			$sqlite = $plugin_directory . '/wp-includes/sqlite';
			require_once "$sqlite/class-wp-sqlite-lexer.php";
			require_once "$sqlite/class-wp-sqlite-query-rewriter.php";
			require_once "$sqlite/class-wp-sqlite-translator.php";
			require_once "$sqlite/class-wp-sqlite-token.php";
			require_once "$sqlite/class-wp-sqlite-pdo-user-defined-functions.php";
		}

		$sqlite_plugin_version = self::get_plugin_version();
		if ( ! $sqlite_plugin_version ) {
			WP_CLI::error( 'Could not determine the version of the SQLite integration plugin.' );
		}

		if ( version_compare( $sqlite_plugin_version, '2.2.0', '<' ) ) {
			WP_CLI::error( 'The SQLite integration plugin must be version 2.2.0 or higher.' );
		}
	}
}
