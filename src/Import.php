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
		$raw_queries  = file_get_contents( $import_file );
		$queries_text = $this->remove_comments( $raw_queries );
		$parser       = $this->driver->create_parser( $queries_text );
		while ( $parser->next_query() ) {
			$ast       = $parser->get_query_ast();
			$statement = substr( $queries_text, $ast->get_start(), $ast->get_length() );
			try {
				$this->driver->query( $statement );
			} catch ( Exception $e ) {
				WP_CLI::error( 'SQLite import could not execute statement: ' . $statement );
				echo $this->driver->get_error_message();
			}
		}
	}

	/**
	 * Remove comments from the input.
	 *
	 * @param string $input
	 *
	 * @return string
	 */
	protected function remove_comments( $text ) {
		return preg_replace( '/\/\*.*?\*\//s', '', $text );
	}
}
