<?php
namespace Automattic\WP_CLI\SQLite;

use Exception;
use Generator;
use WP_CLI;
use WP_SQLite_Translator;

class Import {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_SQLite_Driver|WP_SQLite_Translator
	 */
	protected $driver;

	protected $args;

	public function __construct() {
		SQLiteDatabaseIntegrationLoader::load_plugin();
		$this->driver = SQLiteDriverFactory::create_driver();
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

		$this->execute_statements( $import_file );

		$this->driver->query( 'SET SQL_MODE=@BACKUP_SQL_MODE' );
		$this->driver->query( 'SET UNIQUE_CHECKS=@BACKUP_UNIQUE_CHECKS' );
		$this->driver->query( 'SET FOREIGN_KEY_CHECKS=@BACKUP_FOREIGN_KEY_CHECKS' );

		$imported_from = $is_stdin ? 'STDIN' : $sql_file_path;
		WP_CLI::success( sprintf( "Imported from '%s'.", $imported_from ) );
	}

	/**
	 * Execute SQL statements from an SQL dump file.
	 *
	 * @param $import_file
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function execute_statements( $import_file ) {
		foreach ( $this->parse_statements( $import_file ) as $statement ) {
			try {
				$this->driver->query( $statement );
			} catch ( Exception $e ) {
				try {
					// Try converting encoding and retry
					$detected_encoding = mb_detect_encoding( $statement, mb_list_encodings(), true );
					if ( $detected_encoding && 'UTF-8' !== $detected_encoding ) {
						$converted_statement = mb_convert_encoding( $statement, 'UTF-8', $detected_encoding );
						echo 'Converted ecoding for statement: ' . $converted_statement . PHP_EOL;
						$this->driver->query( $converted_statement );
					} else {
						// It's not an encoding issue, so rethrow the exception.
						throw $e;
					}
				} catch ( Exception $e ) {
					WP_CLI::error( 'Could not execute statement: ' . $statement . '. Error: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Parse SQL statements from an SQL dump file.
	 * @param string $sql_file_path The path to the SQL dump file.
	 *
	 * @return Generator A generator that yields SQL statements.
	 */
	public function parse_statements( $sql_file_path ) {

		$handle = fopen( $sql_file_path, 'r' );

		if ( ! $handle ) {
			WP_CLI::error( "Unable to open file: $sql_file_path" );
		}

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

		fclose( $handle );
	}
}
