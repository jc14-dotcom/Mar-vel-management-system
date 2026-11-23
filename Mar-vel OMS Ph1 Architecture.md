# Mar-vel Office Management System — Phase 1 Architecture

## Summary and Voucher Workflow

Build a LAN-only, modular Laravel application for the shared Windows PC. Use **Laravel 13, Vue 3, Inertia, MySQL, Laravel Reverb, and Echo**. Inertia will handle the browser UI; **Phase 1 will have no separate REST API**, as requested. A versioned API can be added later if another app or integration needs one. Laravel’s current Vue starter kit uses Vue with Inertia, and Laravel documents Reverb for its broadcasting stack. ([Vue starter kit](https://laravel.com/framework/docs/13.x/starter-kits), [broadcasting](https://laravel.com/framework/docs/broadcasting))

The voucher workflow will work as follows:

- Assign a unique number when a voucher is first saved, in the format `DV-YYYY-####`, using the **entry year**, not the voucher’s backdated date.
- Keep the voucher’s business date separate from immutable system timestamps and audit events.
- Allow employees to view, create, and edit shared vouchers by default. The Super Admin can restrict permissions. Employees can create vouchers in parallel; if someone else saves the same draft first, warn the current editor and reload the newer version.
- Prefill **Prepared By** from the logged-in user, but allow employees to edit the printed name. Keep the actual creator account and edits in the audit history.
- Keep signatures for Prepared By, Recommended By, Noted By, Checked By, Approved By, and Received By on paper. The app will record print and revision history, but won’t record digital sign-offs or whether a signed paper copy was returned.
- If a printed voucher changes, require a reason, create a new revision for fresh signatures, and mark the previous print as superseded. Retain cancelled vouchers and their numbers; never reuse numbers.
- Support one project/scope/category combination per voucher. Project names autocomplete from a shared list; a new typed name is added immediately. Admins can rename or merge duplicates, while existing vouchers retain the name recorded at entry.
- Use separate free-text fields for Scope of Work and Expense Category, displayed together on the voucher. Do not create a managed category list. Keep Paid To as free text.
- Use free-text particulars with an amount per row. A row can be marked as a deduction; the user enters a positive amount and the system subtracts it. Calculate totals on the server using decimal currency values. Include a per-voucher Full/Partial payment choice.
- Phase 1 will not include PR/PO fields, receipt uploads, or attachment storage.

The sample’s footer fields—form number `MCS-OP-ACC-01F3`, effective date, template revision, and procedural notices—belong in configurable print-template settings. Keep the **form template revision** separate from each voucher’s revision.

## Architecture, Database, and Technology Comparison

- **Selected application stack:** a single Laravel modular monolith with Vue/Inertia pages, MySQL, and Reverb/Echo. Inertia handles page visits and form actions; authenticated WebSocket events notify other screens to refresh relevant data. Use private channels and send only the identifiers/version needed for clients to refresh.
- **REST:** omit a separate REST API from Phase 1. This avoids maintaining two interfaces for one browser app. Add a versioned API only if another client or integration becomes a real requirement.
- **MySQL:** selected, and appropriate for shared relational records. Run it as a Windows service; keep its port private to the host so employee PCs connect only to the web app. MySQL documents Windows service installation and startup behavior. ([MySQL on Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-start-service.html))
- **PostgreSQL:** a strong alternative with Windows installers, but there is no existing company database standard that makes changing from your chosen MySQL worthwhile. ([PostgreSQL Windows installers](https://www.postgresql.org/download/windows/))
- **SQLite:** technically possible for a small app hosted on one machine, but it serializes writes to one writer at a time. MySQL is the better fit for the shared multi-user system. ([SQLite use cases and concurrency](https://www.sqlite.org/whentouse.html))
- **SQL Server Express:** viable on Windows, but unnecessary without existing SQL Server operations. SQL Server 2025 Express has a 50 GB database limit. ([Microsoft edition limits](https://learn.microsoft.com/en-us/sql/sql-server/editions-and-components-of-sql-server-latest?view=sql-server-ver17))
- **Python/FastAPI:** capable of building APIs, but offers no clear advantage for this Inertia-based Laravel app. ([FastAPI overview](https://fastapi.tiangolo.com/tutorial/first-steps/))
- **Supabase and Convex:** both can be self-hosted, so they are not inherently cloud-only. Supabase’s self-hosted stack uses Docker and requires the company to manage updates, backups, and security; Convex self-hosting would replace the selected MySQL/Laravel data model. Neither is needed for this Phase 1 system. ([Supabase self-hosting](https://supabase.com/docs/guides/self-hosting), [Convex self-hosting](https://docs.convex.dev/self-hosting))

Use these relational tables and relationships:

- `users`, `roles`, `permissions`, and assignment tables for accounts and RBAC.
- `projects`; one project can have many vouchers. Store a project-name snapshot on each voucher so later renames or merges do not rewrite historical vouchers.
- `vouchers`; each stores number, actual date, payee, project reference and snapshot, scope, free-text category, payment type, creator, editable Prepared By text, and current status.
- `voucher_items`; each belongs to one voucher and stores description, amount, expense/deduction type, and display order.
- `voucher_number_sequences`; allocate each year’s sequence atomically so simultaneous saves cannot receive duplicate numbers.
- `voucher_revisions` and `audit_events`; preserve printed-version snapshots, change reason, actor, timestamp, and before/after edits. Retain the generated unsigned PDF per print revision; do not store signed receipt scans.
- `app_settings`; hold company name, form number, effective date, template revision, and print wording.

## Security, Printing, and Deployment

Use app-managed individual accounts with no public self-registration. The Super Admin creates, disables, and resets accounts and manages permissions, projects, and settings. Enforce permissions on the server, use secure password hashing and session protections, and keep an append-only activity history visible to authorized voucher users.

For printing, generate a **Letter-sized short-bond sheet** with two stacked half-Letter vouchers and a cut guide when both fit. A longer voucher uses a full sheet; very long vouchers continue onto full sheets, repeat the voucher number, and keep the total and signature block together on the final sheet. Provide both PDF preview/download and a Print action using the same generated output, so the layout stays consistent. A PHP Dompdf wrapper supports Laravel 13 and page-break rules, avoiding a separate PDF service on the Windows host. ([Dompdf Laravel compatibility](https://github.com/barryvdh/laravel-dompdf/blob/master/composer.json), [page-break support](https://github.com/barryvdh/laravel-dompdf))

Export a generic `.xlsx`, with one row per particular and voucher fields repeated as needed for Excel. Include the actual voucher date, separate system entry timestamp, project, scope, category, payee, line type, amount, and voucher number.

Deploy on the required shared Windows PC with the app, MySQL, Reverb, and a supervised queue worker configured to start automatically. Restrict inbound access to the office LAN, serve only Laravel’s `public` directory, and keep database access local to the host. The PC must stay awake and available during office use; if it is shut down or restarted, users lose access until services return. Laravel identifies queue workers and Reverb as long-running services that need monitoring and restarting after deployment. ([Laravel deployment guidance](https://laravel.com/framework/docs/13.x/deployment), [queue workers](https://laravel.com/framework/docs/13.x/queues))

A separate backup destination is still undecided and is a production prerequisite. Use an external drive or NAS, schedule daily MySQL backups, retain rotating copies, and verify restoration regularly.

## Roadmap and Acceptance Checks

1. **Foundation:** configure LAN hosting, MySQL, app-managed accounts, RBAC, project autocomplete, audit events, and safe yearly voucher numbering.
2. **Voucher module:** build the editor, free-text particulars and deductions, server-calculated totals, backdating, Full/Partial choice, cancellation, and revision controls.
3. **Output and collaboration:** implement Reverb/Echo updates, PDF and print output, two-up batching, continuation sheets, and `.xlsx` export.
4. **Pilot and rollout:** pilot with employees on the office LAN, compare printed forms against the sample, confirm backups can be restored, then introduce future modules within the same modular monolith.

Acceptance checks should cover simultaneous voucher creation without duplicate numbers; backdated dates without changing number order; correct expense and deduction totals; project auto-add and duplicate merge preserving history; Prepared By prefill/edit plus creator audit; stale-edit warnings; cancellation and post-print revision rules; print layout and readable overflow; export contents; permission enforcement and WebSocket authorization; and recovery from a backup.

**Defaults to carry into implementation:** no REST API or digital approval workflow in Phase 1; no PR/PO fields or receipt uploads; app-managed accounts; generic `.xlsx` export; no hard deletion; a separate backup device must be provided before launch.
