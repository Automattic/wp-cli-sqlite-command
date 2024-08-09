<?php
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use WP_CLI\Tests\Context\FeatureContext as WPCLIFeatureContext;

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
	public function theSqliteDatabaseShouldContainATableNamed( $tableName ) {
		$this->connectToDatabase();
		$result = $this->db->query( "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'" );
		$row    = $result->fetchArray();
		if ( ! $row ) {
			throw new Exception( "Table '$tableName' not found in the database." );
		}
	}

	/**
	 * @Then /^the "([^"]*)" should contain a row with name "([^"]*)"$/
	 */
	public function theTableShouldContainARowWithName( $tableName, $name ) {
		$this->connectToDatabase();
		$result = $this->db->query( "SELECT * FROM $tableName WHERE name='$name'" );
		$row    = $result->fetchArray();
		if ( ! $row ) {
			throw new Exception( "Row with name '$name' not found in table '$tableName'." );
		}
	}

	/**
	 * @Then /^the SQLite database should contain the imported data$/
	 */
	public function theSqliteDatabaseShouldContainTheImportedData() {
		$this->connectToDatabase();
		$result = $this->db->query( "SELECT name FROM sqlite_master WHERE type='table'" );
		$tables = [];
		while ( $row = $result->fetchArray() ) {
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

	/**
	 * @BeforeScenario
	 */
	public function beforeScenario( BeforeScenarioScope $scope ) {
		parent::beforeScenario( $scope );
		$this->variables['DB_TYPE'] = 'sqlite';
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
}
