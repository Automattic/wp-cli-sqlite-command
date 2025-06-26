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
		// Check if there is a db.php file in the wp-content directory.
		if ( ! file_exists( ABSPATH . '/wp-content/db.php' ) ) {
			return false;
		}

		// If the file is found, we need to check that it is the sqlite integration plugin.
		$plugin_file = file_get_contents( ABSPATH . '/wp-content/db.php' );
		if ( ! preg_match( '/define\( \'SQLITE_DB_DROPIN_VERSION\', \'([0-9.]+)\' \)/', $plugin_file ) ) {
			return false;
		}

		$plugin_path = self::get_plugin_directory();
		if ( ! $plugin_path ) {
			return false;
		}

		// Try to get the version number from readme.txt
		$plugin_file = file_get_contents( $plugin_path . '/readme.txt' );

		preg_match( '/^Stable tag:\s*?(.+)$/m', $plugin_file, $matches );

		return isset( $matches[1] ) ? trim( $matches[1] ) : false;
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

		$sqlite_plugin_version = self::get_plugin_version();
		if ( ! $sqlite_plugin_version ) {
			WP_CLI::error( 'Could not determine the version of the SQLite integration plugin.' );
		}

		if ( version_compare( $sqlite_plugin_version, '2.1.11', '<' ) ) {
			WP_CLI::error( 'The SQLite integration plugin must be version 2.1.11 or higher.' );
		}

		// Load the translator class from the plugin.
		if ( ! defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
			define( 'SQLITE_DB_DROPIN_VERSION', $sqlite_plugin_version ); // phpcs:ignore
		}

		// We also need to selectively load the necessary classes from the plugin.
		require_once $plugin_directory . '/php-polyfills.php';
		require_once $plugin_directory . '/constants.php';

		$new_driver_supported = class_exists( WP_SQLite_Driver::class );
		$new_driver_enabled   = $new_driver_supported && defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;

		if ( $new_driver_enabled ) {
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
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-lexer.php';
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-query-rewriter.php';
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-translator.php';
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-token.php';
			require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
		}
	}
}
