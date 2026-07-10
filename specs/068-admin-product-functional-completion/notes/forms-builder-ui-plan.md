# Forms & Flows Builder UI Implementation Plan

> **For agentic workers:** Execute inline in the active normal-root checkout. Subagents and worktrees are not
> authorized. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the read-only Forms inventory with a functional, accessible, REST-backed flow list and editor.

**Architecture:** The existing Config production bundle imports a small Forms composition root. Pure state helpers
remain Jest-testable; one hook owns REST effects; focused list/editor/tab components consume normalized state. The PHP
screen only localizes REST configuration and mounts the client.

**Tech Stack:** WordPress 7.0+, PHP 8.3, `@wordpress/element`, `@wordpress/i18n`, shared `window.Corex.api`, Jest,
Pest, Playwright, token-only logical CSS.

---

### Task 1: Pure builder state (T077)

**Files:**
- Create: `plugins/corex-config/src/forms/flowEditor.js`
- Create: `plugins/corex-config/src/forms/__tests__/flowEditor.test.js`

- [ ] Write failing reducer tests for load/error/selection/draft mutation, field add/edit/remove/reorder, stage status,
  and optimistic endpoint construction.
- [ ] Run `npm.cmd run test:js -- --runInBand plugins/corex-config/src/forms/__tests__/flowEditor.test.js`; expect
  failures for missing exports.
- [ ] Implement these public signatures:

```js
export const initialFlowState = { status: 'idle', flows: [], selected: null, draft: null, extensions: null, message: '' };
export function flowReducer( state, action ) {}
export function normalizeFlowPayload( payload ) {}
export function addField( draft, type ) {}
export function updateField( draft, uuid, changes ) {}
export function removeField( draft, uuid ) {}
export function moveField( draft, uuid, direction ) {}
export function stageStatus( draft ) {}
export function flowEndpoint( base, id = '', action = '' ) {}
```

- [ ] Re-run the focused Jest file; expect all assertions green.

### Task 2: REST hook and list (T078–T079)

**Files:**
- Create: `plugins/corex-config/src/forms/useFlows.js`
- Create: `plugins/corex-config/src/forms/FlowList.js`
- Create: `plugins/corex-config/src/forms/index.js`
- Modify: `plugins/corex-config/src/admin/index.js`

- [ ] Add client tests proving search/filter query generation, create selection, lifecycle mutation messages, and
  409 conflict preservation.
- [ ] Implement `useFlows(config)` with `load`, `create`, `select`, `saveDraft`, `publish`, `unpublish`, `close`, and
  `preview` commands over `window.Corex.api`.
- [ ] Implement `FlowList` with labelled search/state controls, truthful loading/empty/error states, a working New
  Flow form, and rows showing state, owner/placement, field count, and updated time.
- [ ] Import `../forms/index.js` from the existing Config entrypoint and keep mounting conditional on the Forms root.

### Task 3: Editor composition and fields (T080)

**Files:**
- Create: `plugins/corex-config/src/forms/FlowEditor.js`
- Create: `plugins/corex-config/src/forms/StageRail.js`
- Create: `plugins/corex-config/src/forms/tabs/FormTab.js`
- Create: `plugins/corex-config/src/forms/components/FieldSettings.js`

- [ ] Add component contract assertions for explicit labels, stable UUID keys, add/edit/remove/reorder controls,
  fallback-safe button types, active stage semantics, and conflict/error announcements.
- [ ] Implement the pipeline rail and Form tab. Field type choices come only from `/flows/extensions`; field settings
  persist label, key, type, placeholder, help text, default, required, options, rules, width, step, and personal-data
  class.
- [ ] Keep the editor toolbar wired to real save/publish/unpublish/close/preview commands and disable actions only
  while the corresponding request is active or a declared lifecycle rule forbids them.

### Task 4: Remaining configuration tabs (T081)

**Files:**
- Create: `plugins/corex-config/src/forms/tabs/ValidationTab.js`
- Create: `plugins/corex-config/src/forms/tabs/RoutingTab.js`
- Create: `plugins/corex-config/src/forms/tabs/EmailsTab.js`
- Create: `plugins/corex-config/src/forms/tabs/SuccessTab.js`
- Create: `plugins/corex-config/src/forms/tabs/PreviewTab.js`
- Create: `plugins/corex-config/src/forms/tabs/TestTab.js`

- [ ] Add tests for registered validation choices, numbered routing order, undeletable fallback, template/recipient/
  reply-to mappings, supported success definitions, visitor preview, and marked-test result rendering.
- [ ] Implement each tab against the versioned `configuration` document and extension catalog. TestTab calls the
  full marked-test endpoint and renders stage-by-stage outcomes; it must not fabricate a successful result.

### Task 5: PHP mount and responsive styling (T078, T082)

**Files:**
- Replace: `plugins/corex-config/src/Forms/FormsFlowsScreen.php`
- Replace: `plugins/corex-config/assets/forms-admin.css`
- Create: `plugins/corex-config/assets/forms-admin.scss`

- [ ] Add a PHP contract test proving conditional Config-bundle enqueue, localized `/corex/v1/flows` URL/nonce, the
  functional mount, shared runtime dependency, translations, and removal of read-only/future copy.
- [ ] Replace the PHP renderer with the AdminGuard-protected shell and `#corex-forms-flows-app` mount.
- [ ] Implement token-only logical CSS for master-detail desktop, stacked mobile, labelled overflow rails, focus,
  disabled/loading/error/success states, RTL, and reduced motion.
- [ ] Run scoped Jest, PHP contract tests, JS/CSS lint, Config build, and `git diff --check`; all must pass before
  T083 pipeline work begins.

## Plan self-review

- Coverage: T077–T082 and FR-027–FR-037 administrative behavior are mapped; T083–T091 retain pipeline, blocks,
  browser proof, docs, and final guard responsibility.
- Type consistency: reducer, hook, endpoint, mount, and component names are identical across tasks.
- Scope: no client-site work, theme business logic, new framework, or unapproved dependency is introduced.
