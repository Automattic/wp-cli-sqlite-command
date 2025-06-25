automattic/wp-cli-sqlite-command
================================

[![Code Quality Checks](https://github.com/Automattic/wp-cli-sqlite-command/actions/workflows/code-quality.yml/badge.svg)](https://github.com/Automattic/wp-cli-sqlite-command/actions/workflows/code-quality.yml)

Imports and exports SQLite databases using WP-CLI.

## Using

### wp sqlite import

Imports a MySQL compatible file into an SQLite database.

```
$ wp sqlite import <file>
```

**OPTIONS**

	<file>
		The path to the MySQL compatible dump file to import. When passing `-` as the file argument, the SQL commands are read from standard input.

### wp sqlite export

Exports an SQLite database to a MySQL compatible file.

```
$ wp sqlite export [<file>] [--tables=<tables>] [--exclude-tables] [--porcelain]
```

**OPTIONS**

	[<file>]
		Path to the file to write the MySQL compatible dump to. If not provided, the SQL commands are written to standard output.

	[--tables=<tables>]
		List of tables to export. Use commas to separate multiple table names.

	[--exclude-tables]
		Exclude certain tables from the export. Use commas to separate multiple table names.

	[--porcelain]
		Output filename for the exported database.

### wp sqlite tables

Lists the SQLite database tables.

```
$ wp sqlite tables [--format=<list|csv>]
```

**OPTIONS**

	[--format=<format>]
		Render output in a specific format.
		---
		Default: list
		Options:
			- list
			- csv
		---

**EXAMPLES**

```
    # List all tables
    $ wp sqlite tables
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

	* List all tables in CSV format
	$ wp sqlite tables --format=csv
	wp_users,wp_usermeta,wp_termmeta,wp_terms,wp_term_taxonomy,wp_term_relationships,wp_commentmeta,wp_comments,wp_links,wp_options,wp_postmeta,wp_posts
```
