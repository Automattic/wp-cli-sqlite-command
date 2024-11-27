# Testing

This repo uses the [BDD](https://en.wikipedia.org/wiki/Behavior-driven_development) testing framework [Behat](https://docs.behat.org/en/latest/). It means we use plain text files to describe "scenarios" containing keywords like "given", "when", and "then". This methodology was [chosen](https://github.com/Automattic/wp-cli-sqlite-command/issues/3) because it's what WP CLI itself uses to [test commands](https://github.com/wp-cli/wp-cli-tests).

The test cases are located in `features/*.feature`.

## Running tests locally

The test cases depend on real WordPress installations, meaning the usual PHP and MySQL dependencies are required. However, we can opt to use SQLite instead of MySQL to make running the tests locally easier. Here's how to run the full test suite with a SQLite database:

```
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat
```
