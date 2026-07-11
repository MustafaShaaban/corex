# UI State Contract

Every approved current CoreX component declares the states that apply. An enabled-looking control without an action violates this contract.

## Required State Vocabulary

- `default`: current real state with primary content
- `hover`: stable visual affordance without layout shift
- `focus-visible`: keyboard-visible ring with sufficient contrast
- `active` or `selected`: current navigation, tab, row, toggle, or choice; not color-only
- `disabled`: non-actionable only for a real current reason; reason available in nearby text or description
- `loading`: operation identified, controls protected from duplicate mutation, live status announced
- `empty`: successful read with zero accessible records
- `error`: failed read/action with safe message and retry where possible
- `success`: authoritative completed result, never inferred from dispatch
- `permission-denied`: proper forbidden state with safe destination and access-request path when eligible
- `dependency-required`: names missing dependency and working setup/install/activate path

## Interaction Rules

1. Links navigate; buttons perform actions; switches change persisted boolean state.
2. Disabled buttons are prohibited when the required product behavior can be implemented in the current product.
3. Modal/drawer open moves focus inside; Escape closes when safe; close returns focus to the trigger.
4. Destructive/production/personal-data dialogs describe scope, require confirmation, and bind confirmation to the preview hash.
5. Tables keep headers, selection, sorting, filters, pagination, loading, empty, and error semantics accessible.
6. Tabs use one active tab, keyboard-reachable controls, and a labelled panel.
7. Toasts/notices supplement, never replace, persistent operation results and audit history.
8. Direction changes preserve semantic order and mirror directional affordances.
9. Reduced motion removes non-essential transitions and auto movement.
10. Mobile pages have no document-level horizontal pan; wide data regions use labelled contained scrolling or responsive cards.

## Visual Evidence Matrix

For every changed route or front-end template, capture or probe:

- dark + LTR desktop
- light + LTR desktop
- dark + RTL desktop
- light + RTL desktop
- 375px mobile in applicable directions
- keyboard focus on primary actions and first error
- hover for pointer actions
- reduced-motion media query
- loading, empty, error, permission, and dependency states where applicable

Evidence must include the route, state fixture, viewport, direction, appearance, and observed result.
