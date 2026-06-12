# Data Model: Unified Kit Activation

No new tables. New in-memory value shapes (corex-core) + option-backed activation state.

## KitSummary (VO, corex-core)

| Field | Type | Description |
|---|---|---|
| `name` | `string` | Kit identifier (e.g. `company`). |
| `label` | `string` | Display label. |
| `applied` | `bool` | Apply has run at least once for this kit. |
| `pending` | `bool` | Enabled but not yet applied or dismissed. |
| `pageCount` | `int` | Number of declared pages. |
| `requiredModules` | `list<string>` | Modules the kit needs. |

## ApplyPreview (VO, corex-core) — read-only

| Field | Type | Description |
|---|---|---|
| `kit` | `string` | Kit name. |
| `pages` | `list<PageDisposition>` | Per declared page: create / adopt / skip (spec 041 classifier, no writes). |
| `frontTargetSlug` | `string\|null` | Slug that will become the front page (the declared `front` page). |
| `modules` | `list<string>` | Modules that will be activated. |
| `flags` | `list<string>` | Feature flags that will be enabled. |

`PageDisposition` and `ApplyOutcome` are the spec-041 corex-core value objects, reused unchanged.

## Activation state (options; extends spec 031 tracking)

| Option | Shape | Meaning |
|---|---|---|
| `corex_kit_pending` | `list<string>` | Kit names enabled but not yet applied/dismissed (drives the prompt). |
| `corex_kit_applied` | `list<string>` | Kit names that have been applied at least once (drives "applied ✓" + the card). |
| `corex_kit_seeded_pages` | `list<int>` | Unchanged (spec 031/041) — the index of kit-touched pages. |

State transitions per kit:

```
disabled ──enable──► pending (prompt shown)
pending  ──Apply───► applied      (+ pages seeded via shared activator, outcome recorded)
pending  ──Not now─► (cleared)    (no content change; recallable from the kit row)
applied  ──Re-apply► applied      (idempotent; spec 041 rules)
applied  ──disable─► applied flag cleared from active state; pages NOT deleted (reset path owns deletion)
```

## SiteStatus (dashboard card view model)

| Field | Type | Description |
|---|---|---|
| `appliedKits` | `list<string>` | Labels of applied kits (empty when none / provisioner unavailable). |
| `submissionCount` | `int` | Live count of contact submissions (0 when forms inactive). |
| `submissionsUrl` | `string` | Link to Corex → Data. |
| `frontPage` | `corex_page` \| `blank` \| `blog_index` | Current front-page status. |
| `isEmptyState` | `bool` | True when nothing is applied and there are no submissions → render the actionable empty state. |

## Validation / invariants

- Preview makes zero writes; its dispositions equal what a subsequent Apply produces for the same site state.
- Apply routes through the one shared `BlueprintActivator` (the wizard and the prompt yield the same `ApplyOutcome` shape).
- The card never errors: missing provisioner → no applied kits + empty state; inactive forms → count 0.
- Apply/Dismiss require capability + valid nonce; otherwise no state change.
