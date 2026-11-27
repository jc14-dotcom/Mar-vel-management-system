# Mar-vel OMS Phase 1 Implementation Plan

**Status:** Approved architecture; application implementation is in progress.
**Audience:** Agentic coding agents implementing Phase 1.
**Source of truth:** [Mar-vel OMS Ph1 Architecture.md](Mar-vel%20OMS%20Ph1%20Architecture.md), this plan, and the voucher sample `voucher.jpg`.

## How to Use This Plan

1. Read the root `AGENTS.md` and the architecture document before changing application code.
2. Implement stages in order. A stage may be split into smaller tasks, but do not start a dependent stage before its predecessor passes its exit gate.
3. Keep database and UI contracts aligned. Update this plan and the architecture document when the user approves a material decision change.
4. If a missing decision affects money, numbering, authorization, audit history, or printed records, stop and ask the user. For minor implementation details, use the defaults in this plan and report the choice.
5. Do not claim an acceptance check passed unless it was actually run and its output was inspected. Record checks that could not be run.

## Fixed Phase 1 Decisions

- Build a LAN-only Laravel modular monolith on the required shared Windows PC. Use Laravel 13, Vue 3 with Inertia, MySQL, Laravel Reverb, and Echo. Do not introduce microservices.
- Inertia and Laravel web routes handle application requests. Do not add a separate REST API in Phase 1. Revisit this only if another client or integration is approved.
- Use app-managed employee accounts. No public sign-up. The Super Admin manages accounts, permissions, settings, and project maintenance.
- All employees can view, create, and edit shared vouchers by default. Permission controls can restrict users. Separate vouchers can be created concurrently; stale edits to the same voucher must be detected and rejected with a reload prompt.
- Assign `DV-YYYY-####` when the voucher is first saved, based on the system entry year. Backdating the voucher date must not change its number. Never reuse a cancelled number.
- Keep the voucher date separate from system creation, update, print, and audit timestamps.
- `Prepared By` starts with the logged-in account name but is editable. Store the actual creator account independently and immutably.
- One voucher has one project/scope/category combination. Project is searchable and registered; a typed unknown project is added to the shared list. Scope and category are separate free-text fields with no category catalog.
- Particulars are free-text rows with amounts. A row can be an expense or a deduction. Users enter deductions as positive amounts; the server subtracts them when calculating the total.
- Signature lines remain on paper. The app does not capture digital approvals or track whether paper signatures were returned.
- A post-print edit requires a reason, creates a new revision for fresh signatures, and supersedes the previous print. A cancelled voucher remains in the database with its number and reason.
- Phase 1 has no PR/PO fields, receipt uploads, or supporting-file attachments.
- Export a generic `.xlsx` with one row per particular. Printing provides PDF preview/download and a Print action using the same generated output.
- The backup destination is not yet selected. A separate external drive, NAS, or second machine is required before production use.

## Stage 0 — Repository and Local Development Foundation

**Goal:** Establish a reproducible baseline before application features are added.

**Tasks:**

- Confirm the workspace and preserve the architecture document, implementation plan, and voucher sample. The workspace currently has no application code or Git repository; initialize source control without deleting or replacing these files.
- Create the Laravel application with the current compatible Laravel 13, PHP 8.3 or later, Vue 3, TypeScript, and Inertia starter. Keep the frontend and backend in one deployable application.
- Configure a supported MySQL release and a local development connection. Keep real credentials in ignored environment files and provide only safe example values in `.env.example`.
- Configure the database-backed queue for the small LAN deployment. Do not add Redis, Docker, cloud services, or a separate API unless a later approved requirement needs them.
- Establish a small feature-oriented structure using Laravel conventions; add only dependencies needed for an approved feature.

**Exit checks:**

- A fresh local setup can install dependencies, run migrations, build frontend assets, and serve the login screen.
- No real secrets are tracked. The application has no public registration route.
- The repository baseline includes the two project guidance documents and preserves the sample voucher.

## Stage 1 — Accounts, Authentication, and RBAC

**Goal:** Establish individual accountability and server-enforced permissions.

**Tasks:**

- Add app-managed user accounts with active/disabled state and a safe Super Admin bootstrap process. Never ship a shared default password or public account-registration flow.
- Implement Super Admin and Employee roles plus explicit permissions for voucher view, create, edit, print, export, cancel, account administration, project administration, and settings.
- Use `spatie/laravel-permission` for role and permission relationships while keeping app-managed account creation, temporary-password enforcement, and the existing audit event contract.
- Give employees view/create/edit access to shared vouchers by default. Keep permission administration Super Admin-only. Record permission changes in the audit history.
- Use Laravel policies or gates for every protected action. Frontend visibility is a usability aid, never the authorization boundary.
- Prefill `Prepared By` from the authenticated user, allow an authorized employee to edit that display value, and retain `created_by` as the actual account that saved the voucher.

**Exit checks:**

- Unauthenticated requests cannot reach voucher or project data.
- The permission matrix is checked for employee and Super Admin accounts, including denied actions.
- Prepared By can be edited while the creator identity remains attributable in the audit record.

## Stage 2 — Projects and Form Settings

**Goal:** Make project entry consistent while allowing new and historical projects.

**Tasks:**

- Create a searchable project master. Normalize whitespace and case for duplicate checks; display existing matches while an employee types.
- When the typed name is not an exact existing match, create it as a shared project immediately. Do not silently merge similar names.
- Allow the Super Admin to rename or merge duplicate project records. Preserve the project name recorded on existing vouchers so maintenance does not rewrite historical documents.
- Store voucher scope and expense category as separate free-text header values. Do not build a category management table.
- Add configurable print-template settings for company name, form number `MCS-OP-ACC-01F3`, effective date, template revision, and the notices shown in `voucher.jpg`.

**Exit checks:**

- A newly typed project is available to other users and to later searches.
- Admin rename/merge actions are permission-protected and audited; old vouchers retain their recorded project display.
- Scope and category can be entered independently without requiring a catalog.

## Stage 3 — Voucher Data and Business Rules

**Goal:** Implement the core voucher record and reliable calculations.

**Tasks:**

- Add `vouchers` and `voucher_items`. A voucher stores its unique number, actual voucher date, payee, project reference and name snapshot, scope, category, Full/Partial payment choice, creator, Prepared By display name, status, and timestamps.
- Add voucher items with description, decimal amount, line type (`expense` or `deduction`), and display order. Do not add quantity/unit/rate fields in Phase 1.
- Calculate totals on the server using decimal arithmetic: sum expenses minus sum deductions. Treat submitted browser totals as display-only and recalculate before saving and printing.
- Add a yearly sequence record and allocate numbers inside a database transaction with a unique constraint. Use the system entry year; format with at least four sequence digits and allow the sequence to grow beyond four digits.
- Support backdated voucher dates without altering `created_at`, number sequence, or audit timestamps.
- Require the fields needed to produce a valid voucher: voucher date, payee, project, payment type, and at least one non-empty particular with an amount. Keep scope and category free text; allow empty values only where the sample workflow permits them.

**Exit checks:**

- Parallel voucher creation cannot produce duplicate numbers.
- Backdated vouchers receive the next entry-year number and retain distinct system timestamps.
- Expense, deduction, and total values are correct to two decimal places and use PHP currency formatting.
- The voucher can be edited before its first print without losing its creator or activity history.

## Stage 4 — Audit, Revisions, Cancellation, and Edit Conflicts

**Goal:** Preserve a reliable history of changes and printed versions.

**Tasks:**

- Add append-only audit events for voucher creation, edits, cancellation, project maintenance, permission changes, and printing. Record actor, timestamp, action, and field changes; never log passwords or secrets.
- Add a monotonically increasing record version for optimistic concurrency. Reject a save based on a stale version, show that another user saved first, and reload the current values.
- Keep a voucher lifecycle such as `draft`, `printed`, `reprint_required`, and `cancelled`. Do not invent a digital `signed` or `approved` state.
- Require a reason for post-print edits and cancellations. Preserve a complete snapshot for each printed revision, identify prior prints as superseded after a new revision is printed, and retain each generated unsigned PDF in protected local storage.
- Do not hard-delete vouchers, revisions, or audit events. Never recycle cancelled voucher numbers.

**Exit checks:**

- The audit trail distinguishes the actual creator from editable Prepared By text and shows who changed each field and when.
- A stale editor cannot silently overwrite a newer save.
- Editing a printed voucher requires a reason and produces a new revision; the old revision remains accessible and marked superseded.
- Cancellation leaves a retained record and a permanent sequence gap.

## Stage 5 — Voucher Screens and Real-Time Updates

**Goal:** Let employees work from a shared browser interface and see relevant changes promptly.

**Tasks:**

- Build Inertia/Vue pages for the voucher list, search/filter, create/edit form, activity history, project autocomplete, and permission-controlled actions.
- Use Laravel validation and authorization for all writes. The browser may calculate a preview, but only the server decides saved totals and permissions.
- Broadcast voucher/project change events through Reverb and Echo after the database transaction commits. Use authenticated private channels and send only the identifiers and version needed to refresh the screen.
- On a relevant event, refresh the affected Inertia data. Show connection state; after a WebSocket reconnect, fetch current data because the database is authoritative.
- Use MySQL for the queue. Supervise the Reverb process and queue worker as auto-starting services on the Windows host.

**Exit checks:**

- Two browser sessions see newly created and updated vouchers without manual full-page refresh under normal LAN operation.
- Unauthorized users cannot subscribe to protected channels or receive voucher data.
- If Reverb disconnects, saving and viewing still work through the application; clients refresh after reconnection.

## Stage 6 — Print Layout and PDF

**Goal:** Reproduce the provided form cleanly while supporting readable variable-length vouchers.

**Tasks:**

- Implement one print template based on `voucher.jpg`, including Paid To, Cost Center/Project, Scope of Work, Date, Particulars, Amount, Total, payment receipt wording, signature lines, form number, effective date, template revision, and footer notices.
- Display the project, scope, and category together while retaining separate values in storage. Keep voucher revision distinct from form-template revision.
- Generate PDF output with the selected PHP Dompdf wrapper and store each generated unsigned PDF privately by voucher revision. The Print action should print the same generated PDF used for preview/download.
- Lay out two half-Letter vouchers vertically on one Letter-sized short-bond sheet when both fit, with a cut guide. If a voucher needs more than half a sheet, allocate it a full sheet and start the next voucher on a fresh sheet.
- Continue very long vouchers onto full short-bond sheets, repeat the voucher number, and keep the total and signature block together on the final sheet. Preserve readable type; do not shrink text to force-fit.

**Exit checks:**

- Render and inspect single, two-up, deduction, long-description, and multi-page cases at actual print size.
- Compare spacing, footer, labels, borders, amount alignment, and signature blocks against the sample.
- Confirm the PDF preview and Print action produce the same layout and that totals/signatures are not split incorrectly.

## Stage 7 — Search and Excel Export

**Goal:** Support follow-up Excel encoding without a company-specific workbook.

**Tasks:**

- Add filters for voucher number, voucher date, project, payee, and category, with sensible date-range defaults.
- Generate a generic `.xlsx` with one row per particular and repeated voucher metadata. Include voucher number, actual date, system entry timestamp, payee, project, scope, category, payment type, description, line type, amount, and creator/Prepared By values.
- Export numeric cells as numeric values. Include an explicit deduction type and signed/net amount suitable for spreadsheet calculations; protect against spreadsheet formula injection in text fields.
- Apply the same server-side authorization to searches, detail views, and exports.

**Exit checks:**

- The export opens in Excel, has one row per particular, preserves decimals/dates, and totals consistently with the voucher.
- Search results and exports respect permission restrictions and filters.

## Stage 8 — LAN Deployment, Backups, and Pilot

**Goal:** Run the app reliably on the available shared Windows PC and prepare for office use.

**Tasks:**

- Configure the web server to serve only Laravel's `public` directory. Bind MySQL to the host; expose only the application and WebSocket ports to the office LAN through Windows Firewall.
- Configure a stable LAN hostname or reserved address and HTTPS with an office-trusted certificate where feasible. Disable production debug output.
- Run MySQL, the web server, Reverb, and the queue worker as supervised auto-start services. Ensure the shared PC does not sleep during office hours and document that a shutdown makes the system unavailable.
- Provide a separate backup destination before launch. Schedule daily database backups, retain rotating daily and monthly copies, keep a separate/offline copy, and perform a documented restore check before rollout and on a regular schedule.
- Pilot with a small employee group. Compare printouts with the existing form, verify Excel encoding, exercise role permissions, and keep the existing paper process available during the pilot.
- Record maintenance steps for deployment, process restart, log review, account deactivation, backup, and restore in this plan or the existing project guidance; do not add cloud runtime dependencies.

**Release gate:**

- A Windows restart brings the required services back automatically.
- Only office-LAN clients can reach the app; employee PCs cannot connect directly to MySQL.
- A backup from the separate destination has been restored successfully into a test database.
- Users complete a voucher from entry through printed revision and Excel export using the agreed rules.

## Phase 2 and Later

Keep later work outside Phase 1 unless separately approved. Candidate modules include PR/PO linkage, receipt attachments, digital approvals, project budgets, and other office-management features. Add these as modules inside the Laravel application first; reconsider separate services only if scale or independent deployment needs justify them.

## Final Acceptance Checklist

- Accounts, permissions, account deactivation, and private-channel authorization behave as defined.
- Backdated date, entry timestamp, yearly numbering, cancellation, and revision history remain distinct and auditable.
- Parallel creation is safe; stale same-voucher edits are rejected without data loss.
- Scope/category free text, project auto-add/merge, expense rows, deduction rows, and server-calculated totals are correct.
- Print and PDF match the sample, two-up sheets cut cleanly, long vouchers continue readably, and only the final page carries the complete total/signature block.
- `.xlsx` export reflects the same values as the saved voucher and does not evaluate user text as formulas.
- Realtime failure does not corrupt or block persisted voucher data.
- Restore from the production backup destination succeeds.
