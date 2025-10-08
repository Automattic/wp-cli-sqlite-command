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

		if ( method_exists( $this->driver, 'create_parser' ) ) {
			$this->execute_statements_with_ast_parser( $import_file );
		} else {
			$this->execute_statements( $import_file );
		}

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

				// Handle escaped characters
				if ( $i > 0 && '\\' === $line[ $i - 1 ] ) {
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

	/**
	 * Execute SQL statements from an SQL dump file using the AST parser.
	 *
	 * @param $import_file
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function execute_statements_with_ast_parser( $import_file ) {
		$raw_queries = file_get_contents( $import_file );

		// Detect and convert encoding to UTF-8
		$detected_encoding = mb_detect_encoding( $raw_queries, mb_list_encodings(), true );
		if ( $detected_encoding && 'UTF-8' !== $detected_encoding ) {
			$raw_queries = mb_convert_encoding( $raw_queries, 'UTF-8', $detected_encoding );
		}

		$parser = $this->driver->create_parser( $raw_queries );
		while ( $parser->next_query() ) {
			$ast       = $parser->get_query_ast();
			$statement = substr( $raw_queries, $ast->get_start(), $ast->get_length() );
			try {
				$this->driver->query( $statement );
			} catch ( Exception $e ) {
				// Skip errors when executing SET comment statements
				if ( 0 === strpos( $statement, 'SET ' ) && false !== strpos( $statement, '*/' ) ) {
					echo 'Warning - SQLite import skipped SET comment statement: ' . $statement . PHP_EOL;
					continue;
				}
				WP_CLI::error( 'SQLite import could not execute statement: ' . $statement );
				echo $this->driver->get_error_message();
			}
		}
	}
}
