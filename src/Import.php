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

		$this->execute_statements( $import_file );

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
			$result = $this->driver->query( $statement );
			if ( false === $result ) {
				WP_CLI::warning( 'Could not execute statement: ' . $statement );
				echo $this->driver->get_error_message();
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

		$single_quotes = 0;
		$double_quotes = 0;
		$in_comment    = false;
		$buffer        = '';

		// phpcs:ignore
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$line = trim( $line );

			// Skip empty lines and comments
			if ( empty( $line ) || strpos( $line, '--' ) === 0 || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			// Handle multi-line comments
			if ( ! $in_comment && strpos( $line, '/*' ) === 0 ) {
				$in_comment = true;
			}
			if ( $in_comment ) {
				if ( strpos( $line, '*/' ) !== false ) {
					$in_comment = false;
				}
				continue;
			}

			$strlen = strlen( $line );
			for ( $i = 0; $i < $strlen; $i++ ) {
				$ch = $line[ $i ];

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

				// Handle quotes
				if ( "'" === $ch && 0 === $double_quotes ) {
					$single_quotes = 1 - $single_quotes;
				}
				if ( '"' === $ch && 0 === $single_quotes ) {
					$double_quotes = 1 - $double_quotes;
				}

				// Process statement end
				if ( ';' === $ch && 0 === $single_quotes && 0 === $double_quotes ) {
					yield trim( $buffer );
					$buffer = '';
				} else {
					$buffer .= $ch;
				}
			}
		}

		// Handle any remaining buffer content
		if ( ! empty( $buffer ) ) {
			yield trim( $buffer );
		}

		fclose( $handle );
	}
}
