# Feature Specification: Cache Architecture and Performance Management

**Feature Branch**: `spec/078-cache-architecture`

**Created**: 2026-07-27

**Status**: Draft

**Input**: User description: "Implement a professional cache architecture with safe admin management — a formal cache abstraction, namespaces and deliberate invalidation, a truthful scope-aware CLI, a Cache & Performance admin section with real provider states, secret-free diagnostics, an optional Redis development profile, honest OPcache reporting, and page-cache/CDN provider seams."

## Why this spec exists

CoreX caches things. It has no cache architecture, and the one command that claims to manage caching
manages one key.

Read from the source before writing this (`evidence/before/cache-inventory.md`):

```php
WP_CLI::add_command( 'corex cache:clear', static function (): void {
    delete_transient( 'corex_asset_manifest' );
    WP_CLI::success( 'Corex asset cache cleared.' );
} );
```

Meanwhile thirteen cache call sites live across seven files. The command touches one of them.

**And the obvious fix is dangerous.** "Clear CoreX's caches" reads as a sweep of `corex_*`
transients. On this codebase that sweep would:

- **delete every rate-limit counter** — `ThrottleMiddleware` stores request counts as
  `corex_throttle_<md5>` transients, so the sweep resets brute-force protection at exactly the
  moment an operator is most likely to press it: while something is going wrong;
- **delete every spent-captcha record** — `TokenReplayGuard` marks used tokens as
  `corex_captcha_seen_<hmac>`, so the sweep re-opens the replay window that guard exists to close;
- **invalidate every pending confirmation** — four preview stores hold data changes, imports,
  migrations and bulk actions an operator is part-way through confirming.

Three of the eight kinds of cached value are safe to clear. Two are security controls implemented as
transients. Three are work in progress. **Classifying them is not bookkeeping — it is what stops a
cache button becoming a security hole**, and it is why this spec leads with classification rather
than with a cache interface.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An operator clears a cache and knows what they cleared (Priority: P1)

An operator whose site is serving something stale can clear the right thing, from the admin or the
command line, and is told exactly what was cleared, what was skipped, and what CoreX cannot reach.

**Why this priority**: it is the reason someone comes to this feature, and today it is a command
whose name promises far more than it does.

**Independent Test**: run the clear command, confirm it names what it cleared, and confirm rate
limiting and captcha replay protection are still in force afterwards.

**Acceptance Scenarios**:

1. **Given** a cache clear is requested, **When** it completes, **Then** the operator is told what
   was cleared, what was skipped and why, and what is not supported — never a bare "success".
2. **Given** a clear of CoreX's own caches, **When** it completes, **Then** rate-limit counters and
   spent-captcha records are **untouched**, and a request that was throttled before is still
   throttled.
3. **Given** an operation CoreX cannot perform — no persistent object cache, no page cache, no CDN
   configured — **When** it is requested, **Then** it says so plainly and does not report success.
4. **Given** a broad or destructive scope on a live site, **When** it is requested, **Then** it is
   confirmed first and recorded afterwards.

---

### User Story 2 - An operator can see what is actually caching (Priority: P1)

An operator can look at one place and learn which caching layers exist on this site, which are
active, which are available but unconfigured, and which CoreX can do anything about.

**Why this priority**: equal to US1, because a clear button with no state behind it is guesswork.
There is no cache status surface at all today.

**Independent Test**: open the Cache & Performance section on a site with no persistent object cache
and confirm it says so, rather than showing an empty panel or implying Redis is running.

**Acceptance Scenarios**:

1. **Given** each caching layer, **When** the operator reads it, **Then** they see its purpose, its
   real state, its provider, whether CoreX can manage it, whether clearing is safe, and when it was
   last checked.
2. **Given** a Redis container exists but WordPress is not using it, **When** the state is shown,
   **Then** it is *not* reported as active. Presence is not use.
3. **Given** OPcache inspection functions are disabled by the host, **When** the panel renders,
   **Then** it says the state is unknown and does not fail.
4. **Given** no page cache and no CDN, **When** the panel renders, **Then** both say "not detected"
   and their actions are unavailable with the reason stated.

---

### User Story 3 - Cached values are classified, and the dangerous ones are protected (Priority: P1)

Every value CoreX caches has a declared owner, lifetime, invalidation path and sensitivity — and
nothing that is security state or a business record can be removed by a cache action.

**Why this priority**: P1, not lower, because it is what makes US1 safe. Without it, US1's clear
button is the security hole described above.

**Independent Test**: run every clear scope in turn and confirm the throttle and captcha keys survive
all of them, and that no submission, audit entry, notification or access request is touched.

**Acceptance Scenarios**:

1. **Given** any cache-clearing operation at any scope, **When** it runs, **Then** rate-limit and
   captcha-replay state survive it.
2. **Given** any cache-clearing operation, **When** it runs, **Then** no submission, audit log entry,
   notification, access request, email delivery record, editorial history or mode-change history is
   removed. These are records, not cache.
3. **Given** a pending confirmation token, **When** a routine cache clear runs, **Then** the operator
   is either warned that in-flight confirmations will be invalidated, or those tokens are left alone.
4. **Given** a new cached value is added anywhere in CoreX, **When** it is reviewed, **Then** it
   declares owner, namespace, lifetime, invalidation and sensitivity.

---

### User Story 4 - Caching works without any of it (Priority: P2)

CoreX runs correctly on shared hosting with no Redis, no Memcached, no Docker, no CDN and no page
cache, and says so honestly rather than degrading silently.

**Why this priority**: the constitution's Principle IX applied to this feature, and shared hosting is
the target the framework exists to serve well.

**Independent Test**: with no persistent object cache installed, exercise every cache path and
confirm correct behaviour and truthful reporting.

**Acceptance Scenarios**:

1. **Given** no persistent object cache, **When** CoreX caches and reads values, **Then** everything
   works through WordPress's own mechanisms.
2. **Given** a configured Redis that is unreachable, **When** CoreX uses the cache, **Then** it
   degrades to working behaviour and reports the failure rather than throwing.
3. **Given** shared hosting, **When** the admin section renders, **Then** nothing suggests installing
   infrastructure is required.

---

### Edge Cases

- **A cache clear while another is running**, or two operators clearing at once.
- **A provider that reports success but did not purge** — a CDN accepting the request and doing
  nothing.
- **Redis reachable at connect time and gone by write time.**
- **`wp_cache_flush()` on a shared object cache**, which affects every other plugin on the site.
- **An expired-transient cleanup on a very large options table.**
- **OPcache reset on a live site**, which briefly slows every request.
- **A cache key colliding with another plugin's**, which is what namespacing prevents.
- **A value cached before an upgrade and read after it**, in a shape the new code does not expect.

## Requirements *(mandatory)*

### Functional Requirements

**Classification — first, because it makes the rest safe**

- **FR-001**: Every value CoreX caches MUST declare its owner, namespace, lifetime, invalidation path
  and sensitivity.
- **FR-002**: Values MUST be classified as safe cache, temporary security state, pending operation,
  historical record, configuration, or generated metadata. The classification MUST be expressed in
  code, not documentation alone.
- **FR-003**: No cache-clearing operation at any scope may remove temporary security state
  (rate-limit counters, spent-captcha records) or any historical record (submissions, audit entries,
  notifications, access requests, email delivery records, editorial history, mode-change history).
- **FR-004**: Pending operation tokens MUST either be excluded from routine clears, or the operator
  MUST be told that in-flight confirmations will be invalidated.
- **FR-005**: Secrets, credentials, passwords, nonces, submission bodies and raw authentication data
  MUST NOT be cached.

**The cache contract**

- **FR-006**: A cache abstraction MUST exist offering read, write, existence, removal, read-through,
  expiry, namespace invalidation, protection against concurrent regeneration, provider status, and
  explicit failure behaviour.
- **FR-007**: It MUST work on shared hosting with no persistent object cache, and MUST take advantage
  of one when present.
- **FR-008**: Keys MUST be namespaced so they cannot collide with another plugin's.
- **FR-009**: A cache failure MUST degrade to correct behaviour, never to an error and never to stale
  data presented as fresh.

**The command line**

- **FR-010**: The CLI MUST offer status, diagnosis, and scope-aware clearing. `cache:clear` with no
  scope MUST affect only CoreX-owned safe cache.
- **FR-011**: Scopes MUST be real and refused when unsupported. A global object-cache flush MUST
  require explicit opt-in, because it affects every other plugin on the site.
- **FR-012**: Every command MUST report what was cleared, skipped, unsupported and failed, and use
  meaningful exit codes.
- **FR-013**: Broad or production-affecting operations MUST require confirmation or an explicit flag,
  and MUST be recorded.
- **FR-014**: No command may claim to clear a visitor's browser cache or to purge a CDN that is not
  configured.

**The admin section**

- **FR-015**: A Cache & Performance section MUST be added to the Operations & Security screen, using
  the section architecture spec 077 established.
- **FR-016**: Each caching layer MUST be shown separately — browser/static assets, OPcache, the
  request object cache, a persistent object cache, CoreX application cache, page cache, CDN — with
  purpose, state, provider, whether CoreX can manage it, whether clearing is safe, when it was last
  checked, an action where one is real, and a plain-language explanation.
- **FR-017**: States MUST distinguish active, available but unconfigured, not detected, unsupported,
  degraded, error and unknown. A present Redis container that WordPress is not using MUST NOT be
  reported as active.
- **FR-018**: Actions MUST be enabled only when supported, explain why when disabled, require the
  capability and nonce, confirm broad operations, warn on production, report the real result, and be
  audited with actor, time, scope, environment, provider and outcome.
- **FR-019**: Diagnostics MUST be secret-free: no passwords, tokens, cached values, private keys,
  production stack traces or full sensitive paths.

**Infrastructure**

- **FR-020**: Redis MUST remain optional. An honest Docker development profile MAY be provided; it
  MUST NOT be required for shared hosting, WAMP, cPanel or default production packages, and CoreX
  MUST NOT install cache plugins on a production site.
- **FR-021**: OPcache MUST be reported truthfully — enabled, memory, hit rate, revalidation,
  restart-pending where available — and MUST not break when inspection is disabled. No routine
  production reset button; reset belongs to deployment or explicit CLI.
- **FR-022**: CoreX MUST NOT implement a page cache. Detection, a provider seam and safe purge
  integration only.
- **FR-023**: A CDN purge MUST NOT reuse a credential granted for another purpose unless it has purge
  permission, cache use is configured, and the operator has been told.
- **FR-024**: Lightweight observability MUST be available — last health check, last operation, last
  failure, provider state — without heavy telemetry on production requests.

### Key Entities

- **Cached value**: something CoreX stores for speed, with an owner, a namespace, a lifetime, an
  invalidation path and a sensitivity.
- **Cache classification**: what a stored value *is* — safe cache, security state, pending operation,
  record, configuration, metadata. Decides what may remove it.
- **Cache layer**: browser, OPcache, request, persistent object, application, page, CDN. Each with
  its own state and its own answer to "can CoreX do anything about this".
- **Provider**: the concrete thing behind a layer — WordPress transients, Redis, a page-cache plugin,
  a CDN. Present, absent, or present-but-unused.
- **Cache operation**: a scoped, capability-gated, audited action with a truthful result.

## Success Criteria *(mandatory)*

- **SC-001**: `wp corex cache:clear` clears every CoreX-owned safe cache. It currently clears one key
  of eight kinds.
- **SC-002**: No cache operation at any scope removes rate-limit or captcha-replay state. Both are
  currently `corex_*` transients that any plausible sweep would delete.
- **SC-003**: No cache operation removes any business record.
- **SC-004**: An operator can name every caching layer on their site and its state, from one screen.
  There is currently no such screen.
- **SC-005**: Every state shown is derived from a real check. A Redis container WordPress is not
  using never reads as active.
- **SC-006**: Every action either performs a real operation or is disabled with a stated reason.
- **SC-007**: CoreX works correctly with no persistent object cache, no Redis, no page cache and no
  CDN — verified, not assumed.
- **SC-008**: Diagnostics contain no secret, verified by test.
- **SC-009**: Every production-affecting cache operation is confirmed and audited.
- **SC-010**: The acceptance matrix — RTL, 375px, 200% zoom, light and dark, keyboard — passes with no
  horizontal overflow beyond stock wp-admin's own and no console error.

## Assumptions

- **The Cache & Performance section joins the spec 077 architecture** rather than inventing another.
  077 deliberately left `cache` out of its section list so this spec adds a section with something in
  it, instead of a heading that promises management and delivers nothing.
- **This spec also extracts the five section renderers into their own files**, which 077 deferred
  precisely so the move could happen in a diff containing nothing else.
- **No page-cache engine is written.** Detection and a provider seam only.
- **Redis stays a development convenience.** The default answer for shared hosting is "you do not
  need it", and the product must be excellent without it.
- **"Cache" excludes records.** Submissions, audit entries, notifications, access requests, email
  delivery records and history are data. That they are sometimes stored in fast places does not make
  them disposable.
- **CoreX cannot clear a visitor's browser cache**, and no wording in this feature may imply it can.
  Asset versioning is what makes browsers fetch updated files.
