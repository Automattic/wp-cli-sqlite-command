<?php

namespace Automattic\WP_CLI\SQLite;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use WP_CLI\Tests\Context\FeatureContext as WPCLIFeatureContext;
use SQLite3;
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
		$result = $this->db->query( "SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name'" );
		$row    = $result->fetchArray();
		if ( ! $row ) {
			throw new Exception( "Table '$table_name' not found in the database." );
		}
	}

	/**
	 * @Then /^the "([^"]*)" should contain a row with name "([^"]*)"$/
	 */
	public function theTableShouldContainARowWithName( $table_name, $name ) {
		$this->connectToDatabase();
		$result = $this->db->query( "SELECT * FROM $table_name WHERE name='$name'" );
		$row    = $result->fetchArray();
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
		$tables = [];
		while ( true ) {
			$row = $result->fetchArray();
			if ( false === $row ) {
				break;
			}
			$tables[] = $row['name'];
		}
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
			$this->db = new SQLite3( $db_file );
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


	////
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
}
