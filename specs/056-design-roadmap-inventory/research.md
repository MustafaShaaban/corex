# Research: Design Roadmap and Inventory Integration

## Decision 1 - Separate planning surfaces by responsibility

**Decision**: Keep the root roadmap, progress file, changelog, decisions log, design planning area, and implementation specs distinct.

**Rationale**: Each surface answers a different question. The separation lets a new maintainer find future direction, immediate resume state, release history, rationale, design approval, and implementation requirements without reconciling duplicates.

**Alternatives considered**:

- Keep all status in the root roadmap: rejected because session status and release history would quickly make it stale.
- Put engineering milestones in the design roadmap: rejected because design approval and engineering dependency order are different concerns.
- Use specs as the master roadmap: rejected because detailed historical specs do not provide an owner-level portfolio view.

## Decision 2 - Use one design inventory with controlled vocabulary

**Decision**: Track each design area with `approved`, `needs revision`, `missing`, or `future`, plus `high`, `medium`, or `low` priority.

**Rationale**: Four status values distinguish readiness from absence and intentional deferral without inventing false progress percentages. Three priorities are sufficient for sequencing and remain easy to audit.

**Alternatives considered**:

- Percentage complete: rejected because external design coverage is qualitative and percentages would be unverifiable.
- Engineering states such as active or implemented: rejected because the inventory tracks design readiness, not delivery status.
- Free-form status text: rejected because it prevents reliable filtering and consistency checks.

## Decision 3 - Require a focused handoff before an engineering spec

**Decision**: Approved external design becomes a focused handoff, then a reviewed engineering spec, then implementation.

**Rationale**: Visual approval alone does not prove interaction states, responsive behavior, RTL, accessibility, performance, token mapping, or scope exclusions. The handoff captures those constraints while Spec Kit remains the implementation authority.

**Alternatives considered**:

- Implement directly from Claude Design: rejected because it bypasses the constitution's spec-first rule and makes scope difficult to test.
- Copy all design concepts into one large spec: rejected because it prevents incremental review and encourages building unused ideas.
- Treat screenshots as sufficient handoff evidence: rejected because screenshots do not define behavior or non-visual requirements.

## Decision 4 - Keep only three upcoming specs visible

**Decision**: List Specs 056, 057, and 058 as the immediate detailed-spec queue.

**Rationale**: This is enough to show direction while preserving the rule that detailed specs are created only for the next two or three implementation items.

**Alternatives considered**:

- Pre-create specs for every milestone: rejected because design and dependencies will change before later milestones are ready.
- List no spec numbers: rejected because the immediate handoff would be ambiguous.
- List an unlimited backlog of specs: rejected because it recreates the outdated spec-history roadmap.

## Decision 5 - Do not modify changelog or decisions history

**Decision**: Keep the planning-only change out of the changelog and decisions log.

**Rationale**: No released/unreleased product behavior or architectural/product decision changes. The roadmap is being reorganized according to user-approved direction; the feature spec records the planning contract.

**Alternatives considered**:

- Add an Unreleased changelog entry: rejected because it would present planning structure as a product change.
- Add a decision entry: rejected because no new architecture or product boundary beyond the approved roadmap is introduced.
