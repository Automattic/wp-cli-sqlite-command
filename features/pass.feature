Feature: Always Passing Test
 Scenario: Evaluate true expression
    Given a WP install

    When I run `wp eval "var_export(true);"`
    Then STDOUT should contain:
      """
      true
      """
