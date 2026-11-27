# Centered Logout Confirmation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Center every shared modal in the viewport and give the system-wide logout confirmation an intentional Mar-vel OMS visual treatment.

**Architecture:** Keep the existing Vue/Inertia layout and shared `Modal.vue` abstraction. Fix the positioning at the shared modal wrapper so the existing account-deletion dialog and logout dialog receive the same safe centering behavior. Refine only `LogoutConfirmation.vue` for the logout-specific content and controls.

**Tech Stack:** Vue 3, TypeScript, Inertia, Tailwind CSS v4, Laravel Vite build.

**Spec:** Approved user request and short design from the preceding conversation.

## Global Constraints

- Keep logout as the existing authenticated Inertia POST action.
- Keep authorization and business rules server-side; this is presentation-only.
- Reuse existing components and the existing forest, moss, sun, paper, and danger palette.
- Preserve keyboard dismissal, body scroll locking, reduced-motion support, and mobile navigation sequencing.
- Do not add dependencies or change routes.

## Review Focus

- Existing account-deletion modal: shared centering must not remove its scrollability or focus behavior.
- Short mobile viewport: the panel must remain fully reachable with safe vertical padding.
- Long content: the shared wrapper must allow scrolling without placing the panel off-screen.
- Keyboard use: Escape and visible focus states must remain available.
- Logout flow: Cancel closes locally; Log out still posts to the existing route.

### Task 1: Center the shared modal and restyle logout confirmation

**Files:**
- Modify: `resources/js/Components/Modal.vue`
- Modify: `resources/js/Components/LogoutConfirmation.vue`

**Interfaces:**
- Consumes: existing `Modal` props/events and `LogoutConfirmation` `v-model:show` contract.
- Produces: a centered shared dialog surface used by all current modal consumers.

- [ ] Write a browser check that opens the authenticated logout confirmation and asserts the dialog panel is centered within the viewport, remains inside the viewport at a mobile size, and exposes Cancel and Log out controls.
- [ ] Run the browser check against the current implementation and confirm it fails because the panel is top-anchored.
- [ ] Update `Modal.vue` so the viewport wrapper centers the panel with flex alignment, keeps safe responsive padding, and retains overflow scrolling for tall panels.
- [ ] Update `LogoutConfirmation.vue` with a compact warning cue, OMS palette, clear heading/body copy, full-width mobile action layout, and existing component buttons.
- [ ] Run the browser check at desktop and mobile viewport sizes and confirm the panel is centered and actionable.
- [ ] Run the frontend build and repository checks.

### Verification

- `npm run build`
- `git diff --check`
- Browser check with Playwright at desktop and mobile sizes, if local authentication is available.

## Self-Review

- Scope is limited to the shared modal positioning and the reusable logout confirmation.
- No backend behavior, route contract, or dependency changes are required.
- The shared wrapper retains scroll behavior for the existing delete-account flow.
- If authenticated browser automation is unavailable, report that check as not run and rely on build plus source-level assertions without claiming visual verification.
