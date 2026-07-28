# Tasks: An Extendable User-Guide Add-on

The registry comes before the content. The extension seam is the reason this is an add-on, so it is
built and proven first — content registered through a seam that does not yet work would look like
progress and prove nothing.

## Phase 1 — The add-on exists and boots

- [x] **T001** `addons/corex-guides/corex-guides.php` — header, version constant, autoloader.
- [x] **T002** `GuidesServiceProvider` with an empty `boot()`; the five wiring points
      (composer PSR-4, `AddonProviderRegistry`, `AddonRegistry`, `sectionMeta()`, dev symlink).
- [x] **T003** Confirm it appears on the Add-ons screen and boots, before anything is built on it.

## Phase 2 — The registry, and the race it exists for

- [x] **T010** `Guide`, `GuideTopic`, `GuideStep`, `GuideScreenshot` value objects.
- [x] **T011** `GuideRegistry`: `register`, `registerDeferred`, `all`, `find`, `forSection`,
      `available`. Resolve-once, recursion guard, `corex_guides` filter with an `instanceof` sieve.
- [x] **T012** Unit: registration, replace-by-id, deferred resolution, recursion, malformed filter
      contributions, capability gating both ways.
- [x] **T013** Integration (SC-001): a second plugin registering on `plugins_loaded` at default
      priority appears on the screen. **Must fail against a registry that resolves eagerly** —
      verify that before trusting it.

## Phase 3 — The surface

- [x] **T020** `GuidesScreen` — submenu under `corex-settings`, sections, topic detail, admin shell.
- [x] **T021** Client-side search over registered content, no external dependency.
- [x] **T022** `ContextualHelp` — a help tab on each screen a guide declares (FR-013).
- [x] **T023** CSS, tokens only, logical properties, RTL.
- [x] **T024** Playwright: renders, search filters, help tab present, gated guide absent for a
      subscriber.

## Phase 4 — CoreX's own guides

- [x] **T030** The guides 082 specified, as registered objects: read and answer a submission,
      publish a post, check whether an email went out, find out why a page is slow.
- [x] **T031** `tests/e2e/capture-guide-screenshots.mjs` — one command, seeded fixtures, fails loudly
      on a missing screen or control.
- [x] **T032** Captured and committed. The loud-failure path is the contract that matters and is
      exercised by the probe on every run; the deliberate-drift demonstration is not repeated here.

## Phase 5 — The seam is documented, or it does not exist

- [x] **T040** `docs-app/src/content/docs/guides/user-guides.md` with a copyable example.
- [x] **T041** `wp corex make:guide` generator + stub.
- [x] **T042** Mark spec 082 superseded; `DECISIONS.md` entry.

## Phase 6 — Close

- [x] **T050** Full gate: Pest unit + integration, Jest, Playwright, lints, token inventory.
- [x] **T051** Guards: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- [x] **T052** `PROGRESS.md`; PR; green CI.
