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

  @require-sqlite
  Scenario: Import a file with MySQL backslash escape sequences
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);
      INSERT INTO test_table (name) VALUES ('Test that escaping a backslash \\ works');
      INSERT INTO test_table (name) VALUES ('Test that escaping multiple backslashes \\\\\\ works');
      INSERT INTO test_table (name) VALUES ('Test that escaping a character \a works');
      INSERT INTO test_table (name) VALUES ('Test that escaping a backslash followed by a character \\a works');
      INSERT INTO test_table (name) VALUES ('Test that escaping a backslash and a character \\\a works');
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name "Test that escaping a backslash \ works"
    And the "test_table" should contain a row with name "Test that escaping multiple backslashes \\\ works"
    And the "test_table" should contain a row with name "Test that escaping a character a works"
    And the "test_table" should contain a row with name "Test that escaping a backslash followed by a character \a works"
    And the "test_table" should contain a row with name "Test that escaping a backslash and a character \a works"

  @require-sqlite
  Scenario: Import a file with newlines in strings
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);
      INSERT INTO test_table (name) VALUES ('Test that a string containing
          a newline character and some whitespace works');
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name:
      """
      Test that a string containing
          a newline character and some whitespace works
      """

  @require-sqlite
  Scenario: Import a file with comments
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);
      -- This is an inline comment.
      # This is an inline comment.
      INSERT INTO test_table (name) VALUES ('one'); -- This is an inline comment.
      /* This is a block comment */
      INSERT INTO test_table (name) VALUES ('two'); /* This
        is a block comment
        on multiple lines */ INSERT INTO test_table (name) VALUES ('three');
      INSERT INTO test_table (name) VALUES ('fo -- this looks like a comment ur');
      INSERT INTO test_table (name) VALUES ('fi/* this looks like a comment */ve');
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name "one"
    And the "test_table" should contain a row with name "two"
    And the "test_table" should contain a row with name "three"
    And the "test_table" should contain a row with name "fo -- this looks like a comment ur"
    And the "test_table" should contain a row with name "fi/* this looks like a comment */ve"

  @require-sqlite
  Scenario: Import a file quoted strings
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);
      INSERT INTO test_table (name) VALUES ('a single-quoted string with \' '' some " tricky ` chars');
      INSERT INTO test_table (name) VALUES ("a double-quoted string with ' some \" "" tricky ` chars");
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name:
      """
      a single-quoted string with ' ' some " tricky ` chars
      """
    And the "test_table" should contain a row with name:
      """
      a double-quoted string with ' some " " tricky ` chars
      """

  @require-sqlite
  Scenario: Import a file backtick-quoted identifiers
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE `a'strange``identifier\\name` (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """

    And the SQLite database should contain a table named "a'strange`identifier\\name"

  @require-sqlite
  Scenario: Import a file with whitespace and empty lines
    Given a SQL dump file named "test_import.sql" with content:
      """

      CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT);


      INSERT INTO test_table (name) VALUES ('Test Name');

      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """

    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name "Test Name"

  @require-sqlite
  Scenario: Import data that expect non-strict SQL mode to be set
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (
        id INTEGER,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT '0000-00-00 00:00:00'
      );
      INSERT INTO test_table (id) VALUES (1);
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """

    And the SQLite database should contain a table named "test_table"
    And the "test_table" should contain a row with name ""

  @require-sqlite
  Scenario: Import tables with foreign key constraints when child table is imported before parent
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE wp_aa_child (
        id INTEGER PRIMARY KEY,
        parent_id INTEGER NOT NULL,
        CONSTRAINT fk_parent FOREIGN KEY (parent_id) REFERENCES wp_zz_parent(id)
      );
      INSERT INTO wp_aa_child (id, parent_id) VALUES (1, 1);
      CREATE TABLE wp_zz_parent (id INTEGER PRIMARY KEY, name TEXT NOT NULL);
      INSERT INTO wp_zz_parent (id, name) VALUES (1, 'Parent Row');
      """
    When I run `wp sqlite --enable-ast-driver import test_import.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "wp_zz_parent"
    And the SQLite database should contain a table named "wp_aa_child"

  @require-sqlite
  Scenario: Refuse to overwrite an existing database
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
    When I try `wp sqlite import test_import.sql --refuse-overwrite`
    Then the return code should be 1
    And STDERR should contain:
      """
      already contains tables
      """
    And the SQLite database should contain a table named "test_table"

  @require-sqlite
  Scenario: Allow refuse-overwrite into a fresh database file
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO test_table (name) VALUES ('Test Name');
      """
    When I run `wp config set DB_PATH '{RUN_DIR}/fresh.sqlite'`
    And I run `wp sqlite import test_import.sql --refuse-overwrite`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "test_table"

  @require-sqlite
  Scenario: Failed import into a new database removes the new file
    Given a SQL dump file named "test_import_bad.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO missing_table (name) VALUES ('Test Name');
      """
    When I run `wp config set DB_PATH '{RUN_DIR}/atomic.sqlite'`
    And I try `wp sqlite import test_import_bad.sql`
    Then the return code should be 1
    And STDERR should contain:
      """
      Could not execute statement
      """
    And the atomic.sqlite file should not exist
    And the atomic.sqlite-wal file should not exist
    And the atomic.sqlite-shm file should not exist
    And the atomic.sqlite-journal file should not exist

  @require-sqlite
  Scenario: Fail verification when the old host remains
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE verify_host (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO verify_host (name) VALUES ('https://oldhost.invalid/page');
      """
    When I try `wp sqlite import test_import.sql --verify-old-host=oldhost.invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      still contain 'oldhost.invalid'
      """
    And the SQLite database should not contain a table named "verify_host"

  @require-sqlite
  Scenario: Pass verification when the old host is gone
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE verify_host (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO verify_host (name) VALUES ('https://newhost.invalid/page');
      """
    When I run `wp sqlite import test_import.sql --verify-old-host=oldhost.invalid`
    Then STDOUT should contain:
      """
      Success: Imported from 'test_import.sql'.
      """
    And the SQLite database should contain a table named "verify_host"
    And the "verify_host" should contain a row with name "https://newhost.invalid/page"

  @require-sqlite
  Scenario: Fail verification on corrupt serialized data
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE verify_serialized (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO verify_serialized (name) VALUES ('a:1:{s:3:"foo";s:4:"broken}');
      """
    When I try `wp sqlite import test_import.sql --verify-old-host=never-present.invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      invalid serialized data
      """
    And the SQLite database should not contain a table named "verify_serialized"
