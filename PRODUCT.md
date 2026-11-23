# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Office employees working on a shared Windows-hosted LAN application. Employees enter and maintain office vouchers; Super Admins manage accounts, permissions, projects, and print settings.

## Product Purpose

Mar-vel OMS is an internal office management system for creating, maintaining, printing, and exporting accountable disbursement vouchers. Success means employees can complete routine voucher work from a shared browser while the system preserves reliable numbering, history, permissions, and printable records.

## Positioning

Its differentiator is an auditable, paper-compatible voucher workflow that keeps business dates, creator identity, editable Prepared By text, revisions, and system history separate instead of treating the browser form as the record.

## Operating Context

The application runs on a shared Windows PC and is used by employees over the office LAN. It replaces repetitive paper-entry coordination while preserving paper signatures and a familiar printed voucher. MySQL is the source of truth; Inertia handles browser requests and later Reverb notifications refresh authorized screens.

## Capabilities and Constraints

- Phase 1 is a Laravel modular monolith using Laravel 13, PHP 8.5, Vue 3, Inertia, MySQL, Reverb, and Echo.
- Phase 1 uses app-managed individual accounts, server-enforced permissions, and no public registration.
- Employees have view/create/edit access by default; Super Admins can restrict permissions and manage accounts.
- There is no separate REST API, digital approval workflow, PR/PO field, receipt upload, or managed expense-category catalog in Phase 1.
- Voucher numbering, totals, revisions, audit history, exports, and print output remain Laravel responsibilities.
- The database named `mar_vel_management_system` is the intended development database; credentials remain in the local environment only.
- The initial implementation scope is Stage 1: accounts, authentication, and RBAC. Later roadmap stages remain planned and must not be implied as complete.

## Evidence on Hand

- [Mar-vel OMS Ph1 Architecture.md](Mar-vel%20OMS%20Ph1%20Architecture.md)
- [Mar-vel-OMS plan.md](Mar-vel-OMS%20plan.md)
- The repository's Laravel 13 skeleton and existing database configuration.
- No production voucher data or verified print sample asset is currently present in the workspace.

## Product Principles

- Server authority over browser convenience.
- Individual accountability without shared credentials.
- Preserve historical records rather than rewriting them.
- Make routine office work clear, fast, and calm.
- Keep the modular monolith simple enough to operate on one LAN host.

## Accessibility & Inclusion

The authenticated workspace should use accessible contrast, visible keyboard focus, readable type, clear validation messages, and responsive layouts suitable for the shared office desktop and smaller screens.
