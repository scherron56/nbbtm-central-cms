# Copilot instructions for nbbtm-central

## Project overview

`nbbtm-central` is a server-rendered PHP application for New Beginnings Baptist
Tabernacle Ministries. It uses MySQL/MariaDB through MySQLi and Composer
packages for SMTP email and JasperReports:

- `phpmailer/phpmailer` sends application email through the Brevo SMTP settings
  loaded by `include/send_email.php`.
- `geekcom/phpjasper` invokes JasperStarter to render compiled `.jasper` report
  files into PDFs.
- `vlucas/phpdotenv` loads SMTP settings from the root `.env` file.

The application is primarily a collection of PHP pages and JSON/action
endpoints, with page-specific JavaScript and shared PHP includes. There is no
framework or application-wide router. `include/header.php` and
`include/footer.php` provide the common shell; `include/auth.php` starts the
session, loads the database connection, defines role/permission helpers, and
implements the login action.

## Build, run, and validation commands

Install the PHP dependencies after cloning or when `composer.lock` changes:

```sh
composer install
```

For local development, use the configured VS Code PHP server on port `3001`,
or run PHP's built-in server from the repository root:

```sh
php -S localhost:3001
```

The application expects a reachable MySQL database named `nbbtm_central` and
the schema/procedures in `config/mysql/` (plus any applicable migration files
in `config/migrations/`). Configure local database and SMTP values before
testing authenticated or email/reporting workflows. Do not commit real
credentials or API keys.

There is no first-party automated test suite or lint script in this repository.
Use PHP's syntax checker as the targeted validation for a changed PHP file:

```sh
php -l path/to/changed-file.php
```

To syntax-check all first-party PHP files while excluding vendored code and
history snapshots:

```sh
find . -path './vendor' -prune -o -path './.history' -prune -o -name '*.php' -print0 |
  xargs -0 -n1 php -l
```

For a browser-facing change, manually exercise the relevant page and its
JSON/action endpoints with a logged-in account whose role matches the
workflow. Report generation also requires Java/JasperStarter and a working
MySQL connection; it is not covered by a local unit-test command.

## Architecture and request flow

- **Authentication and authorization:** Most protected pages begin with
  `require_once __DIR__ . '/include/auth.php'` (or an equivalent relative
  include), then call `requireAdmin()`, `requireEditor()`, `requireRole()`,
  `requireMemberDocuments()`, or `requireDocumentAccess()` as appropriate.
  Roles are stored in the session as `admin`, `developer`, `staff`, `member`,
  or `browser`. Developers are treated as admins by `isAdmin()`, while
  `requireDeveloperOnly()` remains developer-only.
- **Database boundary:** `config/db.php` creates the shared `$db` MySQLi
  connection with strict MySQLi error reporting and `utf8mb4`. Reuse that
  connection rather than opening a second one. Existing code mixes direct
  read queries with prepared statements; use prepared statements and
  `bind_param()` for request-controlled values, especially in new writes and
  filters.
- **Page/API split:** PHP pages render HTML and commonly embed or load
  JavaScript. Action endpoints such as `user_api.php`, `ministry_api.php`,
  `prg_event_api.php`, `vbs_api.php`, and `class_controller.php` return JSON
  and dispatch behavior using an `action` request parameter. Keep JSON
  responses free of notices/HTML; endpoints generally set a JSON content type,
  use HTTP error statuses, and terminate after emitting a response.
- **Feature areas:** Contacts and member records are the central data model.
  Ministries/committees, programs/events and registration/check-in, VBS
  sessions/classes/attendance, member documents, user management, and email
  mailing are separate page/API workflows connected through the shared
  contacts and user tables.
- **Documents:** Files are stored in `document_lib` and served by the
  `include/document_reader.php`, `include/file_loader.php`, and
  `include/download_document.php` handlers. These handlers enforce document
  access before reading binary data and should preserve the correct MIME type,
  download/inline disposition, and no-cache headers.
- **Reports:** Report definitions and parameters are stored in MySQL; report
  templates/assets live under `reports/` and generated output is written under
  `reports/output/`. `report_controller.php` uses PHPJasper for PDF export and
  streams then removes temporary PDFs. Preserve the allowed-report and
  parameter validation when changing report paths or controls.
- **Shared UI:** `include/header.php` derives the active page from
  `PHP_SELF` and renders navigation based on the session role. Reuse the
  existing classes and `css/style.css` rather than introducing a parallel
  layout. Front-end behavior is mostly page-specific JavaScript plus jQuery
  loaded by pages that need it.

## Repository-specific conventions

- Resolve includes from the file location with `__DIR__` where possible; many
  endpoints are called directly and must work regardless of the current
  working directory.
- Start sessions before reading or writing session state, and preserve the
  cookie settings used by `include/auth.php` (`httponly`, `samesite=Lax`,
  root path).
- Keep authorization at both the page and endpoint boundaries. Navigation
  visibility in `include/header.php` is not a security check; action endpoints
  must enforce their own role requirement.
- Treat request values as strings until validated/coerced for their intended
  type. Existing endpoints commonly normalize IDs with `intval()` and use
  prepared statements for mutations.
- Escape values rendered into HTML with `htmlspecialchars()` and preserve
  JSON response headers/status codes. Do not allow PHP warnings or debug HTML
  to precede JSON or PDF output.
- When calling MySQL stored procedures, consume/flush all additional MySQLi
  result sets before issuing another query on the same connection.
- Keep report parameter names synchronized across the database metadata,
  `js/`/inline control-rendering code, `.jrxml`/`.jasper` templates, and the
  PHPJasper options passed to the controller.
- Email is centralized through `sendEmail()` in `include/send_email.php`.
  Reuse it instead of configuring PHPMailer independently, and load SMTP
  configuration from environment variables rather than hardcoding secrets.
- Treat `vendor/` and `.history/` as non-application code. Do not edit
  vendored packages or use history snapshots as implementation sources unless
  the task explicitly concerns them.
- SQL files under `config/mysql/` include schema/procedure/report queries and
  `config/migrations/` contains incremental changes. Update the relevant SQL
  artifact when a schema or stored-procedure contract changes.
