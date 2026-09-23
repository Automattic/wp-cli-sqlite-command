@require-sqlite
Feature: SQLite database storage
  Commands use the same database as WordPress before its database drop-in loads.

  Background:
    Given a WP installation

  Scenario: Use an existing legacy database without migrating storage
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO test_table VALUES (1, 'Imported data');
      """
    When I run `wp eval 'echo FQDB;'`
    Then save STDOUT as {DATABASE_PATH}
    When I run `cp "{DATABASE_PATH}" original.sqlite`
    And I run `rm -r wp-content/database`
    And I run `mkdir wp-content/database`
    And I run `mv original.sqlite wp-content/database/.ht.sqlite`
    And I run `wp sqlite export backup.sql`
    Then the backup.sql file should contain:
      """
      WP CLI Site
      """
    When I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      wp_options
      """
    When I run `wp sqlite import test_import.sql`
    And I run `wp sqlite export -`
    Then STDOUT should contain:
      """
      Imported data
      """
    And the wp-content/database/.ht.sqlite file should exist
    And the wp-content/database/db-path.php file should not exist

  Scenario: Only import initializes fresh default storage
    When I run `wp sqlite export backup.sql`
    And I run `rm -r wp-content/database`
    And I try `wp sqlite export missing.sql`
    Then the return code should be 1
    And the missing.sql file should not exist
    When I try `wp sqlite tables`
    Then the return code should be 1
    And the wp-content/database directory should not exist
    When I run `mkdir wp-content/database`
    And I run `wp sqlite import backup.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'backup.sql'.
      """
    When I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      wp_options
      """
    When I run `wp eval 'echo get_option( "blogname" );'`
    Then STDOUT should be:
      """
      WP CLI Site
      """

  Scenario: Use DB_PATH without changing existing storage
    When I run `wp eval 'echo FQDB;'`
    Then save STDOUT as {DATABASE_PATH}
    When I run `mkdir custom-database`
    And I run `cp "{DATABASE_PATH}" 'custom-database/custom;database.sqlite'`
    And I run `wp config set DB_PATH "dirname( __FILE__ ) . '/custom-database/custom;database.sqlite'" --raw`
    And I run `wp config set FQDB '{RUN_DIR}/unused.sqlite'`
    And I run `wp sqlite export backup.sql`
    And I run `wp sqlite import backup.sql`
    And I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      wp_options
      """
    And the unused.sqlite file should not exist
    And the custom-database/db-path.php file should not exist
    And the custom-database/.htaccess file should not exist
    And the custom-database/.ht.sqlite.lock file should not exist
    When I run `wp sqlite export -`
    Then STDOUT should contain:
      """
      WP CLI Site
      """

  Scenario: Only import creates a missing explicit database
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT);
      INSERT INTO test_table VALUES (1, 'Imported data');
      """
    When I run `wp config set DB_PATH '{RUN_DIR}/new.sqlite'`
    And I try `wp sqlite export backup.sql`
    Then the return code should be 1
    And STDERR should contain:
      """
      The SQLite database does not exist.
      """
    And the new.sqlite file should not exist
    And the backup.sql file should not exist
    When I try `wp sqlite tables`
    Then the return code should be 1
    And the new.sqlite file should not exist
    When I run `wp sqlite import test_import.sql`
    And I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      test_table
      """
    When I run `wp sqlite export -`
    Then STDOUT should contain:
      """
      Imported data
      """

  Scenario: Use the legacy FQDB override
    When I run `wp eval 'echo FQDB;'`
    Then save STDOUT as {DB_PATH}
    When I run `cp "{DB_PATH}" custom.sqlite`
    And I run `wp config set FQDB '{RUN_DIR}/custom.sqlite'`
    And I run `wp sqlite export backup.sql`
    And I run `wp sqlite import backup.sql`
    And I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      wp_options
      """
    When I run `wp eval 'echo FQDB;'`
    Then STDOUT should be:
      """
      {RUN_DIR}/custom.sqlite
      """
    When I run `wp eval 'echo get_option( "blogname" );'`
    Then STDOUT should be:
      """
      WP CLI Site
      """

  Scenario: Reject an invalid DB_PATH without falling back to existing storage
    Given a SQL dump file named "test_import.sql" with content:
      """
      CREATE TABLE test_table (id INTEGER PRIMARY KEY);
      """
    When I run `wp config set DB_PATH null --raw`
    And I try `wp sqlite import test_import.sql`
    Then the return code should be 1
    And STDERR should contain:
      """
      The SQLite database path is invalid.
      """
    When I try `wp sqlite export backup.sql`
    Then the return code should be 1
    And the backup.sql file should not exist
    When I try `wp sqlite tables`
    Then the return code should be 1
    When I run `wp config delete DB_PATH`
    And I run `wp sqlite tables`
    Then STDOUT should not contain:
      """
      test_table
      """
