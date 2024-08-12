Feature: WP-CLI SQLite Import Command
  In order to populate a WordPress SQLite database
  As a website administrator
  I need to be able to import data into SQLite databases using WP-CLI

  Background:
    Given a WP installation

  @require-sqlite
  Scenario: Successfully import a SQLite database
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO test_table (name) VALUES ('Test Name');
      """
    When I run `wp sqlite import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name "Test Name"

  @require-sqlite
  Scenario: Attempt to import without specifying a file
    When I try `wp sqlite import`
    Then STDOUT should contain:
      """
      usage: wp sqlite import <file>
      """
    And the return code should be 1

  @require-sqlite
  Scenario: Import from STDIN
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO test_table (name) VALUES ('Test Name');
      """
    When I run `cat test_import.sql | wp sqlite import -`
    Then STDOUT should contain:
      """
      Success: Imported from 'STDIN'.
      """
    And the SQLite database should contain the imported data
