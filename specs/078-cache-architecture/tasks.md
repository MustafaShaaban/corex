---

description: "Task list for Spec 078 — Cache Architecture and Performance Management"
---

# Tasks: Cache Architecture and Performance Management

**Input**: [spec.md](spec.md), [plan.md](plan.md)

**Tests**: REQUIRED, per the constitution's Definition of Done.

**Ticking rule**: a box is ticked against a verified artifact — a passing test, a captured
screenshot, a command whose output was read — never against "I wrote the code".

---

## Phase 1: The registry, and the guarantee it exists for

**Purpose**: make it structurally impossible for any clear path to remove security state. Everything
else in this spec asks the registry, so the registry has to be right first.

- [ ] T001 `CacheClassification` — safe cache, security state, pending operation, record,
      configuration, metadata — with `mayBeClearedRoutinely()` as the single answer (FR-002).
- [ ] T002 `CacheEntry` — key or prefix, owner, classification, lifetime, invalidation path.
- [ ] T003 `CacheRegistry` declaring the eight entries that exist today, taken from
      `evidence/before/cache-inventory.md`, and `clearable( $scope )` returning only those a scope
      may touch (FR-001/003).
- [ ] T004 [P] Pest: `corex_throttle_*` and `corex_captcha_seen_*` are **never** returned as
      clearable, at any scope. This is the test the spec exists for — write it before the clear path.
- [ ] T005 [P] Pest: every declared entry has an owner, a classification and a lifetime; a new entry
      cannot be added without them.

---

## Phase 2: The store

- [ ] T006 `CacheStore` contract: get, put, has, forget, remember, forgetNamespace, isPersistent.
- [ ] T007 `WordPressCacheStore` — object cache when WordPress is using one, transients otherwise.
      Both are WordPress mechanisms; shared hosting is the normal path, not a fallback (FR-007).
- [ ] T008 `ArrayCacheStore` for deterministic tests.
- [ ] T009 `CacheManager` over the store and the registry, with namespaced keys (FR-008).
- [ ] T010 [P] Pest: reads, writes, expiry, namespace invalidation, and `remember()` regenerating
      once under concurrent misses.
- [ ] T011 [P] Pest: a failing store degrades to a miss and never throws (FR-009).

---

## Phase 3: Scoped clearing that reports what it did

- [ ] T012 `CacheScope` allow-list and `CacheOutcome` (cleared / skipped / unsupported / failed,
      each with a reason).
- [ ] T013 `CacheManager::clear( $scope )` walking declared entries — **never a pattern delete**, so
      no future scope can widen into security state (plan §1).
- [ ] T014 [P] Pest: the default scope clears both safe-cache entries and leaves the other six.
- [ ] T015 [P] Pest: **after every scope in turn**, a throttle counter and a spent-captcha token are
      still present and still effective (FR-003, SC-002).
- [ ] T016 [P] Pest: no scope removes a submission, audit entry, notification, access request or
      history row (FR-003, SC-003).
- [ ] T017 The `object` scope requires explicit opt-in, because `wp_cache_flush()` hits every other
      plugin on the site (FR-011).

---

## Phase 4: The command line

- [ ] T018 `wp corex cache:status` — every layer and its real state.
- [ ] T019 `wp corex cache:doctor` — secret-free diagnostics (FR-019).
- [ ] T020 `wp corex cache:clear [--scope=] [--yes]` replacing the four-line command that deleted
      one transient, reporting cleared/skipped/unsupported/failed with meaningful exit codes
      (FR-010/012/013).
- [ ] T021 [P] Pest: an unsupported scope is refused, not silently ignored; the report names each
      outcome; nothing claims to clear a browser cache (FR-014).

---

## Phase 5: Status from real checks

- [ ] T022 `ObjectCacheProbe` — asks whether **WordPress** is using a persistent object cache, not
      whether a Redis container exists (FR-017).
- [ ] T023 `OpcacheProbe` — truthful, and `unknown` rather than `off` when the host disables
      inspection. "Off" for "not allowed to look" is a confident wrong answer (FR-021).
- [ ] T024 `PageCacheProbe` and `CdnProbe` — detection only, Null providers reporting `unsupported`
      with a reason (FR-022/023).
- [ ] T025 `CacheStatusReport` assembling all seven layers.
- [ ] T026 [P] Pest: a present-but-unused object cache reads as available, never active; a disabled
      OPcache inspection reads as unknown and does not fail.

---

## Phase 6: The admin section, and the 077 extraction

- [ ] T027 Move the five existing section renderers into `Sections/` — a pure move, no markup or
      behaviour change. `OperationsSectionsTest` must pass **untouched**, which is what proves it.
- [ ] T028 `CacheSection` — seven layers with purpose, state, provider, whether CoreX can manage it,
      whether clearing is safe, when it was last checked, and a plain-language explanation (FR-016).
- [ ] T029 The browser layer states plainly that CoreX cannot clear a visitor's cache, beside the
      asset versioning that actually solves what people come to it for.
- [ ] T030 `CacheController` — nonce, capability, allow-listed scope, confirmation for broad scopes,
      real result, audit entry with actor/time/scope/environment/provider/outcome (FR-018).
- [ ] T031 [P] Pest integration: the route refuses without capability, without nonce, and with an
      unknown scope.
- [ ] T032 [P] Pest: the diagnostics payload contains no password, token, key, salt or cached value
      (FR-019, SC-008).

---

## Phase 7: Acceptance and closeout

- [ ] T033 Playwright: the section renders every layer with a real state; disabled actions state
      their reason; no action is offered without a provider.
- [ ] T034 Playwright: RTL, 375px, 200% zoom, light and dark, no overflow beyond stock wp-admin's
      own (DECISIONS #163), no console error.
- [ ] T035 Capture `evidence/after/`.
- [ ] T036 Guards: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- [ ] T037 Documentation: the cache contract, classifications, namespaces, invalidation, shared-host
      behaviour, the optional Redis profile, OPcache, page-cache and CDN providers, the admin
      actions, and the CLI — including the plain statements that CoreX cannot erase a visitor's
      browser cache, that a global object flush affects other plugins, and that historical records
      are not cache.
- [ ] T038 Full gate: `lint:css`, `lint:js`, Jest, Pest unit, Pest integration, token inventory,
      Playwright.
- [ ] T039 `PROGRESS.md` + `DECISIONS.md`.
- [ ] T040 PR, green CI, merge, delete branch.

---

## Dependencies

- Phase 1 blocks everything. T004 is written before T013 deliberately: the guarantee is defined
  before the code that could violate it.
- Phase 3 needs Phases 1–2. Phase 6 needs Phases 1–5.

## Out of scope, deliberately

- A page-cache engine, a CDN client, a Redis client. Detection and provider seams only.
- Reusing the Insights Cloudflare credential for purging (FR-023 forbids it — it is the tempting
  shortcut, which is why it is named).
- Any control whose provider is absent. Those render disabled with the reason.
