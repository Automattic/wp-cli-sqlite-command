<?php

namespace Automattic\WP_CLI\SQLite;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use WP_CLI\Tests\Context\FeatureContext as WPCLIFeatureContext;
use PDO;
use Exception;

class SQLiteFeatureContext extends WPCLIFeatureContext implements Context {

	private $db;

	/**
	 * @Given /^a SQL dump file named "([^"]*)" with content:$/
	 */
	public function aSqlDumpFileNamedWithContent( $filename, PyStringNode $content ) {
		$this->create_file( $filename, $content );
	}

	/**
	 * @Then /^the SQLite database should contain a table named "([^"]*)"$/
	 */
	public function theSqliteDatabaseShouldContainATableNamed( $table_name ) {
		$this->connectToDatabase();
		$stmt = $this->db->prepare( "SELECT name FROM sqlite_master WHERE type='table' AND name=?" );
		$stmt->execute( [ $table_name ] );
		$row = $stmt->fetch();
		if ( ! $row ) {
			throw new Exception( "Table '$table_name' not found in the database." );
		}
	}

	/**
	 * @Then /^the "([^"]*)" should contain a row with name "([^"]*)"$/
	 * @Then /^the "([^"]*)" should contain a row with name:$/
	 */
	public function theTableShouldContainARowWithName( $table_name, $name ) {
		$this->connectToDatabase();
		$stmt = $this->db->prepare( "SELECT * FROM $table_name WHERE name=?" );
		$stmt->execute( [ $name ] );
		$row = $stmt->fetch();
		if ( ! $row ) {
			throw new Exception( "Row with name '$name' not found in table '$table_name'." );
		}
	}

	/**
	 * @Then /^the SQLite database should contain the imported data$/
	 */
	public function theSqliteDatabaseShouldContainTheImportedData() {
		$this->connectToDatabase();
		$result = $this->db->query( "SELECT name FROM sqlite_master WHERE type='table'" );
		$tables = $result->fetchAll( PDO::FETCH_COLUMN );
		if ( empty( $tables ) ) {
			throw new Exception( 'No tables found in the database after import.' );
		}
	}

	/**
	 * @Then /^the file "([^"]*)" should exist$/
	 */
	public function theFileShouldExist( $filename ) {
		$full_path = $this->variables['RUN_DIR'] . '/' . $filename;
		if ( ! file_exists( $full_path ) ) {
			throw new Exception( "File does not exist: $full_path" );
		}
	}

	private function connectToDatabase() {
		if ( ! $this->db ) {
			$run_dir  = $this->variables['RUN_DIR'];
			$db_file  = $run_dir . '/wp-content/database/.ht.sqlite';
			$this->db = new PDO( 'sqlite:' . $db_file );
			$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		}
	}

	/**
	 * Create a file with given filename and content.
	 *
	 * @param string $filename
	 * @param string|PyStringNode $content
	 */
	protected function create_file( $filename, $content ) {
		$content = $this->replace_variables( (string) $content );
		$run_dir = $this->variables['RUN_DIR'];

		if ( strpos( $filename, '/' ) !== false ) {
			$subdir   = dirname( $filename );
			$run_dir .= "/$subdir";
			if ( ! is_dir( $run_dir ) ) {
				mkdir( $run_dir, 0777, true );
			}
		}

		$full_path = $run_dir . '/' . basename( $filename );

		file_put_contents( $full_path, $content );
	}

	/**
	 * @Given /^the SQLite database contains some sample data$/
	 */
	public function theSqliteDatabaseContainsSomeSampleData() {
		$this->connectToDatabase();
		$this->db->exec(
			"
				INSERT OR REPLACE INTO wp_posts (ID, post_title, post_content, post_type, post_status)
				VALUES (1, 'Sample Post', 'This is a sample post content.', 'post', 'publish');
			"
		);

		// Insert or update sample data in wp_users
		$this->db->exec(
			"
				INSERT OR REPLACE INTO wp_users (ID, user_login, user_pass, user_nicename, user_email)
				VALUES (1, 'testuser', 'password_hash', 'Test User', 'testuser@example.com');
			"
		);

		// Insert or update sample data in wp_options
		$this->db->exec(
			"
				INSERT OR REPLACE INTO wp_options (option_id, option_name, option_value, autoload)
				VALUES
				(1, 'siteurl', 'http://example.com', 'yes'),
				(2, 'blogname', 'Test Blog', 'yes'),
				(3, 'blogdescription', 'Just another WordPress site', 'yes'),
				(4, 'users_can_register', '0', 'yes'),
				(5, 'admin_email', 'admin@example.com', 'yes'),
				(6, 'start_of_week', '1', 'yes'),
				(7, 'use_balanceTags', '0', 'yes'),
				(8, 'use_smilies', '1', 'yes'),
				(9, 'require_name_email', '1', 'yes'),
				(10, 'comments_notify', '1', 'yes');
			"
		);
	}

	/**
	 * @Then /^the file "([^"]*)" should contain:$/
	 */
	public function theFileShouldContain( $filename, PyStringNode $content ) {
		$full_path    = $this->variables['RUN_DIR'] . '/' . $filename;
		$file_content = file_get_contents( $full_path );
		if ( strpos( $file_content, (string) $content ) === false ) {
			throw new Exception( "File does not contain expected content:\n" . $content );
		}
	}

	/**
	 * @Then /^the file "([^"]*)" should not contain:$/
	 */
	public function theFileShouldNotContain( $filename, PyStringNode $content ) {
		$full_path    = $this->variables['RUN_DIR'] . '/' . $filename;
		$file_content = file_get_contents( $full_path );
		if ( strpos( $file_content, (string) $content ) !== false ) {
			throw new Exception( "File contains unexpected content:\n" . $content );
		}
	}

	/**
	 * @Given /^the SQLite database contains a serialized settings option with a NUL byte separator$/
	 */
	public function theSqliteDatabaseContainsASerializedSettingsOptionWithANulByteSeparator() {
		$this->connectToDatabase();

		$serialized_settings = array(
			'layout'               => 'full-width',
			'breadcrumb-separator' => "\0" . '0bb',
			'global-color-palette' => array(
				'palette' => array(
					'#ffffff',
					'#000000',
					'#0170b9',
				),
			),
		);

		$this->db->exec( "DELETE FROM wp_options WHERE option_name = 'serialized-theme-settings'" );

		$stmt = $this->db->prepare(
			'
			INSERT INTO wp_options (option_name, option_value, autoload)
			VALUES (:option_name, :option_value, :autoload)
			'
		);
		$stmt->execute(
			array(
				':option_name'  => 'serialized-theme-settings',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress options store PHP-serialized values.
				':option_value' => serialize( $serialized_settings ),
				':autoload'     => 'yes',
			)
		);
	}

	/**
	 * @Then /^the serialized settings option should preserve the NUL byte and color palette$/
	 */
	public function theSerializedSettingsOptionShouldPreserveTheNulByteAndColorPalette() {
		$this->connectToDatabase();

		$stmt = $this->db->prepare( "SELECT option_value FROM wp_options WHERE option_name = 'serialized-theme-settings'" );
		$stmt->execute();
		$option_value = $stmt->fetchColumn();
		if ( false === $option_value ) {
			throw new Exception( 'The serialized settings option was not found.' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Verifies WordPress PHP-serialized option data round-trips.
		$settings = unserialize( $option_value );
		if ( ! is_array( $settings ) ) {
			throw new Exception( 'The serialized settings option did not unserialize to an array.' );
		}

		if ( "\0" . '0bb' !== $settings['breadcrumb-separator'] ) {
			throw new Exception( 'The settings breadcrumb separator did not preserve its leading NUL byte.' );
		}

		if ( ! isset( $settings['global-color-palette']['palette'][2] ) || '#0170b9' !== $settings['global-color-palette']['palette'][2] ) {
			throw new Exception( 'The settings global color palette was not preserved.' );
		}
	}

	/**
	 * @Given /^the SQLite database contains a test table with alphanumeric string hash values$/
	 */
	public function theSqliteDatabaseContainsATestTableWithAlphanumericStringHashValues() {
		$this->connectToDatabase();

		// Create a test table with hash values that look like scientific notation
		$this->db->exec( 'DROP TABLE IF EXISTS test_export_alphanumeric_string' );
		$this->db->exec(
			'
			CREATE TABLE test_export_alphanumeric_string (
				id INTEGER PRIMARY KEY,
				hash_value TEXT
			)
		'
		);

		// Insert test data with values that might be mistaken for scientific notation
		$this->db->exec(
			"
			INSERT INTO test_export_alphanumeric_string (id, hash_value) VALUES
			(1, '123e99')
		"
		);
	}
}
