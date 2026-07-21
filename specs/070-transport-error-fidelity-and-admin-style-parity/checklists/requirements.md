# Specification Quality Checklist: Transport Error Fidelity & Hidden-Admin Style Parity

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-20 (retroactive — see Notes)
**Feature**: [spec.md](../spec.md)

## Content Quality

- [ ] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [ ] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [ ] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [ ] No implementation details leak into specification

## Notes

- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`

### Validation record

**Retroactive checklist.** Written 2026-07-20 after the implementation landed in `f9c5656`, as part
of the artifact backfill described in `plan.md` and `DECISIONS.md` #145. It is an honest assessment
of the spec as it exists, not a rubber stamp — four items are marked **failing**, and they are
failing for the same single reason.

**The four failures are one finding.** `spec.md` is written as a root-cause narrative for engineers,
not as a stakeholder specification. It names `WP_REST_Request::get_parameter_order()`,
`parseAndThrowError()`, `wp_common_block_scripts_and_styles()`, `wp.apiFetch({ parse: false })`,
`$wp_query->set_404()`, specific file paths, and specific line-level mechanics. Its measured outcome
is expressed in response bytes rather than in user-observable terms.

This is a deliberate deviation, not an oversight, and it is worth stating plainly rather than
scoring as a pass:

- Both defects were **owner-reported symptoms whose causes were not what the symptoms suggested**.
  The spec's primary job here was to record *why the obvious explanation was wrong* — 069 had already
  documented the hidden-admin gap as unreachable, and the Email Studio 404 pointed at a template that
  demonstrably existed. Stripping the core-source mechanics out would have destroyed the only durable
  value the document has.
- A stakeholder-facing rewrite would have to say "saving a template failed" and "a hidden admin page
  looked broken" — true, but insufficient to stop the same class of defect recurring.

**What should have happened instead:** the stakeholder-level statement belongs in `spec.md` and the
mechanics belong in `plan.md`, exactly as spec 069 split them. The backfilled `plan.md` now carries
the mechanics, but `spec.md` was not rewritten to match — retro-editing a shipped spec to make a
checklist pass would be dishonest about what was actually reviewed at implementation time.

**Carried forward, not resolved:** future correction specs should follow 069's split. This is
recorded so the pattern is visible, not silently repeated.

### docs-guard finding (2026-07-20)

The backfilled artifacts were run through `docs-guard` before delivery. Every referenced file path
(13), the `RouteParam::int`/`::string` counts (24 and 1 — verified by grep across the four
controllers), the `RouteParamTest` case count (5, including the literal 3859/3860 shadowing),
`draftFrom`/`emptyDraft`/`dropAdminContext`/`enqueueBlockStyles`/`viaApiFetch`/`fromResponse`/
`normalise`/`statusMessage`, the byte figures, `DECISIONS.md` #143/#144, and the in-place 069
correction all verified against source.

**One Rule 1 violation found, and it originated in `spec.md` itself:** the claim that
"`failureMessage()` surfaces `details.fields`". No such function exists in `corex-runtime.js`, and
the string `fields` appears nowhere in that file. The real mechanism is passthrough — `normalise()`
returns a valid envelope verbatim, so the fields were never transformed, only discarded wholesale
when the rejection path fell into the blanket catch.

The backfill had propagated this into `plan.md` and `tasks.md` T018 by trusting `spec.md` instead of
reading the file. All three are corrected, and `spec.md` is corrected **in place** with a note —
this is a false factual claim about a symbol, not the stylistic deviation §Validation record
declines to retro-edit, and 070 itself set the precedent for correcting a wrong spec in place.

### Requirement coverage

| Requirement | Covered by | Verified |
|---|---|---|
| FR-001 — route identity comes from path, never payload | T007–T014 | `tests/Integration/Http/RouteParamTest.php` (5 cases, incl. the exact 3859/3860 shadowing); template save returns 201 |
| FR-002 — a failed request says what actually failed | T015–T018 | `tests/corex-runtime.test.js` (4 rejection cases — every prior test mocked a resolve) |
| FR-003 — a hidden `/wp-admin` is styled, not merely routed | T019–T023 | `tests/Integration/Security/HiddenAdminResponseTest.php`; `tests/e2e/security-access.spec.js` size-within-5% assertion; measured 46,587 B → 79,711 B vs 79,964 B control |

### Open risks carried into 071

Not spec defects; recorded in `spec.md` §Out of scope and re-stated in `tasks.md`:

- The recurring `Mail rejected: Illegal characters in the subject field.` warning is a **live mail
  defect** and is directly adjacent to spec 071's scope. 071 should determine whether its own
  delivery-outcome work surfaces or fixes it, and must not silently absorb it.
- `WP_DEBUG_DISPLAY` is `true` in `wp/wp-config.php`.
- No POT is generated for `corex-core`; `corex-runtime.js` strings are unextractable through `t()`.
- Pre-existing PHP segfault partway through the unit suite, identical with and without 070's work.
