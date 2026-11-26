# Mobile Navigation Responsiveness Implementation Plan

> **For agentic workers:** Implement this plan task-by-task and verify each task before handoff.

**Goal:** Make the authenticated workspace mobile navigation behave as a fixed sidebar drawer without shifting page content or allowing the underlying page to scroll.

**Architecture:** Keep the existing Vue/Inertia layout and visual language. Replace the mobile navigation panel currently rendered in the sticky header with a fixed, transform-based drawer and backdrop. The layout owns open/close state, locks document scrolling while open, closes on backdrop/header/Escape interactions, and exposes logout in the sidebar.

**Tech Stack:** Vue 3, Inertia, TypeScript, Tailwind CSS v4, existing Laravel layout components.

**Spec:** User request plus `AGENTS.md`, `Mar-vel OMS Ph1 Architecture.md`, and `Mar-vel-OMS plan.md`.

## Global Constraints

- Do not change backend behavior, authorization, routes, or voucher business rules.
- Preserve the existing desktop sidebar and current visual tokens.
- Keep logout as an Inertia POST action and do not trust client-side visibility for authorization.
- Maintain keyboard focus visibility, accessible labels, reduced-motion support, and minimum touch targets.

## Review Focus

- Opening the mobile menu must not change the header height or push the main content downward.
- The document must not scroll behind the open drawer, and the drawer itself must remain scrollable when its contents exceed the viewport.
- Backdrop, top-bar clicks, Escape, navigation links, and logout must close or navigate safely without leaving the body locked.
- Desktop widths must retain the existing rail and must not render the mobile drawer/backdrop.

### Task 1: Fixed responsive authenticated navigation

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/css/app.css`

- [x] Replace the in-flow mobile navigation panel with a fixed left drawer and fixed backdrop that use the existing responsive breakpoint and visual tokens.
- [x] Add open-state body scroll locking with cleanup, Escape-to-close handling, and close behavior for backdrop, top bar, and navigation actions.
- [x] Add a visible logout action to the desktop rail and mobile drawer using the existing POST logout route; remove the duplicate logout action from the account dropdown while retaining profile settings.
- [x] Keep the mobile trigger at a 44px touch target and connect `aria-expanded`, `aria-controls`, drawer labeling, and backdrop semantics.

### Task 2: Verification

**Files:**
- No new test files unless the browser check exposes a regression that requires one.

- [x] Run `npm run build` and inspect the output for a successful production bundle.
- [ ] Start the local app as needed and use Playwright at a mobile viewport to verify no layout shift, body scroll lock, outside-click/Escape close, drawer scrolling, and logout visibility. Blocked by the local account credentials and the first sandbox browser-process permission error.
- [ ] Verify a desktop viewport retains the fixed rail and does not show the mobile drawer or backdrop. Blocked by the same Playwright login check.
- [x] Review the final diff and report checks run, checks not run, and any residual risk.
