# Tasks: A release is installed from the admin, on a host with no command line

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

**How this was written**: by hand to the Spec Kit tasks template. `/speckit-tasks` was not run.

Tests are written first and seen to fail. `[P]` marks a task that touches no file another open task
touches. Each slice ends with its guards, its rendered check where it has UI, and its notes
(`CHANGELOG.md` with client impact, `PROGRESS.md`, `DECISIONS.md`). Nothing changes a site's
files before slice 4.

## Slice 1 — The package says what it holds, and is a zip (US2's ground; plan D1, D2)

- [x] T001 Jest: a built package's `corex-release.json` has `schema: 2`, `requires` from `corex-core.php`'s headers, `wordpress_version` from the packaged core (or `null`), every release path, and for each its files, bytes and hash (`tests/build-shared-host-dist.test.js`)
- [x] T002 [P] Jest: the folder hash — the same for the same files whatever order they are read in; different when a byte, a name or a file changes; paths with forward slashes on every system (`tests/release-content-hash.test.js`)
- [x] T003 `scripts/release-content-hash.mjs`; the builder writes the schema 2 manifest after the tree is final (after vendor is installed and pruned)
- [x] T004 Jest: `--zip` writes one zip named for the client, version and build time, with `corex-release.json` at its root and every release path in it; `verifyDist` refuses a package whose contents no longer match its manifest
- [x] T005 `--zip` in the builder; `verifyDist` checks schema 2 and recomputes `contents`
- [x] T006 [P] Pest unit: `ReleaseManifest` — parses a schema 2 manifest; refuses, each with its own reason, one that is not JSON, has no `schema` (built before this), has a release path that leaves the package, or lacks `contents` for a path it names (`tests/Unit/Releases/ReleaseManifestTest.php`)
- [x] T007 `ReleaseManifest` (`plugins/corex-config/src/Releases/`)
- [x] T008 Pest unit: `ReleaseContentHash` over a real directory in the temp folder agrees with a hash recorded from the Node side for the same files (a fixture written by T002's script, committed)
- [x] T009 `ReleaseContentHash`
- [x] T010 Docs: `shared-host-dist.md` (the zip, the manifest's new keys); guards; notes

## Slice 2 — A package that is wrong is refused, and nothing is touched (US2; D3, D4, D5, D15)

- [x] T020 [P] Pest unit: `ReleaseStore` — creates its place with guard files, answers paths, reads and writes state and log, caps the log, never answers a path outside its place
- [x] T021 `ReleaseStore`
- [x] T022 [P] Pest unit: `ReleaseHostFacts` — each fact that prevents an installation, named: file changes not allowed, no `ZipArchive`, a folder not writable, too little space where it is reported. And on real WordPress: what the host says of itself
- [x] T023 `ReleaseHostFacts`
- [x] T024 [P] Pest unit: `InstalledRelease` — from the option; from `corex-release.json` in the site's root when there is no option; unknown when there is neither
- [x] T025 `InstalledRelease`
- [x] T026 Pest unit, with real zips built in the temp folder: `ReleasePackageInspector` refuses, each named for what is wrong — not a zip; no manifest at the root (a zip of a `dist/` folder); schema 1; another client; PHP or WordPress too old; a release path missing from the zip; an entry that leaves its folder; a forbidden segment; and answers the statement of FR-010 for a good one, and that an older release needs saying twice
- [x] T027 `ReleasePackageInspector`, `ReleaseInspection`
- [x] T028 Pest unit: after every refusal in T026 nothing exists in the site that did not before (the same test: the site's folder is listed before and after)
- [x] T029 Guards; notes

## Slice 3 — A package is received and read on the screen (US1, receiving; D12, D13)

- [x] T030 Pest unit: `corex_manage_releases` is in the catalog, critical, implied by managing the admin, and not by managing operations
- [x] T031 The Releases screen in the admin shell's four maps (the ability itself is done, with T030)
- [x] T032 [P] Pest unit: `ReleaseUpload` — parts appended in order; a part at the wrong offset refused with the offset it has; the whole file's hash checked at the end; a second upload does not disturb the first; names cannot leave `incoming/`; a hash that is not one is refused; a package that grows past the limit is thrown away
- [x] T033 `ReleaseUpload`
- [x] T034 Pest integration: the routes — refused without the ability or the nonce; a zip put in `incoming/` is listed; a package is received in parts, kept, inspected and its statement answered; a refusal is in words and is written down
- [x] T035 `ReleasesController`, `ReleaseRestGateway`, `ReleaseDesk`, bindings
- [x] T036 [P] Jest: the installer's client — takes only an answer with the installer's mark; reads an HTML 200 as "wait"; backs off and resumes an upload from the offset the server answers; gives up after a stated number of tries and says so
- [x] T037 The client; the screen: the host's facts, the running release, receive, the statement, each refusal in words
- [x] T038 Playwright: the screen in both themes and both directions, at 1280 and 782; an upload of a small fixture package to its statement; measured spacing
- [x] T039 Guards; UI/UX gate; notes

## Slice 4 — The release is installed (US1, US3; D6 to D11, D14)

- [x] T040 [P] Pest unit: `ReleaseStaging` — unpacks only release paths; resumes from its cursor; a staged folder whose bytes or hash differ from the manifest is refused and staging emptied
- [x] T041 `ReleaseStaging`
- [x] T042 [P] Pest unit: `ReleaseJournal` and `ReleaseSwap` over real folders — swaps every path; a folder the package does not name is left; a folder the installed release owned and the package dropped goes to `previous/`; cut off after any rename, the next call finishes or undoes and says which; a rename that fails undoes what was done
- [x] T043 `ReleaseJournal`, `ReleaseSwap`
- [x] T044 [P] Pest unit: `ReleaseMaintenance` — writes a `.maintenance` WordPress will honour, that lets through only a request carrying the key; removes it; the key is never in the file, only its hash
- [x] T045 `ReleaseMaintenance`
- [x] T046 [P] Pest unit: the recovery plugin, included in a process with no CoreX — with the key it puts every folder back and removes `.maintenance`; without it, or with a wrong one, it does nothing and says nothing
- [x] T047 The stub; `ReleaseRecoveryPlugin` writes and removes it; an installation is refused when it cannot be written
- [x] T042b [P] Pest unit: a file the running release holds in a folder and the package does not is gone from the site after the swap and is in `previous/`; a file beside the release's folders is still there (FR-027: the fault of 2026-10-11)
- [x] T047b [P] Pest unit, then `ReleaseHostFacts`: on a site set not to change its files, `COREX_ALLOW_RELEASE_INSTALL` allows a release and nothing else is asked; without it the blocker names the line (FR-018, plan D16)
- [x] T047c [P] Jest, then the builder: `--with-plugin=<folder>` — the folder is in `wp-content/plugins`, in `plugins`, `release_paths` and `contents`; refused when it is missing, is not a plugin, or is a name the package already holds (FR-028, plan D17); `shared-host-dist.md`
- [x] T047d [P] Pest unit, then `ReleaseHostFacts`: a site whose plugins and themes are not where a package holds them is told it cannot install (found while binding the swap to a site; DECISIONS #307)
- [x] T048 [P] Pest unit: `ReleaseTableCopies` — one copy per registered table, named so it can be found and dated; nothing is renamed over a live table
- [x] T049 `ReleaseTableCopies`
- [x] T050 Pest integration: `ReleaseFinisher` — a component behind its version is migrated and listed with from and to; one already current says no change was needed; a failure is named, the release is not recorded as installed, and `.maintenance` stays until going back
- [x] T051 `ReleaseFinisher`
- [x] T052 Pest integration, on real WordPress: a whole installation of a fixture release that replaces one real plugin folder and adds a table — every step in order, the confirmation required and bound to the package, one installation at a time, the record written, an activity event recorded with no key in it
- [x] T052b Pest integration: an installation is refused where it starts when the host has a blocker (FR-018); the folders the running release holds and the package does not are named before the confirmation (FR-028); a second installation, and a second request of the same one, are told one is running; an installation left after the swap is reported unfinished and finished from the screen without its key (FR-035); one left before the swap can be cancelled
- [x] T053 `ReleaseInstallation`; the routes for each step; OPcache cleared after the swap where it can be
- [x] T053b Pest unit, then `ReleaseFolderVersion` and `ReleaseDowngrades`: a plugin or theme the package holds an older version of than the site is named with both versions; one of the same or a newer version, one not on the site, one without a version, and CoreX's own folders are not (FR-029, DECISIONS #371)
- [x] T053c Pest integration, Jest and Playwright: the statement and the confirmation name it before the version is typed; confirming without saying so is refused by the site and not offered by the screen (FR-029)
- [x] T054 The screen: confirm with the version typed, progress by step, the recovery link shown and downloadable before confirming, the outcome
- [x] T055 Playwright: an installation of the fixture release from the screen to "installed"; a visitor's request during it gets the maintenance answer
- [x] T056 Guards; UI/UX gate; notes

## Slice 5 — Going back, and what happened (US4, US5)

- [x] T060 Pest integration: going back from the admin puts the previous folders back, records it, and says no database change was reversed
- [x] T060b Pest integration: a return made through the recovery link is found when the screen is next opened, recorded as one, and the site's record of its release put back (FR-045)
- [x] T061 The route and the screen's action, with its own confirmation
- [x] T062 [P] Pest unit: one previous release is kept; installing again discards the one before; table copies and unused packages older than their time are removed when the screen is opened
- [x] T063 Retention without a scheduled job
- [x] T064 The record on the screen: each installation, refusal and return
- [x] T065 Playwright; guards; UI/UX gate; notes

## Slice 6 — Said where an operator reads it

- [x] T070 `docs/en/05-deployment/releases-from-the-admin.md`: building the zip, a third-party plugin in it (D17), the configuration line and what it does not give (D16), installing, what is and is not replaced (D18), the recovery link, the database step and what going back does not undo (D19), that this is not a backup
- [x] T071 `docs/en/05-deployment/first-installation-by-hand.md`: the first installation, still by hand, and the move of a site updated by extraction to the screen (D20)
- [x] T072 `updates-and-distribution.md` and `updating-a-client-site.md` point to it; the docs site; guards; notes

## After the first installations on a shared host (2026-10-18; DECISIONS #370)

- [x] T080 [P] Pest unit: `ReleaseSwap::hasMovedNothing()` — cut off before the first rename, after it unmarked, before the second; after going back. `ReleaseRecoveryPlugin::keyHash()`
- [x] T081 `ReleaseSteps::hasMovedNothing()`; `ReleaseInstallation::cancel()` and `ReleaseReturn` ask it; giving up at the swap withdraws this installation's safeguards only (FR-022a)
- [x] T082 Pest integration: a swap cut off before its first folder moved is given up, one that moved two is refused; Perego's case with a kept release; the leftovers named and removed; nothing removed while an installation stands
- [x] T083 `ReleaseLeftovers`, `POST releases/leftovers/remove`, `leftovers` in the overview (FR-022b)
- [x] T084 Jest, then the screen: **Give it up** at the swap when nothing moved; **Left from an installation** with **Remove them**
- [x] T085 The guide's "If the page is closed" section; guards; notes
