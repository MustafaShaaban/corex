# Tasks — Spec 095

- [x] T001 Diagnose the shared-fixture login collision from two unrelated PR failures
- [x] T002 Seed `corex-guides-editor` in the CI fixture step
- [x] T003 Point `guides.spec.js` at it, overridable by `COREX_GUIDES_EDITOR_USER`
- [x] T004 Create the user on the development install
- [x] T005 Guides suite green locally
- [ ] T006 Full browser suite green twice in CI — **only verifiable there**

## Note

T006 is the point of the change and cannot be proven locally: the collision needs a single-IP runner
executing every spec in one session. Left open until CI says so, rather than assumed from a green
guides run.

The lockout policy is deliberately untouched. It is the product working, and turning it down to suit
the suite would remove a real protection from the thing being tested.
