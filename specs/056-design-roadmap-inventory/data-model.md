# Planning Data Model: Design Roadmap and Inventory Integration

This feature stores no runtime data. The entities below define the information contract for version-controlled planning documents.

## Engineering Milestone

Represents a durable product or engineering outcome.

| Field | Required | Rules |
|---|---|---|
| Identifier | Yes | Unique `M0` through `M11` in the current roadmap. |
| Name | Yes | Outcome-oriented and stable enough for owner-level planning. |
| Status | Yes | Plain-language current state; must distinguish active, planned, waiting, or future work. |
| Priority | Yes | High, medium-high, medium, or low/future as appropriate to engineering sequencing. |
| Outcome | Yes | Describes the user or product result, not a list of historical specs. |
| Dependencies | Yes | Names prerequisite milestones, approvals, or external/environment gates. |
| Scope | Yes | Bounded capability list. |
| Deferrals | When applicable | Prevents adjacent future work from entering the milestone implicitly. |

**Relationship**: A milestone may depend on several milestones and may yield several engineering spec candidates over time.

## Design Inventory Item

Represents one design area, screen family, or component family under review.

| Field | Required | Rules |
|---|---|---|
| Area | Yes | Stable design domain such as navigation, company kit, or forms/email. |
| Screen/component | Yes | Human-readable coverage summary. |
| Status | Yes | Exactly `approved`, `needs revision`, `missing`, or `future`. |
| Priority | Yes | Exactly `high`, `medium`, or `low`. |
| Handoff | Yes | `-` until a focused handoff exists; otherwise a relative reference. |
| Notes | Yes | Missing coverage, dependency, or review constraint; must not imply implementation. |

### State transitions

```text
missing -> needs revision -> approved
future  -> missing        -> needs revision -> approved
approved -> needs revision (when approved design changes or critical coverage is found missing)
```

An inventory item does not transition to implemented. Engineering status belongs in the root roadmap, progress file, and implementation spec.

## Design Handoff

Represents an approved design area translated into an engineering-ready input.

| Field | Required | Rules |
|---|---|---|
| Title and source area | Yes | Maps to one inventory item or one explicitly bounded slice. |
| Approval evidence | Yes | Identifies what was approved and by whom/when according to project practice. |
| Scope and exclusions | Yes | Prevents adjacent ideas from entering implementation. |
| Screens/components/variants | Yes | Covers the selected slice completely. |
| Content constraints | Yes | Covers long text, missing media, localization, and user-provided content where relevant. |
| Interactions and states | Yes | Includes default, hover/focus, loading, empty, error, success, disabled, and dependency states where relevant. |
| Responsive behavior | Yes | Covers desktop, tablet, and mobile. |
| Directionality | Yes | Covers LTR, RTL, and mixed-script behavior. |
| Accessibility | Yes | Covers keyboard, focus, contrast, semantics, labels, announcements, and motion. |
| Performance | Yes | Covers conditional assets, media behavior, motion, and fallback expectations. |
| Tokens/primitives | Yes | Maps visual choices to reusable semantic foundations without inventing implementation values. |
| Open questions | Yes | Must be empty or explicitly deferred outside the selected scope before spec creation. |

### Lifecycle

```text
draft -> design review -> approved -> spec candidate
                         -> needs revision
```

## Engineering Spec Candidate

Represents the next bounded engineering feature selected from a milestone and approved handoff.

| Field | Required | Rules |
|---|---|---|
| Spec number/title | Yes | Unique and present in the immediate two-to-three-item queue. |
| Source milestone | Yes | Identifies roadmap dependency and outcome. |
| Source handoff | For design work | Must be approved before the engineering spec is detailed. |
| Scope boundary | Yes | One independently reviewable implementation area. |
| Readiness | Yes | Candidate, specified, planned, tasked, implemented, or verified. |

## Planning Surface

| Surface | Primary responsibility |
|---|---|
| Root roadmap | Durable product and engineering direction. |
| Progress file | Latest resume state and next action. |
| Changelog | Actual released and unreleased product changes. |
| Decisions log | Important architecture/product choices and rationale. |
| Design roadmap/inventory | Design sequence, coverage, and approval state. |
| Design handoff | Approved design translated into engineering constraints. |
| Implementation spec | Testable contract for one bounded implementation area. |

Conflicting status is resolved by responsibility rather than duplication: design readiness comes from the inventory; implementation readiness comes from the active spec and progress evidence; release state comes from the changelog.
