# Agent Instructions for Mar-vel OMS

These rules apply to all agents working in this workspace. Read this file, `Mar-vel OMS Ph1 Architecture.md`, and `Mar-vel-OMS plan.md` before implementing application behavior. The architecture document owns product decisions; the implementation plan owns stage order and acceptance checks.

## Project Boundaries

- Phase 1 is a LAN-only Laravel modular monolith using Vue 3, Inertia, MySQL, Reverb, and Echo.
- Do not add a separate REST API, microservices, cloud runtime services, digital approvals, PR/PO fields, receipt uploads, or a managed expense-category catalog in Phase 1.
- Keep voucher business rules in Laravel. Vue pages must not be the authority for permissions, totals, numbering, or revisions.
- Use the existing architecture and implementation plan as constraints. If implementation exposes a material conflict or requires a new product decision, explain the impact and request direction instead of silently changing scope.

## Naming and File Conventions

- PHP classes use PSR-4 namespaces, a filename matching the class, and `PascalCase` class names such as `StoreVoucherRequest` or `VoucherPolicy`.
- PHP methods and local variables use `camelCase`. Keep Laravel configuration filenames in the framework's established `snake_case.php` style.
- Database tables use plural `snake_case`; columns use `snake_case`; foreign keys use `<singular_entity>_id`, such as `voucher_id` and `project_id`.
- Migration filenames use Laravel's timestamped migration convention and describe the operation and table.
- Vue single-file components use `PascalCase.vue`. Organize pages by domain under `resources/js/pages/`; shared components go under `resources/js/components/`.
- Vue composables use the `use` prefix and a matching name such as `useVoucherTotals.ts`. TypeScript types and interfaces use `PascalCase`; values and functions use `camelCase`.
- Laravel route names use lowercase dot-separated names such as `vouchers.index`; use clear resource-oriented route paths and names.
- Tests use `*Test.php` and live under the matching `tests/Feature` or `tests/Unit` area. Keep naming consistent with neighboring files.
- Follow the repository's existing conventions when they are more specific than these defaults. Do not rename existing user files solely to normalize their names.

## Data Integrity and Voucher Rules

- Store currency as decimal values with two fractional digits. Do not use binary floating-point for money.
- Recompute totals on the server from item rows. An expense adds to the total; a deduction is entered as a positive amount and subtracted.
- Generate voucher numbers transactionally from the system entry year. Enforce a unique database constraint; backdated voucher dates do not affect the sequence; cancelled numbers are never reused.
- Preserve `voucher_date`, system timestamps, `created_by`, and editable `prepared_by` as separate values. Never replace creator identity with the Prepared By display name.
- Require a reason for post-print edits and cancellations. Keep prior revision snapshots, generated PDFs, and audit events; never hard-delete vouchers, revisions, or audit records.
- Detect stale voucher edits with a record version or equivalent concurrency check. Do not use silent last-write-wins behavior or a global lock for independent voucher creation.
- Keep project, scope, and expense category separate in storage. Project merges/renames must not rewrite names preserved on historical vouchers.
- Treat the database as the source of truth. WebSocket messages notify clients to refresh; they are not the only copy of a change.

## RBAC, Audit, and Security

- Enforce authorization in Laravel policies or gates for every protected read, write, export, cancellation, admin action, and private WebSocket channel.
- Hiding a button in Vue does not grant or enforce permission. Do not trust client-supplied user IDs, creator IDs, totals, voucher numbers, permissions, or status transitions.
- Use individual app-managed accounts. No public self-registration, shared employee accounts, or hard-coded production credentials.
- Audit actor, action, timestamp, and relevant before/after values for voucher, project, permission, and account changes. Never include passwords, session tokens, or secrets in logs.
- Keep generated PDFs in protected application storage behind authorization. Phase 1 does not store uploaded receipt or PR/PO files.
- Keep database credentials server-side and out of JavaScript, browser responses, logs, and tracked files. Never expose the MySQL port to employee workstations.
- Escape user content in HTML/PDF output and guard spreadsheet exports against formula injection.
- Keep production debug output disabled. Restrict network access to the office LAN and use HTTPS with a trusted internal certificate where available.

## Inertia, Realtime, Print, and Export

- Use Inertia routes and responses for Phase 1 UI requests. Do not create API routes unless the architecture is deliberately revised.
- Broadcast only after the database transaction commits. Use authenticated private channels and minimal event payloads; clients fetch current authorized data through Inertia.
- Treat Reverb as a notification channel, not a database. Reconnect by refreshing relevant data and handle unavailable sockets without losing saved work.
- Maintain one print layout contract for PDF preview/download and printing. Preserve readable type, two-up half-Letter layout, cut guide, continuation behavior, and signature placement from the sample.
- Keep form-template revision separate from voucher revision. Store each generated unsigned PDF with its corresponding immutable voucher revision.
- Export one row per particular. Preserve numeric amount values and an explicit line type; do not parse combined scope/category text because those fields are stored separately.

## Database and Change Management

- Use migrations for schema changes; do not modify a production database manually or rewrite an already-applied migration.
- Make migrations safe for existing voucher history. For destructive or data-transforming changes, provide a backup/rollback or forward-recovery plan before applying them.
- Use transactions for number allocation, voucher persistence, revision snapshots, and audit writes that must succeed together.
- Keep dependencies few. Before adding a package, confirm it supports the selected Laravel/PHP versions, works without a cloud service, and removes more complexity than it adds.
- Do not put business logic in migrations, Vue components, route closures, or broad model hooks. Keep controllers thin; use validated requests, policies, and focused actions for multi-step workflows.
- Keep the database queue, Reverb, and web application compatible with the shared Windows host and its restart/backup procedures.

## Verification and Handoffs

- Use the stage-specific acceptance checks in `Mar-vel-OMS plan.md` as the definition of done for each feature area.
- Add or update focused tests for changed business behavior when implementation work includes testing. Do not claim a test, build, print check, or restore check passed unless it was run and its output reviewed.
- For print changes, verify rendered PDF pages at actual size, including two-up, overflow, footer, and signature cases.
- For export changes, inspect the generated workbook and verify values, types, filters, and formula-injection handling.
- Report the files changed, checks run and their results, any checks not run, and any deviation from the architecture or plan.
- Update the implementation plan or architecture document when an approved decision changes. Do not silently leave stale guidance.

## Laravel Repository Setup

This repository contains a Laravel application. Complete the following setup before working on the user's request.

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, install the prerequisites with the platform-specific PHP 8.5 installer:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, restart the terminal. If the agent needs the restarted shell to continue, reopen the terminal and rerun the original prompt.

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
