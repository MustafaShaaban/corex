# Quickstart: Validate Design Roadmap and Inventory Integration

## Prerequisites

- Work on the feature branch, not `main`.
- Read the constitution, progress file, active spec, roadmap, and design-planning files.
- Confirm the active feature points to `specs/056-design-roadmap-inventory`.

## 1. Confirm feature artifacts

Verify these artifacts exist:

```text
specs/056-design-roadmap-inventory/spec.md
specs/056-design-roadmap-inventory/plan.md
specs/056-design-roadmap-inventory/research.md
specs/056-design-roadmap-inventory/data-model.md
specs/056-design-roadmap-inventory/contracts/design-handoff-contract.md
specs/056-design-roadmap-inventory/checklists/requirements.md
design/ROADMAP.md
design/INVENTORY.md
design/handoffs/README.md
```

## 2. Validate roadmap coverage

Confirm the root roadmap contains:

- the responsibilities of roadmap, progress, changelog, decisions, specs, and design planning;
- current foundation status with verification caveats;
- milestones M0 through M11;
- status, priority, outcome, dependencies, and bounded scope for each milestone;
- the first-company-site gate and dependencies;
- Free/Core and Pro/future boundaries;
- the deferred/do-not-implement list;
- the spec creation policy;
- exactly Specs 056, 057, and 058 in the immediate recommended-spec list.

## 3. Validate design inventory semantics

Review every inventory row and confirm:

- status is one of `approved`, `needs revision`, `missing`, or `future`;
- priority is one of `high`, `medium`, or `low`;
- no row claims implementation status;
- missing RTL, accessibility, responsive, state, or performance coverage is visible in notes or retains a non-approved status.

## 4. Validate the handoff gate

Compare `design/handoffs/README.md` with the detailed handoff contract. Confirm a future handoff must include scope, exclusions, behavior, responsive rules, RTL, accessibility, performance, tokens, open questions, and approval evidence before specification.

## 5. Validate scope and formatting

Run:

```powershell
git diff --check
git status --short --branch
```

Expected outcome:

- no whitespace errors;
- only authorized planning/specification surfaces changed;
- no product, package, add-on, theme, test, changelog, or decision file changed;
- the documentation guard reports no blocking findings.

## 6. Rollback check

Because the feature changes planning documents only, rollback is a single revert of the feature diff. No database, runtime, content, or release rollback is required.
