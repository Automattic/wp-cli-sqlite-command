<?php
/**
 * Stubs for the WordPress SQLite integration plugin classes.
 *
 * These classes are loaded at runtime by WordPress, not via Composer.
 * The stubs allow PHPStan to analyze code that references them.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations

class WP_SQLite_Connection {
	/**
	 * @param array{path: string} $config
	 */
	public function __construct( array $config ) {}
	public function quote_identifier( string $identifier ): string {}
	public function get_pdo(): PDO {}
}

class WP_SQLite_Driver {
	public function __construct( WP_SQLite_Connection $connection, string $db_name ) {}
	public function query( string $sql ) {}
	public function get_connection(): WP_SQLite_Connection {}
}

class WP_SQLite_Translator {
	public function __construct() {}
	public function query( string $sql ) {}
	public function get_pdo(): PDO {}
}
