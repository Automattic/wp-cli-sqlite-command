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

// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- Stub for the plugin's PDO subclass.
class WP_MySQL_On_SQLite extends PDO {
	public function get_connection(): WP_SQLite_Connection {}
	public function execute_sqlite_query( string $sql, array $params = array() ): PDOStatement {}
}
