# LiteDB

A lightweight, single-file database manager in the spirit of Adminer and phpMiniAdmin.
Drop `litedb.php` on a PHP server, open it in the browser, and log in.

- **Databases:** MySQL / MariaDB, PostgreSQL, SQLite (via PDO)
- **PHP:** 5.6 up to 8.5
- **Requirements:** the PDO extension plus the driver you need (`pdo_mysql`, `pdo_pgsql` or `pdo_sqlite`)
- **License:** MIT

## Features

### Connection and login
- Login for MySQL/MariaDB, PostgreSQL and SQLite (SQLite uses a file path instead of credentials)
- Server as `host`, `host:port` or a Unix socket path
- Configurable defaults for driver, server, username, password and database
- Default password is never sent to the browser, and is only used when username and server match the defaults
- Optional extra access password and IP allow-list
- Optional restriction of SQLite files to a single directory
- Read-only mode that blocks writes from the interface
- Startup validation of the configuration, with problems shown on every page (unknown options, wrong types, missing PDO extensions, unreadable directories and more)

### Databases and schemas
- List, create and drop databases (MySQL/MariaDB and PostgreSQL)
- Collation dropdown when creating a MySQL/MariaDB database, with `(default)` first
- PostgreSQL schema switcher in the sidebar

### Tables and views
- Overview of tables and views with engine, row count and size
- Bulk empty and drop of selected tables
- Create tables with a dynamic column builder: add or remove columns, type suggestions, default, nullable, primary key and auto-increment
- Engine and collation dropdowns when creating a MySQL/MariaDB table, with `(default)` first
- Rename tables
- MySQL/MariaDB: change engine and convert collation
- Sidebar table list with live filter

### Browsing and editing data
- Paging with a selectable page size, and sortable columns
- Search by column with operators `=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IS NULL`, `IS NOT NULL`
- Insert, edit and delete rows; rows are identified by primary key, or by `rowid` / `ctid` / all columns when there is none
- Edits only update the columns that actually changed
- `NULL` checkbox per column; typing in a field clears it
- `ENUM` columns as dropdowns (MySQL/MariaDB and PostgreSQL enum types)
- `SET` columns as checkboxes
- BLOB / binary columns: shown with their size, downloaded as a plain file (no extension), and replaced through a file upload field
- Long text shown in textareas; long cell values truncated in the grid with a tooltip
- "Save and add another" when inserting

### Structure
- Columns, indexes and the full `CREATE` statement
- Add, edit and drop columns (rename, type, default, nullable; MySQL/MariaDB also auto-increment, comment, collation and position)
- Add, edit and drop indexes (including unique indexes)
- Set or remove the primary key
- Add, edit and drop foreign keys, with referenced table, columns and ON UPDATE / ON DELETE actions
- Add, edit and drop check constraints
- SQLite changes that its `ALTER TABLE` can't do are performed by safely rebuilding the table in a transaction, keeping data, indexes and triggers
- Defaults such as `now()` are written as expressions

### SQL console
- Run one or many statements, with results and timings
- Correct handling of quotes, comments and PostgreSQL dollar quoting
- Result row limit and per-session query history
- Ctrl/Cmd + Enter to execute

### Import
- SQL files or pasted SQL, run in a single transaction by default (all or nothing)
- Optional "continue on errors" mode without a transaction
- MySQL `DELIMITER` blocks (triggers, procedures, functions) are supported
- CSV import into an existing table or a new table created from the header: choose the delimiter, header mapping, and empty-as-NULL; always transactional

### Export
- SQL dump with structure and/or data, optional `DROP` statements, multiple tables at once
- Binary-safe data export (hex literals)
- PostgreSQL dumps include check constraints and foreign keys
- CSV export of a table (UTF-8 with BOM, works in Excel)

### Interface and security
- Single file, no dependencies, no build step
- Light and dark theme (follows the system, with a manual toggle)
- Responsive layout for small screens
- CSRF protection on every action, escaped output, quoted identifiers
- Strict Content-Security-Policy and other security headers
- Session cookie flags (HttpOnly, SameSite, Secure over HTTPS) and session ID regeneration on login
- Delayed responses on failed logins

## Installation

1. Copy `litedb.php` to your web server.
2. Optionally edit the `$LITEDB_CONFIG` block at the top of the file.
3. Open it in the browser and log in.

Serve it over HTTPS, set `access_password` and/or `allowed_ips`, and remove the file when you are not using it.
