<?php

namespace Automattic\WP_CLI\SQLite;

use PDO;
use RuntimeException;
use WP_CLI;
use WP_MySQL_On_SQLite;
use WP_SQLite_Connection;
use WP_SQLite_Driver;
use WP_SQLite_Storage;
use WP_SQLite_Translator;

class SQLiteDriverFactory {
	/**
	 * Create an instance of the SQLite driver.
	 *
	 * @param bool $create_database_if_missing Allow imports to create a database when missing.
	 *
	 * @return WP_MySQL_On_SQLite|WP_SQLite_Driver|WP_SQLite_Translator
	 */
	public static function create_driver( $create_database_if_missing = false ) {
		try {
			$database_path = self::get_database_path( $create_database_if_missing );
		} catch ( \Throwable $exception ) {
			WP_CLI::error( 'Could not open the SQLite database: ' . $exception->getMessage() );
		}
		if ( ! $create_database_if_missing && ! is_file( $database_path ) ) {
			WP_CLI::error( 'The SQLite database does not exist.' );
		}

		$db_name = defined( 'DB_NAME' ) && '' !== DB_NAME ? DB_NAME : 'database_name_here';

		if ( class_exists( WP_MySQL_On_SQLite::class ) ) {
			$database_dsn = sprintf(
				'mysql-on-sqlite:path=%s;dbname=%s',
				str_replace( ';', ';;', $database_path ),
				str_replace( ';', ';;', $db_name )
			);
			$driver       = new WP_MySQL_On_SQLite( $database_dsn );
			$driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
			return $driver;
		}

		$new_driver_enabled = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER;
		if ( ! $new_driver_enabled ) {
			return new WP_SQLite_Translator();
		}

		$connection = new WP_SQLite_Connection(
			array(
				'path' => $database_path,
			)
		);
		return new WP_SQLite_Driver( $connection, $db_name );
	}

	/**
	 * Resolve configuration without initializing existing storage.
	 *
	 * @param bool $create_database_if_missing Allow initialization when no managed path is recorded.
	 * @return string
	 */
	private static function get_database_path( $create_database_if_missing ) {
		if ( defined( 'DB_PATH' ) ) {
			$database_path = DB_PATH;
		} elseif ( defined( 'FQDB' ) ) {
			// Older plugin releases and explicit legacy settings use FQDB.
			$database_path = FQDB;
		} else {
			$path_file   = rtrim( FQDBDIR, '/\\' ) . '/db-path.php';
			$legacy_path = rtrim( FQDBDIR, '/\\' ) . '/.ht.sqlite';
			if ( file_exists( $path_file ) ) {
				// Use include so unreadable files can be handled on PHP 7.
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is checked below.
				$database_path = @include $path_file;
				if ( false === $database_path ) {
					throw new RuntimeException( 'Failed to read the SQLite database path file.' );
				}
			} elseif ( is_file( $legacy_path ) ) {
				$database_path = $legacy_path;
			} elseif ( $create_database_if_missing && class_exists( WP_SQLite_Storage::class ) ) {
				$database_path = ( new WP_SQLite_Storage( FQDBDIR ) )->initialize();
			} else {
				throw new RuntimeException( 'No SQLite database path is configured.' );
			}
		}

		if ( ! is_string( $database_path ) || '' === $database_path || false !== strpos( $database_path, "\0" ) ) {
			throw new RuntimeException( 'The SQLite database path is invalid.' );
		}
		return $database_path;
	}
}
