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

		// In v2.2.21+, files moved into wp-includes/database/ subdirectories.
		$new_structure      = file_exists( $plugin_directory . '/wp-includes/database/php-polyfills.php' );
		$new_driver_enabled = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;

		require_once $new_structure
			? $plugin_directory . '/wp-includes/database/php-polyfills.php'
			: $plugin_directory . '/php-polyfills.php';
		require_once $plugin_directory . '/constants.php';

		if ( $new_driver_enabled ) {
			self::load_ast_driver( $plugin_directory, $new_structure );
		} else {
			self::load_legacy_driver( $plugin_directory );
		}
	}

	/**
	 * Load the AST driver classes from the SQLite integration plugin.
	 *
	 * @param string $plugin_directory The plugin directory.
	 * @param bool   $new_structure    Whether the plugin uses the v2.2.22+ directory structure.
	 * @return void
	 */
	private static function load_ast_driver( $plugin_directory, $new_structure ) {
		if ( file_exists( $plugin_directory . '/wp-pdo-mysql-on-sqlite.php' ) ) {
			require_once $plugin_directory . '/wp-pdo-mysql-on-sqlite.php';
			return;
		}

		foreach ( self::get_ast_driver_files( $plugin_directory, $new_structure ) as $file ) {
			require_once $file;
		}
	}

	/**
	 * Return the list of AST driver files to load based on the plugin directory structure.
	 *
	 * @param string $plugin_directory The plugin directory.
	 * @param bool   $new_structure    Whether the plugin uses the v2.2.22+ directory structure.
	 * @return string[]
	 */
	private static function get_ast_driver_files( $plugin_directory, $new_structure ) {
		if ( $new_structure ) {
			$db = $plugin_directory . '/wp-includes/database';
			return [
				"$db/version.php",
				"$db/parser/class-wp-parser-grammar.php",
				"$db/parser/class-wp-parser.php",
				"$db/parser/class-wp-parser-node.php",
				"$db/parser/class-wp-parser-token.php",
				"$db/mysql/class-wp-mysql-token.php",
				"$db/mysql/class-wp-mysql-lexer.php",
				"$db/mysql/class-wp-mysql-parser.php",
				"$db/sqlite/class-wp-sqlite-pdo-user-defined-functions.php",
				"$db/sqlite/class-wp-sqlite-connection.php",
				"$db/sqlite/class-wp-sqlite-configurator.php",
				"$db/sqlite/class-wp-sqlite-driver.php",
				"$db/sqlite/class-wp-sqlite-driver-exception.php",
				"$db/sqlite/class-wp-sqlite-information-schema-builder.php",
				"$db/sqlite/class-wp-sqlite-information-schema-exception.php",
				"$db/sqlite/class-wp-sqlite-information-schema-reconstructor.php",
			];
		}

		$wp = $plugin_directory . '/wp-includes';
		return [
			$plugin_directory . '/version.php',
			"$wp/parser/class-wp-parser-grammar.php",
			"$wp/parser/class-wp-parser.php",
			"$wp/parser/class-wp-parser-node.php",
			"$wp/parser/class-wp-parser-token.php",
			"$wp/mysql/class-wp-mysql-token.php",
			"$wp/mysql/class-wp-mysql-lexer.php",
			"$wp/mysql/class-wp-mysql-parser.php",
			"$wp/sqlite/class-wp-sqlite-pdo-user-defined-functions.php",
			"$wp/sqlite-ast/class-wp-sqlite-connection.php",
			"$wp/sqlite-ast/class-wp-sqlite-configurator.php",
			"$wp/sqlite-ast/class-wp-sqlite-driver.php",
			"$wp/sqlite-ast/class-wp-sqlite-driver-exception.php",
			"$wp/sqlite-ast/class-wp-sqlite-information-schema-builder.php",
			"$wp/sqlite-ast/class-wp-sqlite-information-schema-exception.php",
			"$wp/sqlite-ast/class-wp-sqlite-information-schema-reconstructor.php",
		];
	}

	/**
	 * Load the legacy driver classes from the SQLite integration plugin.
	 *
	 * @param string $plugin_directory The plugin directory.
	 * @return void
	 */
	private static function load_legacy_driver( $plugin_directory ) {
		$sqlite = $plugin_directory . '/wp-includes/sqlite';
		require_once "$sqlite/class-wp-sqlite-lexer.php";
		require_once "$sqlite/class-wp-sqlite-query-rewriter.php";
		require_once "$sqlite/class-wp-sqlite-translator.php";
		require_once "$sqlite/class-wp-sqlite-token.php";
		require_once "$sqlite/class-wp-sqlite-pdo-user-defined-functions.php";
	}
}
