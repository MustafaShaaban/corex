# Forms & Flows Builder UI Design

Date: 2026-07-04  
Status: Approved by Spec 068 and the owner's standing instruction to select the recommended implementation path.

## Selected Pattern

Use a master-detail workflow. The default view is a searchable/filterable flow list with lifecycle, placement/owner,
field count, and updated time. Creating or selecting a flow opens one focused editor. The editor keeps a compact
pipeline rail visible while one detail tab is active: Form, Validation, Routing, Emails, Success, Preview, or Test.

This is preferred over a sequential wizard because administrators must revisit stages non-linearly, and over a
freeform node canvas because deterministic top-down routing does not need spatial graph editing. On narrow screens,
the list and editor stack; tabs become one intentional horizontal rail; field settings become an in-flow panel rather
than a viewport-fixed drawer.

## Interaction Contract

- New Flow creates a real persisted draft and opens it.
- Search and lifecycle filters operate on real server results.
- Add/edit/remove/reorder field actions update local draft state, validate on blur, and save an immutable version
  through the optimistic checksum contract.
- Stage badges mean incomplete, ready, or error and are derived from current configuration, never fabricated.
- Publish, unpublish, close, preview, and test call their matching REST operations and announce loading, success,
  conflict, validation, or permission outcomes through an `aria-live` region.
- Routing is a numbered top-down list; the fallback is always visible and cannot be deleted.
- All inputs have explicit labels. Keyboard focus is visible and returned after dialog/panel actions. Motion is limited
  to short color/opacity transitions and disabled under `prefers-reduced-motion`.

## Component Boundaries

- `flowEditor.js`: pure normalization, reducer, validation, reorder, and endpoint helpers.
- `useFlows.js`: REST loading/mutations and optimistic conflict state.
- `FlowList.js`: query controls, real rows, lifecycle actions, empty/loading/error states.
- `FlowEditor.js`: editor composition and save/publish toolbar.
- `StageRail.js`: pipeline status and active-stage navigation.
- `tabs/`: one focused component per configuration domain.
- `index.js`: localized configuration, mount, and composition only.

## Visual System

Consume existing `--corex-admin-*` variables only. Use the established CoreX surface, border, badge, and button
language; do not import fonts, colors, icon libraries, or CSS frameworks. Verify light/dark, LTR/RTL, reduced motion,
and 375/768/1024/1440 widths. The only permitted horizontal overflow is the labelled stage/tab rail.

## Self-review

The design contains no deferred requirement or competing source of truth. It maps directly to T077–T082 and leaves
the submission pipeline, dynamic blocks, end-to-end evidence, and documentation to T083–T091.
