Feature: WP-CLI SQLite Tables Command
  In order to export individual tables from a WordPress SQLite table
  As a website administrator
  I need to be able to list the tables contained in the database

  Background:
    Given a WP installation

  @require-sqlite
  Scenario: Successfully list the tables in the SQLite database
    When I run `wp sqlite tables`
    Then STDOUT should contain:
      """
      wp_users
      wp_usermeta
      wp_termmeta
      wp_terms
      wp_term_taxonomy
      wp_term_relationships
      wp_commentmeta
      wp_comments
      wp_links
      wp_options
      wp_postmeta
      wp_posts
      hello
      """

  @require-sqlite
  Scenario: Successfully list the tables in the SQLite database in a CSV format
    When I run `wp sqlite tables --format=csv`
    Then STDOUT should contain:
      """
      wp_users,wp_usermeta,wp_termmeta,wp_terms,wp_term_taxonomy,wp_term_relationships,wp_commentmeta,wp_comments,wp_links,wp_options,wp_postmeta,wp_posts
      """
