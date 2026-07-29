# Tasks — Spec 096

- [x] T001 `SpecialistScreenGuides` — Access, Operations & Security, Data, Notifications, Blog Pro, Insights
- [x] T002 Each gated on the ability its screen enforces
- [x] T003 A warning before every step that is hard to undo
- [x] T004 Compose in `CorexGuides::all()`
- [x] T005 Verify all 16 guides resolve with the right sections and capabilities
- [x] T006 Suites green

- [x] T007 Screenshots — one per screen, captured and wired

## On the screenshots

**One shot per screen, not one per topic.** Forty images would be forty things to keep true, and a
screenshot that has drifted is worse than none: it teaches a control that is not there. A
screen-level shot answers "am I in the right place", which is what somebody following a guide
actually needs, and stays true across far more change.

Twelve new captures, all produced without a single failure — the script exits non-zero for any id it
cannot take, so a clean run is the evidence that every URL and probe is real. Verified separately
that every wired capture id has a file on disk.
