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
      wp_commentmeta
      wp_comments
      wp_links
      wp_options
      wp_postmeta
      wp_posts
      wp_term_relationships
      wp_term_taxonomy
      wp_termmeta
      wp_terms
      wp_usermeta
      wp_users
      """

  @require-sqlite
  Scenario: Successfully list the tables in the SQLite database in a CSV format
    When I run `wp sqlite tables --format=csv`
    Then STDOUT should contain:
      """
      wp_commentmeta,wp_comments,wp_links,wp_options,wp_postmeta,wp_posts,wp_term_relationships,wp_term_taxonomy,wp_termmeta,wp_terms,wp_usermeta,wp_users
      """

  @require-sqlite
  Scenario: Successfully list the tables in the SQLite database in a CSV format
    When I run `wp sqlite tables --format=json`
    Then STDOUT should contain:
      """
      ["wp_commentmeta","wp_comments","wp_links","wp_options","wp_postmeta","wp_posts","wp_term_relationships","wp_term_taxonomy","wp_termmeta","wp_terms","wp_usermeta","wp_users"]
      """
