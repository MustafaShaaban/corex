# Specification Quality Checklist: Cache Architecture and Performance Management

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-27
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

**The reconnaissance changed the order of this spec, and that is its most important property.**

The brief leads with the cache abstraction — stores, namespaces, providers — and treats
classification as a step within it. Reading the actual cache call sites first inverted that.
CoreX has thirteen cached values across seven files, all prefixed `corex_`, and **two of them are
security controls**: `ThrottleMiddleware` keeps rate-limit counters in `corex_throttle_*` transients,
and `TokenReplayGuard` keeps spent captcha tokens in `corex_captcha_seen_*`.

The obvious implementation of "clear CoreX's caches" is a `corex_*` sweep. On this codebase that
sweep resets brute-force protection and re-opens the captcha replay window — at precisely the moment
an operator is most likely to run it, because something is already going wrong. Three more entries
are pending confirmation tokens for imports, migrations and bulk actions.

So classification is **US3 at P1**, not a sub-task of the architecture, and FR-003 is written as an
absolute rather than as guidance. The full inventory is in `evidence/before/cache-inventory.md` with
the file and constant for each entry, so the claim can be checked rather than believed.

**Baselines are counted, not adjectival.** SC-001 says the command clears one key of eight kinds;
SC-002 names the two transient families that must survive every scope. Both are read from source.

**Two scope decisions worth the owner's eye**, both recorded in Assumptions:

- The Cache & Performance section joins the section architecture spec 077 built. 077 deliberately
  omitted a `cache` tab so this spec could add one with content, rather than shipping a heading that
  promised management and delivered nothing.
- This spec also performs the `Sections/` extraction 077 deferred — 077 chose to defer precisely so
  the move could land in a diff containing nothing but the move.

**Deliberately not specified**: a page-cache engine (detection and a provider seam only), and any
requirement that Redis exist. Shared hosting without Redis is the default target, not the degraded
one.
