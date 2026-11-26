# Frontend Prefetch and Loading UX Implementation Plan

> **For agentic workers:** Implement this plan task-by-task and verify each task before handoff.

**Goal:** Reduce perceived page loading time with Inertia’s built-in frontend cache while keeping Reverb as the source of cache invalidation and server refreshes.

**Architecture:** Use Inertia 2 link prefetching with short in-memory cache windows for common read-only navigation. Flush that cache before realtime-driven reloads so cached data cannot outlive a committed voucher or project change. Add a small layout-level navigation progress cue for uncached visits.

**Tech Stack:** Vue 3, Inertia 2, Laravel Echo/Reverb, TypeScript, Tailwind CSS v4.

## Global Constraints

- Do not use localStorage or sessionStorage for voucher/project data.
- Do not cache writes, permissions, totals, or authorization decisions.
- Reverb remains a notification channel; the database remains the source of truth.
- Do not add a dependency.

## Review Focus

- Prefetch cache must not survive a matching Reverb change without being flushed.
- Navigation links must remain usable when Reverb is unavailable.
- Mobile and desktop navigation must use the same authorized Inertia routes.
- Progress feedback must not block or disable ordinary navigation.

### Task 1: Cache-aware navigation and loading feedback

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `resources/js/Pages/Projects/Index.vue`
- Modify: `resources/js/Pages/Vouchers/Index.vue`
- Modify: `resources/js/Pages/Vouchers/History.vue`
- Modify: `resources/css/app.css`

- [x] Add Inertia prefetch and short cache windows to common read-only navigation links, pagination links, and dashboard actions.
- [x] Show a non-blocking navigation progress cue in the authenticated layout.
- [x] Preserve existing mobile drawer behavior, permissions, filters, and form submit behavior.

### Task 2: Realtime cache invalidation

**Files:**
- Modify: `resources/js/useRealtimeUpdates.ts`

- [x] Flush Inertia’s prefetch cache before voucher/project change handlers and reconnect refresh handlers run.
- [x] Preserve existing event cleanup and behavior when Echo is unavailable.

### Task 3: Verification

- [x] Run `npm run build` and inspect output.
- [x] Run `git diff --check`.
- [x] Review cache scope, stale-data behavior, and unrelated worktree changes.
