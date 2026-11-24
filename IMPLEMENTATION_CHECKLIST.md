# Mar-vel OMS Implementation Checklist

**Last updated:** 2026-11-24

This checklist compares the approved Phase 1 implementation plan with the code that is currently present.

## Status legend

- [x] Implemented and available
- [~] Partially implemented
- [ ] Not implemented
- [!] Not verified or requires deployment/operational setup

## Current release position

**Phase:** Phase 1

**Active implementation:** Stage 1, Accounts, Authentication, and RBAC (implemented; verification follow-up remains)

**Next stage:** Stage 2, Projects and Form Settings

The application is currently a runnable Laravel/Inertia foundation with authentication, account administration, role-level permission management, audit-event persistence, and forced temporary-password changes. Voucher entry and the remaining approved Phase 1 modules are not implemented yet.

## Stage 0 — Repository and Local Development Foundation

- [x] Laravel 13 application structure is present
- [x] PHP 8.3.29 is available and the Composer lock resolves for the declared PHP `^8.3` requirement
- [x] Vue 3, TypeScript, Vite, and Inertia are installed
- [x] Laravel Breeze authentication scaffolding is present
- [x] Laravel Boost guidance tooling is installed
- [x] Composer dependencies were re-resolved to PHP 8.3-compatible Symfony 7.4 packages
- [x] MySQL is configured for `mar_vel_management_system`
- [x] Database migrations are applied to the MySQL database
- [x] Database-backed cache, session, and queue configuration is available
- [x] Architecture and implementation plan documents are preserved
- [x] Individual account credentials are kept in the local environment
- [ ] Reverb and Echo setup is not installed or configured
- [ ] LAN binding and Windows host setup is not complete
- [!] Fresh setup and frontend build checks need to be repeated on another machine

**Stage 0 status: Partially complete**

## Stage 1 — Accounts, Authentication, and RBAC

- [x] Login and logout routes are available
- [x] Password reset and profile management routes are available
- [x] Public registration route is absent
- [x] Individual account login is supported
- [x] Active/disabled account state is stored on users
- [x] Disabled accounts cannot authenticate
- [x] Super Admin and Employee roles exist
- [x] Permission records exist
- [x] Super Admin and Employee role assignments are stored
- [x] Employee defaults include voucher view, create, and edit permissions
- [x] Account administration is protected by `accounts.manage`
- [x] Super Admin can view account records
- [x] Super Admin can create employee or Super Admin accounts
- [x] Super Admin can disable or restore accounts
- [x] Super Admin cannot disable their own account through the status action
- [x] `oms:bootstrap-admin` creates or promotes a Super Admin
- [x] Passwords are hashed with Laravel hashing
- [x] Login attempts are rate limited
- [x] Modern login, dashboard, and account administration UI is present
- [x] Super Admin role-level permission-management UI is implemented
- [x] Super Admin can assign permissions to roles, with protection against removing permission administration from the Super Admin role
- [~] Custom permission assignment per account is not exposed; permissions are currently assigned at the role level
- [x] New accounts receive a temporary password and are required to change it before continuing
- [~] Account, role-permission, password-change, and password-reset actions write audit events; profile changes and future module actions still need coverage
- [~] Current account and permission routes use server-side permission middleware; policy/gate coverage for future modules remains to be established
- [!] Role and permission acceptance tests exist but could not run locally because `pdo_sqlite` is unavailable

**Stage 1 status: Core implementation complete; test-environment and audit/policy coverage follow-up remains**

## Stage 2 — Projects and Form Settings

- [ ] Project master table
- [ ] Project name autocomplete
- [ ] Whitespace/case duplicate detection
- [ ] Shared project auto-add for typed names
- [ ] Super Admin project rename
- [ ] Super Admin project merge
- [ ] Historical project-name snapshots
- [ ] Scope of Work free-text storage
- [ ] Expense Category free-text storage
- [ ] Printable company name setting
- [ ] Printable form number setting
- [ ] Printable effective date setting
- [ ] Printable template revision setting
- [ ] Printable procedural notices setting
- [ ] Project and settings audit events

**Stage 2 status: Not implemented — next implementation stage**

## Stage 3 — Voucher Data and Business Rules

- [ ] Voucher model and migration
- [ ] Voucher item model and migration
- [ ] Voucher number sequence table
- [ ] Transactional `DV-YYYY-####` numbering
- [ ] Unique database voucher-number constraint
- [ ] Entry-year numbering independent of backdated voucher dates
- [ ] Backdated voucher date support
- [ ] Voucher date/system timestamp separation
- [ ] Payee field
- [ ] Project reference and project-name snapshot
- [ ] Separate Scope of Work and Expense Category fields
- [ ] Full/Partial payment choice
- [ ] Creator account storage
- [ ] Editable Prepared By display text
- [ ] Expense and deduction line types
- [ ] Positive deduction amounts converted to server-side subtraction
- [ ] Server-calculated decimal totals
- [ ] Required-field validation
- [ ] Voucher create/edit UI
- [ ] Voucher list and filter UI

**Stage 3 status: Not implemented**

## Stage 4 — Audit, Revisions, Cancellation, and Edit Conflicts

- [x] Audit-event table is present and currently used append-only for account, role-permission, and password actions
- [ ] Voucher create/edit/cancel/print audit events
- [~] Audit events store actor, timestamp, and metadata with before/after values for covered status and permission changes; voucher-level coverage remains
- [ ] Monotonic voucher record version
- [ ] Stale-edit detection
- [ ] Reload prompt for concurrent edits
- [ ] Draft lifecycle state
- [ ] Printed lifecycle state
- [ ] Reprint-required lifecycle state
- [ ] Cancelled lifecycle state
- [ ] Post-print edit reason
- [ ] Post-print revision snapshot
- [ ] Superseded prior print state
- [ ] Private immutable unsigned PDFs
- [ ] Cancellation reason
- [ ] Permanent cancelled voucher numbering behavior
- [ ] No hard deletion for vouchers or history

**Stage 4 status: Audit foundation partially implemented; voucher history and concurrency controls are not implemented**

## Stage 5 — Voucher Screens and Real-Time Updates

- [ ] Voucher list page
- [ ] Voucher search/filter page
- [ ] Voucher create page
- [ ] Voucher edit page
- [ ] Voucher activity-history page
- [ ] Project autocomplete UI
- [ ] Server-authorized voucher actions
- [ ] Reverb setup
- [ ] Echo setup
- [ ] Authenticated private change channels
- [ ] Transaction-after-commit broadcasts
- [ ] Inertia refresh on voucher/project changes
- [ ] Realtime connection state UI
- [ ] WebSocket reconnect refresh behavior
- [ ] Supervised queue and Reverb deployment configuration

**Stage 5 status: Not implemented**

## Stage 6 — Print Layout and PDF

- [ ] Letter-sized voucher print template
- [ ] Paid To field
- [ ] Project/cost center display
- [ ] Scope of Work display
- [ ] Expense Category display
- [ ] Particulars and amount layout
- [ ] Expense/deduction rendering
- [ ] Server-calculated print total
- [ ] Signature blocks
- [ ] Form number and effective date footer
- [ ] Template revision and procedural notices
- [ ] Dompdf integration
- [ ] Private generated PDF storage
- [ ] PDF preview/download action
- [ ] Print action using the same generated output
- [ ] Two-up half-Letter voucher layout
- [ ] Cut guide
- [ ] Single and full-sheet behavior
- [ ] Long-voucher continuation sheets
- [ ] Repeated voucher number on continuation sheets
- [ ] Final-page total/signature protection

**Stage 6 status: Not implemented**

## Stage 7 — Search and Excel Export

- [ ] Voucher number filter
- [ ] Voucher date filter
- [ ] Project filter
- [ ] Payee filter
- [ ] Category filter
- [ ] Sensible date-range defaults
- [ ] `.xlsx` export
- [ ] One row per particular
- [ ] Repeated voucher metadata per export row
- [ ] Numeric export amounts
- [ ] Explicit expense/deduction line type
- [ ] Signed/net amount for spreadsheet calculations
- [ ] Formula-injection protection
- [ ] Export authorization enforcement

**Stage 7 status: Not implemented**

## Stage 8 — LAN Deployment, Backups, and Pilot

- [ ] Stable office-LAN hostname or reserved address
- [ ] LAN-only firewall configuration
- [ ] MySQL bound only to the host
- [ ] Employee PCs cannot connect directly to MySQL
- [ ] Internal HTTPS certificate configuration
- [ ] Production debug disabled
- [ ] Web server serving only Laravel's `public` directory
- [ ] Windows automatic startup for MySQL
- [ ] Windows automatic startup for the web server
- [ ] Windows automatic startup for Reverb
- [ ] Windows automatic startup for the queue worker
- [ ] Shared PC sleep policy documented
- [ ] Separate backup destination selected
- [ ] Daily database backup schedule
- [ ] Daily/monthly retention policy
- [ ] Offline or separate backup copy
- [ ] Documented backup/restore procedure
- [ ] Restoration into a test database verified
- [ ] Employee pilot completed
- [ ] Printed form compared with the sample
- [ ] Excel export pilot completed
- [ ] Role permissions exercised in the pilot
- [ ] Paper process retained during rollout

**Stage 8 status: Not implemented**

## Verification performed

- [x] Laravel migrations and seed ran against `mar_vel_management_system`
- [x] `npm run build` completed successfully
- [x] PHP Pint formatted changed PHP files
- [x] Impeccable UI detector returned no findings
- [x] Login page returned HTTP 200 during runtime smoke testing
- [!] `php artisan test --compact` ran but did not pass: 3 tests passed and 31 tests errored because the PHP SQLite driver (`pdo_sqlite`) is unavailable
- [!] Browser visual review at actual office sizes has not been completed
- [!] Print layout verification is not applicable before the print module exists
- [!] Backup/restore verification is not applicable before a production backup destination exists

## Recommended next implementation

Complete the remaining Stage 1 verification and then begin Stage 2:

1. Install the PHP SQLite extension or configure the test suite to use MySQL
2. Rerun the account, permission, and authentication acceptance tests
3. Extend audit coverage to remaining account/profile actions and document the event contract
4. Establish policy/gate coverage for the future voucher, project, print, and export modules
5. Complete the account lifecycle acceptance checks
6. Begin project master and form-setting work from Stage 2
