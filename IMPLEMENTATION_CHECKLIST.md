 # Mar-vel OMS Implementation Checklist

**Last updated:** 2026-11-26

This checklist compares the approved Phase 1 implementation plan with the code that is currently present.

## Status legend

- [x] Implemented and available
- [~] Partially implemented
- [ ] Not implemented
- [!] Not verified or requires deployment/operational setup

## Current release position

**Phase:** Phase 1

**Active implementation:** Stage 5, Voucher Screens and Real-Time Updates — code implemented; live LAN acceptance remains

**Next implementation:** Verify two-session realtime behavior and Windows service supervision, then begin Stage 6

The application has a runnable Laravel/Inertia foundation, authentication, account administration, role-level permission management, project management, configurable print-template settings, voucher data/business-rule backends, Stage 4 lifecycle controls, core Stage 5 voucher screens, and code-level Reverb/Echo realtime updates. Two-session LAN verification and Windows process supervision remain, along with PDF generation/storage, export, and deployment stages.

## Stage 0 — Repository and Local Development Foundation

- [x] Laravel 13 application structure is present
- [x] PHP 8.3.29 is available and the Composer lock resolves for the declared PHP `^8.3` requirement
- [x] Vue 3, TypeScript, Vite, and Inertia are installed
- [x] Laravel Breeze authentication scaffolding is present
- [x] Laravel Boost guidance tooling is installed
- [x] Composer dependencies were re-resolved to PHP 8.3-compatible Symfony 7.4 packages
- [x] MySQL is configured for `mar_vel_management_system`
- [x] Initial application migrations are applied to the MySQL database
- [x] Database-backed cache, session, and queue configuration is available
- [x] Architecture and implementation plan documents are preserved
- [x] Individual account credentials are kept in the local environment
- [x] Reverb and Echo packages and application configuration are installed
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
- [!] Full PHPUnit suite ran with SQLite enabled: 113 tests passed and one Stage 1 login-redirect assertion failed

**Stage 1 status: Core implementation complete; the full suite still has one unrelated login-redirect assertion failure**

## Stage 2 — Projects and Form Settings

- [x] Project master table with normalized-name uniqueness
- [x] Searchable project directory and voucher-entry project autocomplete are available
- [x] Whitespace/case duplicate detection
- [x] Projects can be added from the directory or directly from voucher entry
- [x] Super Admin project rename
- [x] Super Admin project merge without deleting the source record
- [x] Historical project-name snapshots (implemented with the Stage 3 voucher schema)
- [x] Scope of Work free-text storage (implemented with the Stage 3 voucher header)
- [x] Expense Category free-text storage (implemented with the Stage 3 voucher header)
- [x] Printable company name setting
- [x] Printable form number setting
- [x] Printable effective date setting
- [x] Printable template revision setting
- [x] Printable procedural notices setting
- [x] Project and settings audit events

**Stage 2 status: Project administration, print settings, voucher-entry autocomplete, and typed-project creation are implemented.**

## Stage 3 — Voucher Data and Business Rules

- [x] Voucher model and migration
- [x] Voucher item model and migration
- [x] Voucher number sequence table
- [x] Transactional `DV-YYYY-####` numbering
- [x] Unique database voucher-number constraint
- [x] Entry-year numbering independent of backdated voucher dates
- [x] Backdated voucher date support
- [x] Voucher date/system timestamp separation
- [x] Payee field
- [x] Project reference and project-name snapshot
- [x] Separate Scope of Work and Expense Category fields
- [x] Full/Partial payment choice
- [x] Creator account storage
- [x] Editable Prepared By display text
- [x] Expense and deduction line types
- [x] Positive deduction amounts converted to server-side subtraction
- [x] Server-calculated decimal totals
- [x] Required-field validation
- [x] Voucher create/edit UI
- [x] Voucher list and filter UI

**Stage 3 status: Voucher backend, business rules, create/edit screens, and list/filter screens are implemented. The plan lists UI work in both Stages 3 and 5; the screens are delivered with Stage 5. MySQL concurrency for first-time sequence creation has not been exercised.**

## Stage 4 — Audit, Revisions, Cancellation, and Edit Conflicts

- [x] Append-only audit events cover voucher creation, edits, printing, and cancellation
- [x] Audit events record actor, timestamp, action, and relevant before/after metadata; cancellation and post-print edit reasons are retained
- [x] Monotonic voucher record version
- [x] Stale-edit and stale-cancellation rejection
- [x] Stale edits return a conflict and the editor offers a reload path with current server data
- [x] Draft lifecycle state
- [x] Printed lifecycle state
- [x] Reprint-required lifecycle state
- [x] Cancelled lifecycle state
- [x] Post-print edit reason
- [x] Post-print revision snapshots preserve prior printed revisions
- [x] A prior print is marked superseded only when the pending revision is printed
- [ ] Private immutable unsigned PDFs (generation/storage is planned for Stage 6)
- [x] Cancellation reason
- [x] Cancellation preserves voucher numbering; subsequent vouchers receive the next sequence number
- [x] Cancellation retains voucher, item, revision, and audit history without hard deletion

**Stage 4 status: Backend lifecycle, revision snapshots, cancellation, audit, stale-write rejection, and the editor conflict/reload path are implemented and verified. PDF generation and private storage remain with Stage 6.**

## Stage 5 — Voucher Screens and Real-Time Updates

- [x] Voucher list page
- [x] Voucher search/filter page
- [x] Voucher create page
- [x] Voucher edit page
- [x] Voucher activity-history page
- [x] Project search/autocomplete and typed-project creation from voucher entry
- [x] Server-authorized voucher actions
- [x] Reverb setup and environment-backed allowed-origin configuration
- [x] Single Echo client setup
- [x] Authenticated private voucher and project change channels
- [x] Transaction-after-commit voucher and project broadcasts
- [x] Inertia refresh on voucher/project changes
- [x] Realtime connection state UI
- [x] WebSocket reconnect refresh behavior
- [ ] Two-session LAN verification, including unsaved editor preservation and reconnect recovery
- [ ] Windows Task Scheduler startup and failure-recovery verification for Reverb and the database queue worker

**Stage 5 status: Core screens and code-level realtime behavior are implemented and covered by focused tests/build. Live two-session LAN behavior and Windows process supervision are not yet verified.**

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
- [x] Stage 2 project feature tests passed (15 tests)
- [x] Stage 2 print-settings feature tests passed (4 tests)
- [x] Voucher business-rule tests passed (16 tests)
- [x] Voucher lifecycle tests passed (17 tests)
- [x] Voucher page feature tests passed (11 tests)
- [x] Broadcasting feature tests passed (17 tests)
- [!] Full PHPUnit suite: 113 passed, 1 failed; `StageOneAccessTest::test_new_accounts_are_required_to_change_their_temporary_password` expected `/profile` but received `/dashboard`
- [x] `php artisan migrate --pretend --no-interaction` generated the pending MySQL voucher schema SQL; `migrate:status` confirmed the migrations remain pending and were not applied
- [x] `npm run build` completed successfully after the latest frontend change
- [x] `vendor/bin/pint --dirty --format agent` completed successfully
- [x] `git diff --check` reported no whitespace errors
- [!] Authenticated browser visual review was not completed; the local voucher URL redirected to sign-in
- [!] MySQL concurrent first-number allocation was not exercised; SQLite tests cover sequential voucher creation only
- [x] Impeccable UI detector returned no findings
- [x] Login page returned HTTP 200 during runtime smoke testing
- [!] Browser visual review at actual office sizes has not been completed
- [!] Print layout verification is not applicable before the print module exists
- [!] Backup/restore verification is not applicable before a production backup destination exists

## Recommended next implementation

1. Complete live two-session LAN acceptance and Windows Reverb/queue-worker startup and recovery checks for Stage 5
2. Implement Stage 6 PDF generation/storage and verify print layout at actual size
3. Investigate the existing Stage 1 login-redirect test failure separately
