<?php


class Sqlite_Command extends WP_CLI_Command {

	/**
	 * Imports the database to SQLite.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The file to import or - for stdin.
	 */
	public function import($args, $assoc_args) {
		WP_CLI::success( 'Importing database...' );

		$import = new WP_CLI\SQLite\Import();

		$file = $args[0];

		$import->run( $file, $assoc_args );

	}


	/**
	 * Exports the database from SQLite
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The file to export to or - for stdout.
	 */
	public function export($args, $assoc_args) {
		WP_CLI::success( 'Exporting database...' );

		$export = new WP_CLI\SQLite\Export();
		$export->run( $args[0], $assoc_args );
	}
}