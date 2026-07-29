# Tasks — Spec 091

- [x] T001 Reproduce the 1px RTL overflow at 375px, measured not looked at
- [x] T002 Bisect the document to the actual element — `#wp-admin-bar-menu-toggle .ab-item`
- [x] T003 Confirm it affects every CoreX screen, not just access
- [x] T004 Test two candidate fixes in both directions before choosing one
- [x] T005 Add the flipped-margin rule to `corex-admin-shell.css`
- [x] T006 `tests/e2e/rtl-overflow.spec.js` — 6 screens × 2 directions, measured
- [x] T007 Verify the six RTL cases fail without the fix
- [x] T008 Trace the accumulating fixture users to their real source
- [x] T009 Delete the fixture user in `AccessRequestFormTest`'s `afterEach`
- [x] T010 Verify a run leaves the fixture count unchanged
- [x] T011 Update `PROJECT-STATUS.md` and its mirror
- [x] T012 Full suites

## Not done

- **The 311 fixture users already on the development install.** Repository state is fixed; that
  install is not repository state, and deleting user accounts on somebody's environment is not a
  spec's call. The command is in `PROJECT-STATUS.md`.
