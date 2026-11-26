# Voucher Lifecycle Design

## Intent

Complete Stage 4 before building the voucher screens. The voucher data backend exists, but records have no version check, printed-revision history, or cancellation workflow. These controls should be in place before the UI exposes shared editing and historical voucher actions.

## Goals

- Reject edits based on an outdated `record_version` without changing voucher data or audit history.
- Track the lifecycle as `draft`, `printed`, `reprint_required`, and `cancelled`; do not add digital approval or signature states.
- Preserve a snapshot for each printed revision. A post-print edit requires a reason and creates a pending revision; the existing print remains current until the new revision is printed, then becomes superseded.
- Require a reason for cancellation, preserve the voucher and its number, and never recycle cancelled numbers.
- Keep each state-changing operation and its audit event in one database transaction, with Laravel enforcing authorization.

## Non-goals

- Voucher list, search/filter, create/edit, or activity-history screens. Those are Stage 5.
- PDF rendering and protected PDF storage. Those are Stage 6; the print-state action must be usable by that later integration.
- Reverb/Echo updates, Excel export, deployment, or changes to the existing numbering and money rules.

## Constraints

- Stay within the approved Phase 1 Laravel/Inertia/MySQL monolith; do not add a REST API or dependencies.
- Keep business rules and authorization on the server.
- Keep voucher data, revision snapshots, and audit events; do not hard-delete or use silent last-write-wins updates.
- Follow the existing Stage 4 implementation plan and current database/test conventions.

## Approaches

1. **Stage 4 before Stage 5 (recommended).** Implement lifecycle and concurrency behavior first, then build the list and edit screens against stable rules. This preserves the approved stage order and avoids later UI rework, but delays browsing old vouchers until Stage 5.
2. **Stage 5 list/search before Stage 4.** Make historical vouchers visible sooner, but build the UI before lifecycle and stale-edit behavior are settled; the pages would need follow-up changes and this reverses the approved stage order.

**Recommendation:** follow Stage 4 before Stage 5. This is a proposed default, not a user-approved change to the roadmap.

## Design

Keep voucher behavior in focused Laravel actions and use validated requests at the HTTP boundary. Add a monotonically increasing `record_version` and compare it under a row lock before applying edits or cancellation. Store printed snapshots in `voucher_revisions`, and update voucher state, revision state, and audit events transactionally. On cancellation, retain the voucher and its full history. Stage 5 will present these records in the browser; Stage 6 will connect printed revisions to generated PDFs.

The detailed task sequence and acceptance checks are in [the Stage 4 implementation plan](../plans/2026-11-26-voucher-lifecycle-concurrency.md).

## Approval

This design is pending user review. Do not implement Stage 4 until it is approved.
