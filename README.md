automattic/wp-cli-sqlite-command
================================

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

