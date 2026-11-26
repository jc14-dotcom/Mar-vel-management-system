# Stage 5 Realtime Updates Implementation Plan

**Status:** Code implementation and automated verification complete; live LAN acceptance remains pending.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add private, LAN-hosted realtime notifications so authorized voucher and project screens refresh after committed changes and recover current data after reconnecting.

**Architecture:** Add Laravel Reverb and Echo to the existing Laravel/Inertia application. Broadcast only change identifiers and voucher record versions after database commits; authorized clients fetch current data through Inertia. Keep ordinary page visits and saves usable when WebSockets are unavailable.

**Tech Stack:** Laravel 13, Vue 3, Inertia 2, MySQL database queue, Laravel Reverb, Laravel Echo, TypeScript.

**Spec:** [Mar-vel OMS Phase 1 Architecture](../../../Mar-vel%20OMS%20Ph1%20Architecture.md); [Mar-vel OMS implementation plan, Stage 5](../../../Mar-vel-OMS%20plan.md)

## Global Constraints

- Build a LAN-only, modular Laravel application using Laravel 13, Vue 3, Inertia, MySQL, Laravel Reverb, and Echo.
- Inertia will handle the browser UI; Phase 1 will have no separate REST API.
- Broadcast only after the database transaction commits.
- Use authenticated private channels and minimal event payloads; clients fetch current authorized data through Inertia.
- Treat Reverb as a notification channel, not a database. Reconnect by refreshing relevant data and handle unavailable sockets without losing saved work.
- Use MySQL for the queue. Supervise the Reverb process and queue worker as auto-starting services on the Windows host.
- Before adding a package, confirm it supports the selected Laravel/PHP versions and works without a cloud service. Dependency changes require explicit approval before execution.
- Implemented behavior defaults: preserve unsaved editor input and offer an explicit reload after a matching voucher changes; automatically refresh read-only register/history data while preserving filters and page; never block ordinary reads or saves because Reverb is disconnected.

## Approaches

- **Laravel Reverb and Echo (selected):** fits the approved LAN-only Laravel architecture, supports authenticated private channels, and lets Inertia reload authorized database data. The architecture and Stage 5 acceptance checks already select this approach.
- **Polling with Inertia:** avoids a WebSocket process but does not meet the Stage 5 Reverb/Echo requirement and adds repeated requests.
- **Hosted broadcaster or separate realtime service:** rejected because Phase 1 is LAN-only and does not add cloud runtime services or microservices.

The approved architecture settles the transport. The editor-preservation and read-only refresh details above were implemented as the Stage 5 behavior defaults.

## Review Focus

- A mutation inside a transaction that rolls back must not publish a change; cover in Task 2 with `test_rolling_back_a_voucher_change_does_not_broadcast`.
- A user missing the relevant permission must not subscribe to a private channel; cover in Task 1 with the channel authorization tests.
- An older or duplicate voucher event must not overwrite newer local form data; cover in Task 3 with a two-session stale-version acceptance check.
- A project rename or merge must update active project choices without rewriting historical voucher snapshots; cover in Task 3 with a project-change acceptance check.
- A disconnected socket must not block saves, and reconnect must refresh server state without discarding editor input; cover in Task 3 with disconnect/reconnect acceptance checks.

---

### Task 1: Configure Reverb, Echo, and Private Channel Authorization

**Files:**
- Create: `routes/channels.php`
- Create: `resources/js/echo.ts`
- Create: `tests/Feature/BroadcastingTest.php`
- Modify: `bootstrap/app.php`
- Modify: `resources/js/app.ts`
- Modify: `.env.example`
- Generated/modified by the installer: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `config/broadcasting.php`, `config/reverb.php`

**Interfaces:**
- Produces a single Echo client exported from `resources/js/echo.ts`.
- Defines private channel `vouchers`, authorized for users with `vouchers.view` or `vouchers.edit`.
- Defines private channel `projects`, authorized for users with `vouchers.view`, `vouchers.create`, `vouchers.edit`, or `projects.manage`.
- Uses `VITE_REVERB_*` values only for browser connection settings; Reverb secrets remain server-side.

- [x] **Step 1: Write channel authorization feature tests**

Add these cases to `tests/Feature/BroadcastingTest.php`:
- `test_voucher_channel_authorizes_users_with_view_or_edit_permission`
- `test_project_channel_authorizes_users_with_voucher_or_project_access`
- `test_private_channels_forbid_users_without_access_permissions`
- `test_private_channels_require_an_authenticated_user`

Post `socket_id` and `channel_name` to `/broadcasting/auth`. Assert `200` for allowed users and `403` for denied users. For example:

```php
$this->actingAs($viewer)
    ->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-vouchers',
    ])
    ->assertOk();

$this->actingAs($unprivilegedUser)
    ->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-vouchers',
    ])
    ->assertForbidden();
```

- [x] **Step 2: Run the tests and verify they fail**

Run: `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit tests/Feature/BroadcastingTest.php`

Expected: allowed channel requests fail until the callbacks are registered. Tests must use a real Reverb/Pusher broadcaster because Laravel's null broadcaster bypasses channel authorization.

- [x] **Step 3: Install the Laravel 13 broadcasting scaffold**

After explicit approval for dependency changes, run:

```powershell
php artisan install:broadcasting --reverb --no-interaction
```

The installer adds `laravel/reverb` and the Echo client dependencies (`laravel-echo` and `pusher-js`). Use the generated configuration and verify the installed Composer and npm package versions support Laravel 13 and the current PHP version. Do not add a hosted broadcaster or another realtime service.

- [x] **Step 4: Define and register the two private channels**

In `routes/channels.php`, implement the permissions listed in the Interfaces section. Register the channel routes through the generated Laravel broadcasting bootstrap configuration, preserving authenticated web-session authorization.

- [x] **Step 5: Configure one Echo client**

Create `resources/js/echo.ts` using the generated Reverb settings and environment-backed `VITE_REVERB_*` values. Import it once from `resources/js/app.ts`. Add only non-secret local placeholders to `.env.example`.

- [x] **Step 6: Run channel tests and verify setup**

Run: `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit tests/Feature/BroadcastingTest.php`

Expected: all four authorization cases pass. Also run `php artisan route:list --path=broadcasting` and confirm the authorization route is registered.

- [x] Build: `npm run build` passed.

- [ ] Commit the Task 1 changes.

```bash
git add routes/channels.php bootstrap/app.php resources/js/echo.ts resources/js/app.ts tests/Feature/BroadcastingTest.php .env.example composer.json composer.lock package.json package-lock.json config/broadcasting.php config/reverb.php
git commit -m "feat: configure private realtime channels"
```

### Task 2: Broadcast Committed Voucher and Project Changes

**Files:**
- Create: `app/Events/VoucherChanged.php`
- Create: `app/Events/ProjectChanged.php`
- Modify: `app/Actions/Vouchers/CreateVoucherAction.php`
- Modify: `app/Actions/Vouchers/UpdateVoucherAction.php`
- Modify: `app/Actions/Vouchers/CancelVoucherAction.php`
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `app/Http/Controllers/Admin/ProjectController.php`
- Test: `tests/Feature/BroadcastingTest.php`

**Interfaces:**
- `VoucherChanged(int $voucherId, int $recordVersion, string $changeType)` broadcasts on private channel `vouchers` as `voucher.changed`. Its payload contains only `voucher_id`, `record_version`, and `change_type`.
- `ProjectChanged(int $projectId, string $changeType)` broadcasts on private channel `projects` as `project.changed`. Its payload contains only `project_id` and `change_type`.
- Both events implement `ShouldBroadcast` and `ShouldDispatchAfterCommit`.
- Voucher change types are `created`, `updated`, and `cancelled`; project change types are `created`, `renamed`, and `merged`.

- [x] **Step 1: Write failing event integration tests**

Add feature tests proving:
- Creating, updating, and cancelling a voucher dispatches the corresponding `VoucherChanged` with the saved voucher ID and current `record_version`.
- Creating a genuinely new project, renaming a project, and merging a project dispatches `ProjectChanged` with the affected project ID and change type.
- Submitting a normalized project name that already exists does not dispatch a second `created` event.
- Rolling back an outer transaction after a voucher or project mutation does not dispatch its change event.

Use `Event::fake()` and `Event::assertDispatched()` to assert the exact event class and payload values. Use `Event::assertNotDispatched()` after a rolled-back outer transaction.

- [x] **Step 2: Run the new tests and verify they fail**

Run: `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit tests/Feature/BroadcastingTest.php`

Expected: event assertions fail because no broadcast events are defined or dispatched.

- [x] **Step 3: Implement the two typed broadcast events**

Create the event classes with explicit `broadcastOn`, `broadcastAs`, and `broadcastWith` methods. Use `PrivateChannel` and include no names, payees, amounts, metadata, or other voucher contents in event payloads.

- [x] **Step 4: Dispatch voucher events within their existing transactions**

Dispatch `VoucherChanged` after the audit write in each voucher action, using the new `record_version` after update/cancellation. `ShouldDispatchAfterCommit` must discard events if the containing transaction rolls back.

- [x] **Step 5: Dispatch project events only for committed changes**

Dispatch `ProjectChanged` inside the existing transaction after audit writes for project creation, rename, and merge. Emit `created` only when `firstOrCreate` actually creates a row; do not emit on normalized-name matches.

- [x] **Step 6: Run event tests and existing business tests**

Run: `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit tests/Feature/BroadcastingTest.php`

Expected: event payload, event type, duplicate-project, and rollback tests pass.

Run: `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit tests/Feature/VoucherBusinessRulesTest.php`

Expected: voucher numbering, totals, validation, and stale-write tests remain green.

- [ ] **Step 7: Commit**

```bash
git add app/Events app/Actions/Vouchers/CreateVoucherAction.php app/Actions/Vouchers/UpdateVoucherAction.php app/Actions/Vouchers/CancelVoucherAction.php app/Http/Controllers/ProjectController.php app/Http/Controllers/Admin/ProjectController.php tests/Feature/BroadcastingTest.php
git commit -m "feat: broadcast committed voucher and project changes"
```

### Task 3: Refresh Inertia Screens and Show Realtime Connection State

**Files:**
- Create: `resources/js/useRealtimeUpdates.ts`
- Create: `resources/js/Components/RealtimeStatus.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/Pages/Vouchers/Index.vue`
- Modify: `resources/js/Pages/Vouchers/Form.vue`
- Modify: `resources/js/Pages/Vouchers/History.vue`
- Modify: `resources/js/Pages/Projects/Index.vue`

**Interfaces:**
- `useRealtimeUpdates` subscribes to `vouchers` and `projects`, exposes typed change callbacks and the current connection state, and removes its listeners when the owning component unmounts.
- `RealtimeStatus.vue` displays connection state in the authenticated layout without disabling ordinary page navigation or form submission.

- [x] **Step 1: Implement the typed realtime subscription helper**

Use the Echo singleton from Task 1. Define TypeScript payloads matching the exact PHP event payloads in Task 2. Ensure subscriptions are private, and clean up listeners/channels when the component unmounts.

- [x] **Step 2: Connect the voucher register and history pages**

On `Vouchers/Index.vue`, reload only `vouchers` after a voucher event, preserving the current query, filters, page, and scroll position.

On `Vouchers/History.vue`, reload only `voucher`, `revisions`, and `events` when the event's voucher ID matches the page voucher.

- [x] **Step 3: Handle events on an open editor without losing user input**

On `Vouchers/Form.vue`, react only to a newer version event for the currently edited voucher. Keep the existing `useForm` values, show the existing conflict/reload path, and do not silently replace the form. Reuse the server's `409` stale-version rejection if the user saves before reloading.

Refresh project options when a relevant project event arrives, but preserve voucher project-name snapshots and do not rewrite historical voucher data.

- [x] **Step 4: Refresh project administration data**

On `Projects/Index.vue`, reload `projects`, `projectOptions`, and `activeProjectCount` after a project event while preserving the current search and pagination.

- [x] **Step 5: Show connection state and recover after reconnect**

In `RealtimeStatus.vue`, expose connecting, connected, and reconnecting/unavailable states with accessible status text. On reconnection, reload the current page's relevant Inertia props. For an open editor, refresh server props and show the stale notice without resetting local form input.

- [x] Build: `npm run build` passed.

- [ ] Verify two-session browser behavior, including stale editor handling, project updates, unavailable Reverb behavior, and reconnect recovery.

Using two authenticated browser sessions, verify:
- A created, updated, or cancelled voucher appears on the register without a manual page refresh.
- A matching open editor retains unsaved input and offers reload; saving a stale version is still rejected.
- A project rename or merge updates active project choices while an existing voucher's historical project snapshot remains unchanged.
- Disconnecting Reverb does not block ordinary reads or saves; reconnecting fetches current authorized Inertia data.

- [ ] **Step 7: Commit**

```bash
git add resources/js/useRealtimeUpdates.ts resources/js/Components/RealtimeStatus.vue resources/js/Layouts/AuthenticatedLayout.vue resources/js/Pages/Vouchers resources/js/Pages/Projects/Index.vue
git commit -m "feat: refresh authorized screens from realtime events"
```

### Task 4: Configure Windows Reverb and Queue Startup

**Files:**
- Modify: `.env.example`
- Modify: `config/reverb.php`
- Modify: `IMPLEMENTATION_CHECKLIST.md`

**Interfaces:**
- Reverb server listens on the configured host/port; browser clients use the configured application host/port.
- Queue connection remains `database`; broadcast jobs are processed by a database queue worker.
- Windows Task Scheduler is the proposed no-extra-dependency supervisor for startup and restart-on-failure. Machine-specific paths and hostnames remain outside tracked files.

- [x] **Step 1: Configure safe environment and origin defaults**

Set Reverb host/port values through environment configuration. Restrict `allowed_origins` to the office application hostname; do not use `*`, commit secrets, expose MySQL, or use a hosted broadcaster.

- [ ] **Step 2: Configure startup and recovery tasks on the shared Windows host**

Create startup-triggered Task Scheduler tasks that run whether or not a user is logged in, with restart-on-failure and logs:

```powershell
php artisan reverb:start
php artisan queue:work database --sleep=3 --tries=3 --timeout=60
```

Use the host's configured PHP executable and application path. Keep the queue timeout below the database queue's `retry_after`. Restrict the Reverb port to office-LAN clients through Windows Firewall; do not expose the database port.

- [x] **Step 3: Verify automated checks**

Broadcasting tests passed (17/17), `npm run build`, Pint, and `git diff --check` passed. The full suite completed with 113/114 tests passing; the sole failure is the existing Stage 1 temporary-password redirect assertion (`/profile` expected, `/dashboard` received).

- [ ] **Step 4: Verify operational acceptance checks**

Verify both Task Scheduler tasks recover after a controlled stop, two browser sessions receive committed updates, and reads/saves continue while Reverb is unavailable.

- [x] **Step 5: Update the implementation checklist**

Distinguish code-level automated checks from live two-session and Windows process acceptance, which remain pending. Keep unrelated Stage 1 and database migration status accurate.

- [ ] **Step 6: Commit**

```bash
git add .env.example config/reverb.php IMPLEMENTATION_CHECKLIST.md
git commit -m "feat: configure realtime services"
```
