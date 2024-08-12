Feature: WP-CLI SQLite Export Command
  In order to backup or migrate a WordPress SQLite database
  As a website administrator
  I need to be able to export data from SQLite databases using WP-CLI

  Background:
    Given a WP installation
    And the SQLite database contains some sample data

  @require-sqlite
  Scenario: Successfully export the entire SQLite database
    When I run `wp sqlite export test_export.sql`
    Then STDOUT should contain:
      """
      Success: Exporting database...
      Success: Export complete. File written to test_export.sql
      """
    And the file "test_export.sql" should exist
    And the file "test_export.sql" should contain:
      """
      CREATE TABLE
      """
    And the file "test_export.sql" should contain:
      """
      INSERT INTO
      """

  @require-sqlite
  Scenario: Export specific tables
    When I run `wp sqlite export test_export_specific.sql --tables=wp_posts,wp_users`
    Then STDOUT should contain:
      """
      Success: Exporting database...
      Success: Export complete. File written to test_export_specific.sql
      """
    And the file "test_export_specific.sql" should exist
    And the file "test_export_specific.sql" should contain:
      """
      CREATE TABLE wp_posts
      """
    And the file "test_export_specific.sql" should contain:
      """
      CREATE TABLE wp_users
      """
    But the file "test_export_specific.sql" should not contain:
      """
      CREATE TABLE wp_options
      """

  @require-sqlite
  Scenario: Export all tables except specific ones
    When I run `wp sqlite export test_export_exclude.sql --exclude_tables=wp_posts,wp_users`
    Then STDOUT should contain:
      """
      Success: Exporting database...
      Success: Export complete. File written to test_export_exclude.sql
      """
    And the file "test_export_exclude.sql" should exist
    And the file "test_export_exclude.sql" should not contain:
      """
      CREATE TABLE wp_posts
      """
    And the file "test_export_exclude.sql" should not contain:
      """
      CREATE TABLE wp_users
      """
    But the file "test_export_exclude.sql" should contain:
      """
      CREATE TABLE wp_options
      """

  @require-sqlite
  Scenario: Export to STDOUT
    When I run `wp sqlite export -`
    Then STDOUT should contain:
      """
      -- Dumping data for table
      """
    And STDOUT should contain:
      """
      CREATE TABLE
      """
    And STDOUT should contain:
      """
      INSERT INTO
      """

  @require-sqlite
  Scenario: Export with porcelain flag
    When I run `wp sqlite export --porcelain`
    Then STDOUT should match /^[\w-]+\.sql$/
    And STDOUT should not contain:
      """
      Success: Exporting database...
      """

  @require-sqlite
  Scenario: Export with both --tables and --exclude_tables
    When I run `wp sqlite export test_export_both.sql --tables=wp_posts,wp_users,wp_options --exclude_tables=wp_users`
    Then STDOUT should contain:
      """
      Success: Exporting database...
      Success: Export complete. File written to test_export_both.sql
      """
    And the file "test_export_both.sql" should exist
    And the file "test_export_both.sql" should contain:
      """
      CREATE TABLE wp_posts
      """
    And the file "test_export_both.sql" should contain:
      """
      CREATE TABLE wp_options
      """
    But the file "test_export_both.sql" should not contain:
      """
      CREATE TABLE wp_users
      """
