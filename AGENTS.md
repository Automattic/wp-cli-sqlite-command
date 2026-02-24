# wp-cli-sqlite-command

WP-CLI package for importing and exporting SQLite databases, used by WordPress Studio to power the Import, Export, and Sync features.

## SQLiteCommands

### `wp sqlite import <file>`

Imports a MySQL dump file into the SQLite database. Parses SQL statements using a streaming character-by-character parser, sets default SQL modes (`NO_AUTO_VALUE_ON_ZERO`, disables unique/foreign key checks) to match `mysqldump` defaults, and attempts UTF-8 encoding conversion as a fallback when queries fail.

**Parameters:**

- `<file>` (required), Path to the SQL dump file. Use `-` to read from STDIN.
- `[--enable-ast-driver]` Use the new AST-based `WP_SQLite_Driver` instead of the legacy `WP_SQLite_Translator` for full MySQL compatibility.

### `wp sqlite export [<file>]`

Exports the SQLite database to a MySQL-compatible SQL dump file. Generates `DROP TABLE IF EXISTS`, `CREATE TABLE`, and `INSERT INTO` statements for each table. Automatically excludes internal SQLite system tables (`_mysql_data_types_cache`, `sqlite_master`, `sqlite_sequence`, `_wp_sqlite_*`).

**Parameters:**

- `[<file>]` (optional), Output file path. Use `-` to write to STDOUT. Defaults to `{Y-m-d}-{random-hash}.sql`.
- `[--tables=<tables>]` Comma-separated list of tables to include. If omitted, all tables are exported.
- `[--exclude_tables=<tables>]` Comma-separated list of tables to skip.
- `[--porcelain]` Output only the filename (for scripting).
- `[--enable-ast-driver]` Use the new AST-based driver.

### `wp sqlite tables`

Lists all user tables in the SQLite database, excluding internal/system tables. With the legacy driver, results are sorted alphabetically.

**Parameters:**

- `[--format=<format>]` Output format: `list` (default, one table per line), `csv`, or `json`.
- `[--enable-ast-driver]` Use the new AST-based driver.

## Tech Stack

- PHP >=7.4, WP-CLI package (`wp-cli/wp-cli ^2.5`)
- WordPress SQLite Database Integration plugin, translation layer between MySQL queries and SQLite
- Two driver modes: legacy `WP_SQLite_Translator` and new AST-based `WP_SQLite_Driver` (via `--enable-ast-driver`)

## Directory Structure

```txt
command.php          # Entry point, registers the `wp sqlite` CLI command
src/                 # Core classes: SQLite_Command, Import, Export, Tables
features/            # Behat BDD test scenarios (.feature files)
features/bootstrap/  # Behat step definitions (SQLiteFeatureContext)
manual_wp_test/      # Manual testing utilities
```

## Validation

### Lint

```sh
composer lint
```

### Code Style (PHPCS)

```sh
composer phpcs
```

Autofix:

```sh
composer phpcbf
```

Uses the `WP_CLI_CS` ruleset (WordPress Coding Standards). Config in `phpcs.xml.dist`.

### Test

```sh
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat
```

Tests require a WordPress installation. Using `WP_CLI_TEST_DBTYPE=sqlite` avoids the MySQL dependency.

### All Checks

```sh
composer test
```

Runs lint, phpcs, phpunit, and behat sequentially.

**IMPORTANT - Post-Change Verification**: After applying code changes, always run the linter (`composer lint`), code style checks (`composer phpcs`), and relevant tests (`WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat`) before considering the work complete.

## Conventions

- **Namespace:** `Automattic\WP_CLI\SQLite` all classes under `src/`
- **Global prefix:** `automattic_wpcli_sqlite` for any global variables or functions
- **Commits:** Imperative mood, descriptive subject. Reference PR/issue numbers (e.g., `Fix import query parsing #14`)
- **Branches:** Create from `main`. Prefixed with `fix/`, `update/`, or `add/` followed by a descriptive name
- **PRs:** Target `main` branch. MUST pass all CI checks (code quality + testing) before merge
- **Indentation:** Tabs for PHP files, spaces (2) for YAML, JSON, and `.feature` files (see `.editorconfig`)
- **Code style:** Enforced by PHPCS with `WP_CLI_CS` ruleset, do not duplicate linter rules
- **IMPORTANT:** Prefer merging `main` into your branch over rebasing. Avoid force pushes to `main` or already-pushed branches

## Architecture

- `command.php` is the entry point, it registers `wp sqlite` as a WP-CLI command pointing to `SQLite_Command`
- `SQLite_Command` delegates to `Import`, `Export`, and `Tables` classes
- `SQLiteDatabaseIntegrationLoader::load_plugin()` bootstraps the WordPress SQLite Database Integration plugin, which MUST be loaded before any database operations
- `SQLiteDriverFactory::create_driver()` creates the appropriate driver based on whether the AST driver is enabled
- The import parser (`Import::parse_statements()`) is a streaming SQL parser using PHP generators, it handles quotes, comments, escape sequences, and multi-line statements character by character

## Common Pitfalls

- **MUST load the SQLite plugin first:** All database operations require `SQLiteDatabaseIntegrationLoader::load_plugin()` to be called before creating a driver. The driver depends on classes from the WordPress SQLite Database Integration plugin.
- **Two driver APIs:** The legacy `WP_SQLite_Translator` and new `WP_SQLite_Driver` (AST) have different class names. Use `SQLiteDriverFactory::create_driver()` to get the correct one,do not instantiate drivers directly.
- **SQL parser edge cases:** The `Import::parse_statements()` method handles escape sequences, nested quotes, and multi-line comments manually. Changes to this parser MUST be tested with the existing Behat scenarios in `features/sqlite-import.feature`.
- **Encoding handling:** Import attempts UTF-8 conversion as a fallback when queries fail. This is intentional error recovery, not a bug.
- **`@when after_wp_config_load`:** All command methods use this WP-CLI hook annotation. New commands MUST include it to ensure WordPress config is loaded before execution.
- **PHPCS inline suppressions:** The codebase uses `@phpcs:disable` / `@phpcs:enable` comments around `define('WP_SQLITE_AST_DRIVER')` because the constant name doesn't follow the project prefix convention. This is intentional.

## Documentation

- **Testing guide:** `TESTING.md` How to run the Behat test suite locally with SQLite
- **README:** `README.md` CLI usage, options, and examples for all commands
