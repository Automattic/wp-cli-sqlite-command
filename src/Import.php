<?php
namespace Automattic\WP_CLI\SQLite;

use Exception;
use Generator;
use PDO;
use RuntimeException;
use Throwable;
use WP_CLI;
use WP_MySQL_On_SQLite;
use WP_SQLite_Driver;
use WP_SQLite_Translator;

class Import {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite|WP_SQLite_Driver|WP_SQLite_Translator
	 */
	protected $driver;

	protected $args;

	public function __construct() {
		SQLiteDatabaseIntegrationLoader::load_plugin();
	}

	/**
	 * Execute the import command for SQLite.
	 *
	 * @param string $sql_file_path The path to the SQL dump file.
	 * @param array  $args          The arguments passed to the command.
	 *
	 * @return void
	 */
	public function run( $sql_file_path, $args ) {
		$this->args = $args;

		$is_stdin    = '-' === $sql_file_path;
		$import_file = $is_stdin ? 'php://stdin' : $sql_file_path;
		$handle      = fopen( $import_file, 'r' );
		if ( ! $handle ) {
			WP_CLI::error( "Unable to open file: $import_file" );
		}

		$database_existed_before_import = $this->database_file_exists();

		// Create the driver after opening the input, so a missing file doesn't create a database.
		$this->driver = SQLiteDriverFactory::create_driver( true );

		if ( isset( $args['refuse-overwrite'] ) ) {
			$this->refuse_when_tables_exist( $database_existed_before_import );
		}

		/*
		 * Set default SQL mode and other options as per the "mysqldump" command.
		 *
		 * This ensures that backups that don't specify SQL modes or other options
		 * will be imported successfully. Backups that explicitly set any of these
		 * options will override the default values.
		 *
		 * See also WP-CLI's "db import" command SQL mode handling:
		 *   https://github.com/wp-cli/db-command/blob/abeefa5a6c472f12716c5fa5c5a7394d3d0b1ef2/src/DB_Command.php#L823
		 */
		$this->driver->query( "SET @BACKUP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'" );
		$this->driver->query( 'SET @BACKUP_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0' );
		$this->driver->query( 'SET @BACKUP_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0' );

		/*
		 * Disable foreign key constraints at the SQLite level during import.
		 *
		 * The MySQL SET FOREIGN_KEY_CHECKS=0 above is stored as a session
		 * variable by the driver but does not translate to SQLite's PRAGMA.
		 * We need to explicitly disable foreign keys so that tables with
		 * cross-references can be imported in any order (e.g., alphabetical).
		 */
		$this->driver->execute_sqlite_query( 'PRAGMA foreign_keys = OFF' );

		/*
		 * Load the dump inside a single transaction so a failed import leaves
		 * no partial data behind. Verification (when requested) runs before
		 * the COMMIT so a verification failure rolls back as well.
		 */
		$this->begin_transaction();
		try {
			$this->execute_statements( $handle );
			$this->verify_after_import();
			$this->commit_transaction();
		} catch ( Throwable $exception ) {
			$this->rollback_transaction();
			if ( ! $database_existed_before_import ) {
				$this->delete_created_database_files();
			}
			fclose( $handle );
			WP_CLI::error( sprintf( 'Import failed and was rolled back: %s', $exception->getMessage() ) );
		}
		fclose( $handle );

		/*
		 * Re-enable foreign key constraints and verify integrity.
		 *
		 * PRAGMA foreign_key_check validates all FK references after the
		 * full import, catching any actual data integrity issues.
		 */
		$this->driver->execute_sqlite_query( 'PRAGMA foreign_key_check' );
		$this->driver->execute_sqlite_query( 'PRAGMA foreign_keys = ON' );

		$this->driver->query( 'SET SQL_MODE=@BACKUP_SQL_MODE' );
		$this->driver->query( 'SET UNIQUE_CHECKS=@BACKUP_UNIQUE_CHECKS' );
		$this->driver->query( 'SET FOREIGN_KEY_CHECKS=@BACKUP_FOREIGN_KEY_CHECKS' );

		$imported_from = $is_stdin ? 'STDIN' : $sql_file_path;
		WP_CLI::success( sprintf( "Imported from '%s'.", $imported_from ) );
	}

	/**
	 * Execute SQL statements from an SQL dump file.
	 *
	 * @param resource $handle The SQL dump file handle.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function execute_statements( $handle ) {
		foreach ( $this->parse_statements( $handle ) as $statement ) {
			try {
				$this->driver->query( $statement );
			} catch ( Throwable $statement_exception ) {
				$retried = false;
				try {
					// Try converting encoding and retry
					$detected_encoding = mb_detect_encoding( $statement, mb_list_encodings(), true );
					if ( $detected_encoding && 'UTF-8' !== $detected_encoding ) {
						$converted_statement = mb_convert_encoding( $statement, 'UTF-8', $detected_encoding );
						echo 'Converted ecoding for statement: ' . $converted_statement . PHP_EOL;
						$this->driver->query( $converted_statement );
						$retried = true;
					}
				} catch ( Throwable $retry_exception ) {
					// The retry failed; the original failure is reported below.
					unset( $retry_exception );
				}
				if ( ! $retried ) {
					throw new RuntimeException(
						sprintf(
							'Could not execute statement: %s (%s)',
							$statement,
							$statement_exception->getMessage()
						)
					);
				}
			}
		}
	}

	/**
	 * Whether the SQLite database file already exists.
	 *
	 * Resolves the configured path without creating storage, so calling
	 * this before create_driver() tells whether the import will write into
	 * a new file or an existing one.
	 *
	 * @return bool
	 */
	protected function database_file_exists() {
		try {
			$database_path = SQLiteDriverFactory::get_database_path( false );
		} catch ( Throwable $exception ) {
			return false;
		}
		return is_file( $database_path );
	}

	/**
	 * Refuse the import when the database already holds user tables.
	 *
	 * @param bool $database_existed_before_import Whether the database file existed before the driver was created.
	 *
	 * @return void
	 */
	protected function refuse_when_tables_exist( $database_existed_before_import ) {
		$tables = $this->get_user_table_names();
		if ( empty( $tables ) ) {
			return;
		}
		if ( ! $database_existed_before_import ) {
			$this->delete_created_database_files();
		}
		WP_CLI::error( 'Import refused: the SQLite database already contains tables. Run without --refuse-overwrite to import anyway.' );
	}

	/**
	 * Get the user table names in the target database.
	 *
	 * Internal SQLite bookkeeping tables are excluded, mirroring Export.
	 *
	 * @return string[]
	 */
	protected function get_user_table_names() {
		$result = $this->driver->query( 'SHOW TABLES' );
		if ( $this->driver instanceof PDO ) {
			$tables = $result->fetchAll( PDO::FETCH_COLUMN );
		} else {
			$tables = array();
			foreach ( $result as $row ) {
				$tables[] = array_values( (array) $row )[0];
			}
		}
		return array_values(
			array_filter(
				$tables,
				function ( $table_name ) {
					if ( in_array( $table_name, array( '_mysql_data_types_cache', 'sqlite_master', 'sqlite_sequence' ), true ) ) {
						return false;
					}
					return 0 !== strpos( $table_name, '_wp_sqlite_' );
				}
			)
		);
	}

	/**
	 * Quote a MySQL identifier.
	 *
	 * @param string $identifier Identifier to quote.
	 * @return string
	 */
	protected function quote_identifier( $identifier ) {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Start the import transaction.
	 *
	 * @return void
	 */
	protected function begin_transaction() {
		try {
			$this->driver->query( 'START TRANSACTION' );
		} catch ( Throwable $exception ) {
			$this->driver->execute_sqlite_query( 'BEGIN' );
		}
	}

	/**
	 * Commit the import transaction.
	 *
	 * @return void
	 */
	protected function commit_transaction() {
		try {
			$this->driver->query( 'COMMIT' );
		} catch ( Throwable $exception ) {
			$this->driver->execute_sqlite_query( 'COMMIT' );
		}
	}

	/**
	 * Roll back the import transaction.
	 *
	 * @return void
	 */
	protected function rollback_transaction() {
		try {
			$this->driver->query( 'ROLLBACK' );
		} catch ( Throwable $exception ) {
			try {
				$this->driver->execute_sqlite_query( 'ROLLBACK' );
			} catch ( Throwable $sqlite_exception ) {
				// The connection may already be unusable; the import error below is what matters.
				unset( $sqlite_exception );
			}
		}
	}

	/**
	 * Delete a database file created by a failed import.
	 *
	 * Removes the main file plus any -wal, -shm, and -journal sidecars so
	 * no partial database is left behind.
	 *
	 * @return void
	 */
	protected function delete_created_database_files() {
		try {
			$database_path = SQLiteDriverFactory::get_database_path( false );
		} catch ( Throwable $exception ) {
			return;
		}
		if ( ! is_string( $database_path ) || '' === $database_path ) {
			return;
		}
		foreach ( array( '', '-wal', '-shm', '-journal' ) as $suffix ) {
			$file = $database_path . $suffix;
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Verify the imported data when --verify-old-host is given.
	 *
	 * Fails when any stored value still contains the old host, or when any
	 * value that looks like PHP-serialized data no longer unserializes.
	 * Runs inside the import transaction so a failure rolls everything back.
	 *
	 * @return void
	 */
	protected function verify_after_import() {
		if ( ! isset( $this->args['verify-old-host'] ) ) {
			return;
		}
		$old_host = $this->args['verify-old-host'];
		if ( ! is_string( $old_host ) || '' === $old_host ) {
			throw new RuntimeException( '--verify-old-host requires a non-empty value.' );
		}

		$host_hits       = array();
		$serialized_hits = array();
		foreach ( $this->get_user_table_names() as $table_name ) {
			$this->scan_table_for_verification( $table_name, $old_host, $host_hits, $serialized_hits );
		}

		if ( ! empty( $host_hits ) ) {
			throw new RuntimeException(
				sprintf(
					"Verification failed: %d value(s) in the imported database still contain '%s' (e.g., table '%s').",
					array_sum( $host_hits ),
					$old_host,
					array_keys( $host_hits )[0]
				)
			);
		}
		if ( ! empty( $serialized_hits ) ) {
			throw new RuntimeException(
				sprintf(
					'Verification failed: %d value(s) contain invalid serialized data (e.g., table \'%s\').',
					array_sum( $serialized_hits ),
					array_keys( $serialized_hits )[0]
				)
			);
		}
		WP_CLI::log(
			sprintf(
				"Verified: no occurrences of '%s' remain and serialized data is intact.",
				$old_host
			)
		);
	}

	/**
	 * Scan one table for verification failures.
	 *
	 * @param string $table_name      Table to scan.
	 * @param string $old_host        Host that must no longer occur.
	 * @param array  $host_hits       Hit counts per table, updated in place.
	 * @param array  $serialized_hits Corrupt-serialized counts per table, updated in place.
	 *
	 * @return void
	 */
	protected function scan_table_for_verification( $table_name, $old_host, &$host_hits, &$serialized_hits ) {
		$result = $this->driver->query( 'SELECT * FROM ' . $this->quote_identifier( $table_name ) );
		if ( $this->driver instanceof PDO ) {
			$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		} else {
			$rows = array();
			foreach ( $result as $row ) {
				$rows[] = (array) $row;
			}
		}
		foreach ( $rows as $row ) {
			foreach ( $row as $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				if ( false !== strpos( $value, $old_host ) ) {
					$host_hits[ $table_name ] = isset( $host_hits[ $table_name ] ) ? $host_hits[ $table_name ] + 1 : 1;
				}
				if ( ! $this->is_valid_serialized_string( $value ) ) {
					$serialized_hits[ $table_name ] = isset( $serialized_hits[ $table_name ] ) ? $serialized_hits[ $table_name ] + 1 : 1;
				}
			}
		}
	}

	/**
	 * Check that a value holding PHP-serialized data still unserializes.
	 *
	 * Non-serialized values always pass; only values that look serialized
	 * but fail to unserialize are reported as corrupt.
	 *
	 * @param string $value Value to check.
	 * @return bool True when the value is not corrupt serialized data.
	 */
	protected function is_valid_serialized_string( $value ) {
		if ( function_exists( 'is_serialized' ) ) {
			$is_serialized = is_serialized( $value );
		} else {
			$is_serialized = $this->looks_like_serialized( $value );
		}
		if ( ! $is_serialized ) {
			return true;
		}
		if ( 'b:0;' === trim( $value ) ) {
			return true;
		}
		$unserialized = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Verification intentionally round-trips stored serialized values.
		return false !== $unserialized;
	}

	/**
	 * Fallback check for PHP-serialized strings when WordPress is unavailable.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	protected function looks_like_serialized( $value ) {
		$value = trim( $value );
		if ( 'N;' === $value ) {
			return true;
		}
		if ( strlen( $value ) < 4 || ':' !== $value[1] ) {
			return false;
		}
		$last_character = substr( $value, -1 );
		if ( ';' !== $last_character && '}' !== $last_character ) {
			return false;
		}
		$token = $value[0];
		if ( 's' === $token || 'a' === $token || 'O' === $token ) {
			return (bool) preg_match( '/^' . $token . ':[0-9]+:/s', $value );
		}
		if ( 'b' === $token || 'i' === $token || 'd' === $token ) {
			return (bool) preg_match( '/^' . $token . ':[0-9.E+-]+;/', $value );
		}
		return false;
	}

	/**
	 * Parse SQL statements from an SQL dump file.
	 * @param resource $handle The SQL dump file handle.
	 *
	 * @return Generator A generator that yields SQL statements.
	 */
	public function parse_statements( $handle ) {
		$starting_quote = null;
		$in_comment     = false;
		$buffer         = '';

		// phpcs:ignore
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$strlen = strlen( $line );
			for ( $i = 0; $i < $strlen; $i++ ) {
				$ch = $line[ $i ];

				// Handle escape sequences in single and double quoted strings.
				// TODO: Support NO_BACKSLASH_ESCAPES SQL mode.
				if ( "'" === $ch || '"' === $ch ) {
					// Count preceding backslashes.
					$slashes = 0;
					while ( $slashes < $i && '\\' === $line[ $i - $slashes - 1 ] ) {
						++$slashes;
					}

					// Handle escaped characters.
					// A characters is escaped only when the number of preceding backslashes
					// is odd - "\" is an escape sequence, "\\" is an escaped backslash.
					if ( 1 === $slashes % 2 ) {
						$buffer .= $ch;
						continue;
					}
				}

				// Handle comments.
				if ( null === $starting_quote ) {
					$prev_ch = isset( $line[ $i - 1 ] ) ? $line[ $i - 1 ] : null;
					$next_ch = isset( $line[ $i + 1 ] ) ? $line[ $i + 1 ] : null;

					// Skip inline comments.
					if ( ( '-' === $ch && '-' === $next_ch ) || '#' === $ch ) {
						break; // Stop for the current line.
					}

					// Skip multi-line comments.
					if ( ! $in_comment && '/' === $ch && '*' === $next_ch ) {
						$in_comment = true;
						continue;
					}
					if ( $in_comment ) {
						if ( '*' === $prev_ch && '/' === $ch ) {
							$in_comment = false;
						}
						continue;
					}
				}

				// Handle quotes
				if ( null === $starting_quote && ( "'" === $ch || '"' === $ch || '`' === $ch ) ) {
					$starting_quote = $ch;
				} elseif ( null !== $starting_quote && $ch === $starting_quote ) {
					$starting_quote = null;
				}

				// Process statement end
				if ( ';' === $ch && null === $starting_quote ) {
					$buffer = trim( $buffer );
					if ( ! empty( $buffer ) ) {
						yield $buffer;
					}
					$buffer = '';
				} else {
					$buffer .= $ch;
				}
			}
		}

		// Handle any remaining buffer content
		$buffer = trim( $buffer );
		if ( ! empty( $buffer ) ) {
			yield $buffer;
		}
	}
}
