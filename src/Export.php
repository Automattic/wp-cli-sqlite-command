<?php
namespace Automattic\WP_CLI\SQLite;

use Exception;
use PDO;
use WP_CLI;
use WP_MySQL_On_SQLite;

class Export {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	protected $driver;

	protected $args      = array();
	protected $is_stdout = false;

	public function __construct() {
		SQLiteDatabaseIntegrationLoader::load_plugin();
		$this->driver = SQLiteDriverFactory::create_driver();
	}

	/**
	 * Run the export command.
	 *
	 * @param string $result_file The file to write the exported data to.
	 * @param array  $args        The arguments passed to the command.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function run( $result_file, $args ) {
		$this->args = $args;

		$handle = $this->open_output_stream( $result_file );

		$this->write_sql_statements( $handle );
		$this->close_output_stream( $handle );

		$this->display_result_message( $result_file );
	}

	/**
	 * Get output stream for the export.
	 *
	 * @param $result_file
	 *
	 * @return false|resource
	 * @throws WP_CLI\ExitException
	 */
	protected function open_output_stream( $result_file ) {
		$this->is_stdout = '-' === $result_file;
		$handle          = $this->is_stdout ? fopen( 'php://stdout', 'w' ) : fopen( $result_file, 'w' );
		if ( ! $handle ) {
			WP_CLI::error( "Unable to open file: $result_file" );
		}
		return $handle;
	}

	/**
	 * Close the output stream.
	 *
	 * @param $handle
	 *
	 * @return void
	 * @throws WP_CLI\ExitException
	 */
	protected function close_output_stream( $handle ) {
		if ( ! fclose( $handle ) ) {
			WP_CLI::error( 'Error closing output stream.' );
		}
	}

	/**
	 * Write SQL statements to the output stream.
	 *
	 * @param resource $handle The output stream.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function write_sql_statements( $handle ) {
		$include_tables = $this->get_include_tables();
		$exclude_tables = $this->get_exclude_tables();
		foreach ( $this->get_table_names() as $table_name ) {

			// Skip tables that are not in the include_tables list if the list is defined
			if ( ! empty( $include_tables ) && ! in_array( $table_name, $include_tables, true ) ) {
				continue;
			}

			// Skip tables that are in the exclude_tables list
			if ( in_array( $table_name, $exclude_tables, true ) ) {
				continue;
			}

			$this->write_create_table_statement( $handle, $table_name );
			$this->write_insert_statements( $handle, $table_name );
		}

		fwrite( $handle, sprintf( '-- Dump completed on %s', gmdate( 'c' ) ) );
	}

	/**
	 * Get the database table names.
	 *
	 * @return string[]
	 */
	protected function get_table_names() {
		$result = $this->driver->query( 'SHOW TABLES' );
		return $result->fetchAll( PDO::FETCH_COLUMN );
	}

	/**
	 * Write the create statement for a table to the output stream.
	 *
	 * @param resource $handle
	 * @param string   $table_name
	 *
	 * @throws Exception
	 */
	protected function write_create_table_statement( $handle, $table_name ) {
		$quoted_table_name = $this->quote_identifier( $table_name );
		$comment           = $this->get_dump_comment( sprintf( 'Table structure for table %s', $quoted_table_name ) );
		fwrite( $handle, $comment . PHP_EOL . PHP_EOL );
		fwrite( $handle, sprintf( 'DROP TABLE IF EXISTS %s;', $quoted_table_name ) . PHP_EOL );
		fwrite( $handle, $this->get_create_statement( $table_name ) . PHP_EOL );
	}

	/**
	 * Write the insert statements for a table to the output stream.
	 *
	 * @param $handle
	 * @param $table_name
	 *
	 * @return void
	 */
	protected function write_insert_statements( $handle, $table_name ) {

		if ( ! $this->table_has_records( $table_name ) ) {
			return;
		}

		$comment = $this->get_dump_comment( sprintf( 'Dumping data for table %s', $this->quote_identifier( $table_name ) ) );
		fwrite( $handle, $comment . PHP_EOL . PHP_EOL );
		foreach ( $this->get_insert_statements( $table_name ) as $insert_statement ) {
			fwrite( $handle, $insert_statement . PHP_EOL );
		}

		fwrite( $handle, PHP_EOL );
	}

	/**
	 * Get the CREATE TABLE statement for a table.
	 *
	 * @param string $table_name
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function get_create_statement( $table_name ) {
		$create = $this->driver->query( 'SHOW CREATE TABLE ' . $this->quote_identifier( $table_name ) );
		$sql    = $create->fetchColumn( 1 );
		return rtrim( $sql, ';' ) . ";\n";
	}

	/**
	 * Get the INSERT statements for a table.
	 *
	 * @param string $table_name
	 *
	 * @return \Generator
	 */
	protected function get_insert_statements( $table_name ) {
		$quoted_table_name = $this->quote_identifier( $table_name );
		$stmt              = $this->get_sqlite_pdo()->query( 'SELECT * FROM ' . $quoted_table_name );
		// phpcs:ignore
		while ( $row = $stmt->fetch( PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT ) ) {
			yield sprintf( 'INSERT INTO %1s VALUES (%2s);', $quoted_table_name, $this->escape_values( $row ) );
		}
	}

	/**
	 * Get the tables to exclude from the export.
	 *
	 * @return array|false|string[]
	 */
	protected function get_exclude_tables() {
		return isset( $this->args['exclude_tables'] ) ? explode( ',', $this->args['exclude_tables'] ) : [];
	}

	protected function display_result_message( $result_file ) {
		if ( $this->is_stdout ) {
			return;
		}

		if ( isset( $this->args['porcelain'] ) ) {
			WP_CLI::line( $result_file );
		} else {
			WP_CLI::success( 'Export complete. File written to ' . $result_file );
		}
	}

	/**
	 * Get the tables to include in the export.
	 *
	 * @return array|false|string[]
	 */
	protected function get_include_tables() {
		return isset( $this->args['tables'] ) ? explode( ',', $this->args['tables'] ) : [];
	}

	/**
	 * Escape values for insert statement
	 *
	 * @param $values
	 *
	 * @return string
	 */
	protected function escape_values( $values ) {
		$escaped_values = [];
		foreach ( $values as $value ) {
			if ( is_null( $value ) ) {
				$escaped_values[] = 'NULL';
			} elseif ( ctype_digit( $value ) ) {
				$escaped_values[] = $value;
			} else {
				// Quote the values and escape encode the newlines so the insert statement appears on a single line.
				$escaped_values[] = $this->escape_string( $value );
			}
		}
		return implode( ',', $escaped_values );
	}

	/**
	 * Escapes a string for use in an insert statement.
	 *
	 * @param $value
	 *
	 * @return string
	 */
	protected function escape_string( $value ) {
		return "'" . strtr(
			$value,
			array(
				"\0"   => '\0',
				"\n"   => '\n',
				"\r"   => '\r',
				"\x1a" => '\Z',
				'\\'   => '\\\\',
				"'"    => "\\'",
			)
		) . "'";
	}

	/**
	 * Get a comment for the dump.
	 *
	 * @param $comment
	 *
	 * @return string
	 */
	protected function get_dump_comment( $comment ) {
		$comment = str_replace( array( "\r\n", "\r" ), "\n", $comment );

		return implode(
			"\n",
			array( '--', sprintf( '-- %s', str_replace( "\n", "\n-- ", $comment ) ), '--' )
		);
	}

	/**
	 * Check if the given table has records.
	 *
	 * @param string $table_name
	 *
	 * @return bool
	 */
	protected function table_has_records( $table_name ) {
		$table_name = $this->quote_identifier( $table_name );
		$stmt       = $this->get_sqlite_pdo()->query( 'SELECT COUNT(*) FROM ' . $table_name );
		return $stmt->fetchColumn() > 0;
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
	 * Get the underlying SQLite PDO instance.
	 *
	 * Rows are read through the SQLite PDO directly so that large tables can be
	 * streamed row by row instead of being materialized by the driver.
	 *
	 * @return PDO
	 */
	protected function get_sqlite_pdo() {
		return $this->driver->get_connection()->get_pdo();
	}
}
