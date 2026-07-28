# Specification Quality Checklist: Files, End to End

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-28
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
- [x] Success criteria are technology-agnostic
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

**This is one feature, not four bugs, and the issue is what makes that clear.** Its closing note is
the single most useful sentence in the report: *"Any one of them alone leaves a workable path;
together they force a fully parallel implementation outside the framework."* Split across four PRs,
each looks like a small enhancement that could be deferred; together they are the difference between
a site being buildable on CoreX and not.

**Every claim was verified in the tree**, not taken from the report:

- `FieldTypeRegistry::builtIns()` seeds 16 types — `text`, `email`, `phone`, `number`, `textarea`,
  `select`, `multi-select`, `radio`, `checkbox`, `date`, `time`, `url`, `hidden`, `consent`,
  `rating`, `step`. No file.
- `SubmitController::payload()` reads `get_json_params()` then `get_body_params()`. `$_FILES` appears
  nowhere in it.
- `sanitizeShape()` has exactly three branches: `email`, `textarea`, default.
- `FieldRenderer::INPUT_TYPES` **does** contain `'file'`, and it is unreachable.
- `ApplicationService::apply(int $jobId, array $data, array $cvFile, int $cvAttachmentId = 0)` — the
  provider passes the sanitised `$_FILES['cv']` descriptor as `$cvFile` and never supplies
  `$cvAttachmentId`, so it stays `0`. The careers add-on contains no `wp_handle_upload`.
- The careers applications table is not registered as a `ManagedTable`.

**FR-015 is the requirement most likely to be quietly dropped**, and it is called out in Assumptions
for that reason. A CV is personal data; the media library is world-readable by default; and the
honest fixes — a protected directory, signed URLs, an authenticated delivery route — are each a real
design decision with real cost. It should be settled in the plan, not discovered halfway through
implementation. Storing CVs in the public uploads directory and calling the story done would satisfy
every other requirement here while being the worst outcome in the document.

**Two documentation lies are in scope on purpose.** `FieldRenderer::INPUT_TYPES` listing `'file'`
and `COREX-EMAIL-ADDON.md` documenting `attach()` / `attachMedia()` / `attachGenerated()` /
`AttachmentResolver` both describe capability that does not exist. They cost a day each to the next
person who believes them, and removing a lie is cheaper than any of the code here.

**Deliberately unresolved, for the plan**: whether multi-file fields are supported at all, and what
happens to a file when its submission is deleted under the existing retention policy. Both are named
in the spec rather than assumed, because guessing either would put a wrong answer in the code.
