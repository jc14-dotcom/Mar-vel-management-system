# Voucher Lifecycle and Concurrency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add version-checked voucher edits, printed-revision history, print-state recording, and reasoned cancellation to the existing voucher backend.

**Architecture:** Keep voucher rules in focused Laravel actions, validate requests at the HTTP boundary, and persist voucher changes, revisions, and audit events in the same transaction. Use `record_version` for stale-write detection and retain immutable printed snapshots in `voucher_revisions`; later Stage 5/6 work will connect these actions to the UI and generated PDFs.

**Tech Stack:** Laravel 13, PHP 8.3+, Eloquent, MySQL, Inertia web routes, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-26-voucher-lifecycle-design.md`; `Mar-vel-OMS plan.md` (Stage 4); `Mar-vel OMS Ph1 Architecture.md` (voucher workflow, data integrity, RBAC, and audit requirements).

## Global Constraints

- Build a LAN-only Laravel modular monolith on the required shared Windows PC. Use Laravel 13, Vue 3 with Inertia, MySQL, Laravel Reverb, and Echo. Do not introduce microservices.
- Inertia and Laravel web routes handle application requests. Do not add a separate REST API in Phase 1.
- Keep the voucher’s business date separate from immutable system timestamps and audit events.
- Assign `DV-YYYY-####` when the voucher is first saved, based on the system entry year. Backdating the voucher date must not change its number. Never reuse a cancelled number.
- Employees can create vouchers in parallel; if someone else saves the same draft first, warn the current editor and reload the newer version.
- A post-print edit requires a reason, creates a new revision for fresh signatures, and marks the previous print as superseded.
- Retain cancelled vouchers and their numbers; never reuse numbers.
- Enforce permissions on the server, use secure password hashing and session protections, and keep an append-only activity history visible to authorized voucher users.
- Do not hard-delete vouchers, revisions, or audit events. Never recycle cancelled voucher numbers.
- Use migrations for schema changes. Do not edit migrations already applied to a shared database or apply these migrations to the configured MySQL database during implementation verification.
- Use existing dependencies and project conventions. Do not add packages.
- The worktree contains unrelated uncommitted changes. Stage only task-owned paths/hunks; never stage an entire directory or unrelated changes.
- Preserve the Stage 1/2 audit behavior for voucher creation, project maintenance, and permission changes; this stage extends the voucher lifecycle audit trail for edits, printing, and cancellation.
- This plan covers Stage 4 backend lifecycle behavior only. Voucher pages/project autocomplete remain Stage 5; PDF generation and storage remain Stage 6.

## Review Focus

- A stale edit or cancellation must return conflict without changing the voucher, items, revisions, or audit history. Test `record_version` replay after a newer write.
- A printed voucher edit with a missing or blank reason must fail validation without changing saved data. Test both missing and whitespace-only reasons.
- An edit to a printed voucher must create a new pending revision snapshot, retain the prior printed revision as current until the new revision is printed, and require a fresh print. Test both lifecycle transitions.
- A user without `vouchers.cancel` must not cancel a voucher, even with a valid reason and version. Test the denied route and unchanged database state.
- Cancelling a voucher must preserve its number and history, and the next voucher must receive the next sequence number. Test cancellation followed by a new voucher.

---

### Task 1: Add Record Versions and Reject Stale Draft Edits

**Files:**
- Create: `database/migrations/<timestamp>_add_record_version_to_vouchers_table.php` using `php artisan make:migration add_record_version_to_vouchers_table --no-interaction`
- Modify: `app/Models/Voucher.php`
- Modify: `app/Http/Requests/SaveVoucherRequest.php`
- Modify: `app/Actions/Vouchers/UpdateVoucherAction.php`
- Modify: `tests/Feature/VoucherBusinessRulesTest.php`

**Interfaces:**
- Consumes: Existing `UpdateVoucherAction::handle(User $actor, Voucher $voucher, array $data): Voucher`.
- Produces: An unsigned `record_version` on every voucher, starting at `1`. Update requests must submit the version they loaded. A stale version returns HTTP `409` without modifying records or writing audit events.

- [ ] **Step 1: Write the failing tests**

Add `test_successful_draft_edit_increments_record_version` and `test_stale_draft_edit_returns_conflict_without_mutating_voucher_or_audit`. Submit `record_version: 1`; assert the successful edit stores version `2`, and a later request still using version `1` receives `409` with voucher fields, items, and audit count unchanged. Update the existing draft-edit test to submit the required version.

- [ ] **Step 2: Run the tests and confirm the expected failure**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherBusinessRulesTest.php`

Expected: The version assertions fail because the schema and update action do not yet track versions.

- [ ] **Step 3: Add the version column and locked comparison**

Add `record_version` as an unsigned integer with default `1`. Validate it as a required positive integer on update. In `UpdateVoucherAction`, compare the submitted value with the locked voucher row before any changes; return `409` on mismatch and increment the version in the same transaction after a successful update.

- [ ] **Step 4: Run the focused tests**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherBusinessRulesTest.php`

Expected: All tests in the file pass.

- [ ] **Step 5: Commit this task**

```bash
git add -- 'database/migrations/*_add_record_version_to_vouchers_table.php' app/Models/Voucher.php app/Http/Requests/SaveVoucherRequest.php app/Actions/Vouchers/UpdateVoucherAction.php tests/Feature/VoucherBusinessRulesTest.php
git commit -m "feat: reject stale voucher edits" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

### Task 2: Persist Voucher Revisions and Record Print State

**Files:**
- Create: `database/migrations/<timestamp>_create_voucher_revisions_table.php` using `php artisan make:migration create_voucher_revisions_table --no-interaction`
- Create: `app/Models/VoucherRevision.php`
- Create: `database/factories/VoucherRevisionFactory.php`
- Create: `app/Actions/Vouchers/RecordVoucherPrintAction.php`
- Modify: `app/Models/Voucher.php`
- Create: `tests/Feature/VoucherLifecycleTest.php`

**Interfaces:**
- Consumes: `Voucher`, its ordered `items` relation, `User`, and `AuditEvent`.
- Produces: `VoucherRevision` with `voucher_id`, `revision_number`, `snapshot`, `reason`, `actor_id`, `printed_at`, and `superseded_at`; `Voucher::revisions(): HasMany`; and `RecordVoucherPrintAction::handle(User $actor, Voucher $voucher): VoucherRevision`.
- Snapshot keys: `voucher_number`, `voucher_date`, `payee`, `project_id`, `project_name_snapshot`, `scope_of_work`, `expense_category`, `payment_type`, `created_by`, `prepared_by`, `total_amount`, and `items` (ordered rows of `description`, `amount`, `line_type`, and `sort_order`). Serialize money values as decimal strings with two fractional digits.

- [ ] **Step 1: Write failing print-state tests**

Add `test_recording_first_print_creates_revision_snapshot_and_audit_event` and `test_recording_reprint_marks_pending_revision_printed_and_supersedes_previous`, invoking `app(RecordVoucherPrintAction::class)->handle($actor, $voucher)` directly. For the reprint test, seed one printed revision and one pending revision with the factory. Assert the snapshot includes the specified header keys and ordered item rows; revision number is `1` for the first print and the next revision number is `2`; snapshot amount values are two-decimal strings; a pending revision keeps its number when marked printed; both printed revisions receive `printed_at`; the prior printed revision is marked superseded only when the new revision is printed; status becomes `printed`; `record_version` increments; and the actor/action are recorded with old/new status and revision number.

- [ ] **Step 2: Run the tests and confirm the expected failure**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php`

Expected: The tests fail because revision persistence and print-state recording do not exist.

- [ ] **Step 3: Add revision storage and print-state action**

Create `voucher_revisions` with a restrictive voucher foreign key, restrictive actor foreign key, unique `(voucher_id, revision_number)`, JSON snapshot, nullable reason, nullable `printed_at`, nullable `superseded_at`, and timestamps. Add the Eloquent model, relation, factory, and transactional action. For a draft, create revision `1` from the current voucher and set `printed_at`; for `reprint_required`, mark its latest pending revision printed and mark the previous printed revision superseded. Lock the voucher, update status/version, and write `voucher.printed` audit metadata in the same transaction. Reject cancelled or already-printed vouchers; Stage 6 will reuse the current PDF for an unchanged reprint rather than create a revision.

- [ ] **Step 4: Run the focused lifecycle tests**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php`

Expected: Both print-state tests pass.

- [ ] **Step 5: Commit this task**

```bash
git add -- 'database/migrations/*_create_voucher_revisions_table.php' app/Models/Voucher.php app/Models/VoucherRevision.php database/factories/VoucherRevisionFactory.php app/Actions/Vouchers/RecordVoucherPrintAction.php tests/Feature/VoucherLifecycleTest.php
git commit -m "feat: record voucher print revisions" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

### Task 3: Require Reasons for Post-Print Edits

**Files:**
- Modify: `app/Http/Requests/SaveVoucherRequest.php`
- Modify: `app/Actions/Vouchers/UpdateVoucherAction.php`
- Modify: `app/Models/VoucherRevision.php`
- Modify: `tests/Feature/VoucherLifecycleTest.php`

**Interfaces:**
- Consumes: `record_version` from Task 1 and `VoucherRevision`/`RecordVoucherPrintAction` from Task 2.
- Produces: Validated `reason` for edits when status is `printed` or `reprint_required`. An accepted post-print edit moves the voucher to `reprint_required` and keeps an updated pending snapshot.

- [ ] **Step 1: Write failing post-print edit tests**

Add `test_printed_voucher_edit_requires_non_blank_reason`, `test_printed_voucher_edit_creates_pending_revision_without_superseding_current_print`, and `test_second_edit_before_reprint_updates_pending_revision`. Assert a missing or whitespace-only reason returns validation errors with no writes. For a valid edit, assert version increments, status becomes `reprint_required`, the new revision contains updated fields/items and the reason, and the previous printed revision is retained and is not yet marked superseded. Assert a second edit updates the same pending revision number and snapshot. Task 2's reprint test verifies that the old print becomes superseded only after the pending revision is printed.

- [ ] **Step 2: Run the tests and confirm the expected failure**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php`

Expected: The tests fail because post-print edits are currently rejected and no revision is created.

- [ ] **Step 3: Implement post-print edit behavior**

Require `reason` for `printed` and `reprint_required` vouchers. Keep the locked `record_version` comparison from Task 1 before writing any status. Reject `cancelled` vouchers with `409`. For a `printed` voucher, create the next revision snapshot from the updated voucher/items, leave the prior printed revision unsuperseded, set `reprint_required`, and increment `record_version`. For another edit before reprinting, update the pending revision snapshot and its reason while keeping its revision number. Keep the existing draft-edit behavior and before/after audit event. The prior print becomes superseded only when the pending revision is printed.

- [ ] **Step 4: Run lifecycle and business-rule tests**

Run:

```bash
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherBusinessRulesTest.php
```

Expected: Both test files pass.

- [ ] **Step 5: Commit this task**

```bash
git add app/Http/Requests/SaveVoucherRequest.php app/Actions/Vouchers/UpdateVoucherAction.php app/Models/VoucherRevision.php tests/Feature/VoucherLifecycleTest.php
git commit -m "feat: version post-print voucher edits" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

### Task 4: Add Reasoned Voucher Cancellation

**Files:**
- Create: `app/Http/Requests/CancelVoucherRequest.php`
- Create: `app/Actions/Vouchers/CancelVoucherAction.php`
- Modify: `app/Http/Controllers/VoucherController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/VoucherLifecycleTest.php`

**Interfaces:**
- Produces: `CancelVoucherAction::handle(User $actor, Voucher $voucher, int $recordVersion, string $reason): Voucher`.
- Controller: `VoucherController::cancel(CancelVoucherRequest $request, Voucher $voucher, CancelVoucherAction $cancel): RedirectResponse`.
- Route: `POST /vouchers/{voucher}/cancel`, named `vouchers.cancel`, protected by `auth`, `password.changed`, and `permission:vouchers.cancel`.
- Request: requires integer `record_version` and non-blank `reason` (maximum 2,000 characters).
- Cancellation is allowed from `draft`, `printed`, and `reprint_required`; `cancelled` is terminal.

- [ ] **Step 1: Write failing cancellation tests**

Add `test_cancellation_requires_reason_and_matching_record_version`, `test_user_without_cancel_permission_cannot_cancel_voucher`, `test_cancellation_retains_history_and_does_not_reuse_number`, and `test_cancelled_voucher_cannot_be_edited_or_printed`. Assert invalid/stale requests do not change records; unauthorized requests return `403`; valid cancellation sets `cancelled`, increments the version, records reason and actor, keeps voucher/items/revisions, and the next voucher receives the next sequence number. Assert a cancelled voucher rejects later edit and print attempts with `409`.

- [ ] **Step 2: Run the tests and confirm the expected failure**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php`

Expected: The tests fail because the cancellation route and action do not exist.

- [ ] **Step 3: Implement cancellation**

Validate permission and request values. In a transaction, lock the voucher, compare `record_version`, reject already-cancelled vouchers with `409`, set status to `cancelled`, increment the version, and append `voucher.cancelled` with before/after metadata and the reason. Do not delete voucher, item, revision, or audit rows.

- [ ] **Step 4: Run the cancellation tests**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php`

Expected: All lifecycle tests pass.

- [ ] **Step 5: Commit this task**

```bash
git add app/Http/Requests/CancelVoucherRequest.php app/Actions/Vouchers/CancelVoucherAction.php app/Http/Controllers/VoucherController.php routes/web.php tests/Feature/VoucherLifecycleTest.php
git commit -m "feat: cancel vouchers with audit history" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

### Task 5: Verify Stage 4 and Update the Checklist

**Files:**
- Modify: `IMPLEMENTATION_CHECKLIST.md`
- Verify: `tests/Feature/VoucherLifecycleTest.php`, `tests/Feature/VoucherBusinessRulesTest.php`

**Interfaces:**
- Consumes: all Stage 4 behavior from Tasks 1–4.
- Produces: verified lifecycle backend; checklist distinguishes implemented lifecycle behavior from UI, PDF storage, and browser behavior still assigned to Stages 5–6.

- [ ] **Step 1: Run all voucher backend feature tests**

Run:

```bash
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherLifecycleTest.php
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/VoucherBusinessRulesTest.php
```

Expected: All tests in both files pass.

- [ ] **Step 2: Preview migrations and run formatting**

Run: `php artisan migrate --pretend --no-interaction`

Expected: SQL for pending migrations is printed; no migration is applied.

Run: `vendor/bin/pint --dirty --format agent`

Expected: Pint exits successfully and formats changed PHP files.

- [ ] **Step 3: Run the full suite and whitespace check**

Run: `php -d extension=pdo_sqlite vendor/bin/phpunit`

Expected: All Stage 4 tests pass. Report the known Stage 1 temporary-password redirect failure if it remains; do not mask it as a Stage 4 failure.

Run: `git diff --check`

Expected: No whitespace errors.

- [ ] **Step 4: Update Stage 4 checklist status**

Mark only tested Stage 4 items complete in `IMPLEMENTATION_CHECKLIST.md`. Keep UI conflict messaging, PDF generation/storage, true MySQL concurrency verification, and all later-stage work explicitly pending until their own checks run. Since the checklist already has unrelated worktree changes, stage only the Stage 4 checklist hunk when committing.

- [ ] **Step 5: Commit this task**

```bash
git add IMPLEMENTATION_CHECKLIST.md
git commit -m "docs: record voucher lifecycle status" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```
