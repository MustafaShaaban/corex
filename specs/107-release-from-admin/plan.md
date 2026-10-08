# Implementation Plan: A release is installed from the admin, on a host with no command line

**Branch**: `spec/107-release-from-admin` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/107-release-from-admin/spec.md`

**How this was written**: by hand to the Spec Kit plan template, from a map of the source at
`9302016f`. `/speckit-plan` was not run.

## Summary

The package a site's repository builds becomes a zip that says enough about itself to be checked
where it lands. On the site an administrator gives CoreX that zip; CoreX checks it, unpacks only
the folders the release owns into a staging place, swaps them in with renames while WordPress's
own maintenance answer is up, brings the database to the release in a following request that
runs the new code, keeps what it replaced, and records it. A must-use plugin that needs nothing
of CoreX puts the previous release back when the admin will not load.

Delivered in six slices, each its own pull request, in the order under "Slices".

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (ES2022) through `@wordpress/scripts` 36 for the admin
screen; Node for the builder

**Primary Dependencies**: WordPress 7.0; PHP's `ZipArchive` (the screen says so when it is
absent); `adm-zip` in the builder (already a dev dependency)

**Storage**: a directory of the installer's own, `wp-content/corex-releases/`, outside every
folder a release replaces and outside `uploads/`; one option for the installed release's
description. No new table: the tables are what a release migrates

**Testing**: Pest unit with real directories and real zips in the system temp directory; Pest
integration on real WordPress for the routes and the migration step; Jest for the builder and
for the screen's stepping; Playwright for the screen

**Target Platform**: shared hosting with no shell, LiteSpeed or Apache or nginx, a single site
first; a network is supported for the files and migrates site by site

**Project Type**: WordPress plugin (`plugins/corex-config`), the builder (`scripts/`), one
generated must-use plugin

**Performance Goals**: SC-004: back on the previous release in under two minutes. Each request
of an installation stays under half the host's PHP time limit, and under 15 seconds

**Constraints**: no scheduled job (the owner's rule on this host); no `WP_Filesystem` (it can ask
for FTP credentials nobody is there to give, which is why CoreX avoids it already); nothing the
installation needs may live in a folder it replaces; a burst of requests is answered with an
HTML page that must be read as "wait"

**Scale/Scope**: 2,633 files and 27MB of release-owned folders for the framework alone, measured
in slice 1, out of a 34MB zip that is mostly WordPress; an account allowance of 300,000 files
shared by two sites

## What the source does today

| Fact | Where |
|---|---|
| `build:dist` fills a `dist/` directory. Nothing makes a zip | `scripts/build-shared-host-dist.mjs` `runBuild()` |
| `corex-release.json` holds `name`, `built_at`, `corex_version`, `client`, `plugins`, `themes`, `autoload`, `pdf_fonts`, `excludes`, `forbidden_segments`. No requirement, no WordPress version, no hash, no file count | same, `buildPlan()` |
| The package holds WordPress core as well as `wp-content/plugins`, `themes`, `packages/cli` and `vendor`. The last two are named only through `autoload` | same |
| `verifyDist` checks structure, forbidden path segments, no root `.htaccess`, client assets, no dev packages, and a load probe that needs its own PHP process | `scripts/verify-shared-host-dist.mjs`, `shared-host-dist-probe.php` |
| Nothing on a running site reads `corex-release.json`, and a site has no record of which client it is | `git grep` over `plugins`, `addons`, `packages`, `theme` |
| The version is the constant `COREX_CORE_VERSION`, fixed when the file is included; requirements are plugin headers | `plugins/corex-core/corex-core.php` |
| The schema follows the code on the first admin or cron request, one site at a time; REST does not trigger it. There is one registered component. No migration can be reversed | `Database/Schema/SchemaSelfHeal.php`, `SiteMigrationRunner.php` |
| Three add-ons create their tables on every `init`, outside the registry | bookings, careers, newsletter providers |
| A table can be copied (`CREATE TABLE … LIKE`, `INSERT … SELECT`) and the copy discarded; the same class's `restore()` drops the live table | `DataModels/WpMigrationTableRunner.php` |
| CoreX's maintenance mode answers at `template_redirect`, after every plugin has loaded, and lets admin, REST, AJAX, cron and administrators through. WordPress's own `.maintenance` file is not used anywhere | `Operations/MaintenanceGuard.php` |
| Bounded jobs keep their state in a table of the component a release migrates, and always hand themselves to a scheduler | `Jobs/JobRunner.php`, `JobService.php` |
| Nothing renames or unpacks a directory at runtime, writes a must-use plugin, clears OPcache, reads `DISALLOW_FILE_MODS`, the PHP upload or time limits, or free space | `git grep` |
| The admin's API helper reads a 200 answer that is not JSON as success with no data | `plugins/corex-core/assets/js/corex-runtime.js` |
| An administrator holds every CoreX ability through `manage_options`; a role can be denied one. `Confirmation` binds an action to an actor, a target and a typed phrase, once | `Access/AbilityCompatibility.php`, `corex-core/src/Operations/Confirmation.php` |

## Decisions

### D1. The builder makes the zip

`npm run build:dist -- --zip` writes `corex-release-<client or "framework">-<version>-<built>.zip`
beside `dist/`, with the package's contents at the zip's root, so `corex-release.json` is always
at the root. A zip made by hand from a `dist/` folder is refused on the site with that reason.

### D2. The package says what it holds (manifest schema 2)

`corex-release.json` gains:

- `schema: 2`;
- `requires: { php, wordpress }`, read from `corex-core.php`'s headers;
- `wordpress_version`, read from the packaged `wp-includes/version.php`, or `null`;
- `release_paths`: every folder the release owns, relative to the package: each
  `wp-content/plugins/<name>`, each `wp-content/themes/<name>`, `wp-content/packages`,
  `wp-content/vendor`;
- `contents`: for each release path, its number of files, its bytes, and a hash.

The hash of a folder is SHA-256 over one line per file, sorted by path: the path with forward
slashes, the size, and the file's SHA-256. The builder computes it in Node and the site computes
it in PHP over what it unpacked; a test builds a package and checks the two agree. A package
with no `schema` is refused as built before this.

### D3. What is checked on the site, and what cannot be

In PHP, in order, stopping at the first refusal: the zip opens; the manifest is at its root and
is schema 2; the client matches the site's (D4); PHP and WordPress meet `requires`; every release
path is in the zip; no entry leaves its folder (`..`, an absolute path, a drive letter) and none
has a forbidden segment; then, after unpacking, each folder's count, bytes and hash match;
`vendor/composer/installed.json` holds no dev package; the autoloader and every mapped namespace
folder are there.

The builder's load probe needs a PHP process of its own, which this host cannot start. Its place
is taken by the request that finishes the installation: it is the first to run the new code, and
if it cannot, the previous release is one link away (D9).

### D4. A site learns which client it is

The description of the installed release is kept in the option `corex_release_installed`. On a
site that has never installed from the admin, CoreX reads `corex-release.json` from the site's
root, where a package unpacked by hand leaves it. With neither, the first installation asks the
administrator to confirm the package's client by name, and records it.

### D5. The installer's own place and state

`wp-content/corex-releases/`, with the guard files `ProtectedUploads` writes:

```text
incoming/   packages being received, and packages put there by hand
staging/    the release paths of the package being installed, unpacked
previous/   the folders the last installation replaced, and its journal
state.json  the installation in progress: its step, its cursor, its key's hash
log.json    what happened: installations, refusals, returns (the last 50)
```

Not under `uploads/`: a backup plugin would copy two releases of code into every backup. Not a
bounded job: a job's state is in a table the release migrates and its runner is in a folder the
release replaces.

### D6. The swap is renames, journaled

For each release path: the live folder is renamed into `previous/`, the staged one renamed into
its place. The journal is written before each rename and marked after it, so a request that is
cut off is finished or undone by the next one, and the screen says which (FR-022). A folder the
installed release owned and the package does not hold is moved to `previous/` as well; a folder
the package does not name is never touched (FR-026).

### D7. Visitors get WordPress's own maintenance answer

For the swap and until the installation is finished, the installer writes WordPress's
`.maintenance` file in the site's root. WordPress reads it before it loads any plugin, so no
request can run a half-replaced site, and it stops applying by itself after ten minutes. The file
is PHP that WordPress includes; ours lets through the one request that carries the installation's
key, which is how the finishing request and the recovery link get in. CoreX's own Maintenance
mode is not used: it answers after every plugin has loaded, and switching it would have to be
undone.

### D8. Steps, each one request, driven by the browser

| Step | Runs on | Does |
|---|---|---|
| receive | old code | appends a part of the zip, answers how much it has |
| inspect | old code | D3's checks on the zip; answers the statement of FR-010 or a refusal |
| unpack | old code | unpacks release paths into `staging/`, a time-boxed share per request |
| verify | old code | counts, bytes and hash of each staged folder, one or more per request |
| copy tables | old code | copies CoreX's registered tables, one per request (FR-034) |
| swap | old code | writes the recovery plugin and `.maintenance`, runs D6, clears OPcache |
| finish | **new code** | migrates the site or the next batch of sites, lists what moved, removes `.maintenance`, records |

Between inspect and unpack the administrator confirms, with a `Confirmation` bound to them and
to the package's hash, typing the version being installed.

The finishing request is answered by the release just installed. Its route and its answer are a
contract between releases, versioned in `state.json`, and documented as one.

### D9. The way back that needs no admin

When an installation starts, CoreX writes `wp-content/mu-plugins/corex-release-recovery.php`. It
uses nothing of CoreX. Asked with the installation's key, it reads the journal in `previous/`,
renames each replaced folder back, removes `.maintenance`, and says what it did in plain text.
WordPress loads must-use plugins before ordinary ones, so it runs when the new release cannot.
The link is shown, and offered as a text file, before the administrator confirms. If the
must-use folder cannot be written, the installation is refused (FR-042).

Going back from the Releases screen runs the same reversal through CoreX's own code.

### D10. Going back and the database

No schema change CoreX makes can be reversed today, and the screen says exactly that. The copy
of FR-034 is made of the tables in the schema registry, kept fourteen days, and removed when the
screen is next opened after that (no scheduled job). It is never renamed over a live table. Not
in it, and said on the screen: the three add-ons' own tables, and submissions, which are posts.

### D11. The database step

In the finishing request: the stored version of each component is read, the runner is run for
the site (on a network, for the next batch of sites, resumable by offset), and what is listed is
each component with the version it moved from and to. Tables an add-on creates for itself are
named as created outside the list. Under D7 no other request can start the schema's own
self-heal in the meantime.

### D12. Receiving a package

In parts no larger than the host says it takes (`wp_max_upload_size()`, less a margin), each a
raw body appended to a `.part` file, with the offset answered so an interrupted upload resumes.
The browser paces itself and backs off. The whole file's SHA-256, computed in the browser, is
checked when the last part lands. A zip named `corex-release-*.zip` put in `incoming/` through
the host's file manager is offered without an upload.

The installer's requests do not go through the admin's API helper. They use a small client of
their own that takes only a JSON answer carrying the installer's mark as an answer; anything
else, the host's HTML challenge page included, is "wait and ask again" (FR-025).

### D13. Who

A new ability, `corex_manage_releases`, critical, held by administrators through the catalog and
deniable per role. On a network it also needs a super administrator, and the screen is the main
site's.

### D14. The record

`log.json` in the installer's place, so a record survives a release whose migration failed, and
an activity event beside it when the activity log can take one.

### D15. What the host can do, said first

`ReleaseHostFacts` reads, when the screen is opened: whether file changes are allowed
(`wp_is_file_mod_allowed`), whether `ZipArchive` is there, whether the plugins, themes,
must-use, content and root folders are writable, the upload and time limits, and free space
where the host reports it. Each fact that prevents an installation is named with what to do.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| Spec before code (X) | The spec and this plan, before the first slice. |
| Theme is a skin (I), plugins boot themselves (II) | Nothing here is in a theme. The recovery plugin is generated, self-contained, and removed when no previous release is kept. |
| Thin controllers, logic in services (III) | One controller maps a step to a service call. Inspection, staging, swap and finishing are services over a store; the journal and the manifest are values. |
| Everything injected (IV) | Services are bound in `ConfigServiceProvider`. The recovery plugin is the stated exception: it must not need the container. |
| Security declarative (VII) | Routes go through a gateway with the ability and the nonce; the screen through `AdminGuard`. The installation's key is checked by hash, never stored, and never put in the activity log. Zip entries are checked for traversal before anything is written. |
| RTL first (VIII), tokens (V), assets conditional (VI) | The screen uses the admin shell's tokens and logical properties, and its script loads on its own page. |
| No optional dependency is hard (IX) | `ZipArchive` and OPcache are detected; without the first the screen says so, without the second nothing is cleared. |
| i18n, WCAG 2.2 AA | Every string through `corex`; progress is announced; the confirmation is a labelled field. The recovery plugin's few lines are plain English: it runs when translation may not load. |
| Tests | Under each slice in tasks.md, written first. |
| Guard Gate, UI/UX gate | Each slice through its guards; the screen measured in both themes and directions before it is presented. |

Two exceptions are stated, not hidden: the recovery plugin instantiates nothing through the
container and is not translated, for the reason it exists.

## Project Structure

```text
scripts/build-shared-host-dist.mjs            # schema 2 manifest, --zip
scripts/release-content-hash.mjs              # new: the folder hash, shared with the verifier
plugins/corex-config/src/Releases/
├── ReleaseManifest.php                       # the description, parsed and refused
├── ReleaseContentHash.php                    # the folder hash, in PHP
├── ReleaseStore.php                          # the installer's place: paths, guards, state, log
├── ReleaseHostFacts.php                      # D15
├── InstalledRelease.php                      # D4
├── ReleasePackageInspector.php               # D3 on the zip
├── ReleaseInspection.php                     # the statement, or the refusal
├── ReleaseUpload.php                         # D12
├── ReleaseStaging.php                        # unpack and verify
├── ReleaseTableCopies.php                    # D10
├── ReleaseSwap.php, ReleaseJournal.php       # D6
├── ReleaseMaintenance.php                    # D7
├── ReleaseRecoveryPlugin.php                 # D9: writes the must-use plugin from a stub
├── ReleaseFinisher.php                       # D11
├── ReleaseInstallation.php                   # the steps of D8 over the state
├── ReleasesController.php, ReleaseRestGateway.php
├── ReleasesScreen.php
└── *.js                                      # the screen and its client
plugins/corex-config/stubs/corex-release-recovery.php.stub
plugins/corex-core/src/Access/CorexAbility.php, CorexAbilityCatalog.php   # the ability
docs/en/05-deployment/releases-from-the-admin.md                          # new guide
tests/Unit/Releases/, tests/Integration/Releases/, tests/e2e/releases.spec.js
```

## Slices

Each is one pull request and leaves `main` releasable. Nothing reaches a site's files before
slice 4.

| # | Stories | What lands |
|---|---|---|
| 1 | US2 (its ground) | The builder writes the schema 2 manifest and the zip; `ReleaseManifest` and `ReleaseContentHash` in PHP, with the test that both sides hash a real package the same. |
| 2 | US2 | The installer's place, the host's facts, the site's installed release, and inspection of a zip: every refusal of story 2, with nothing written outside the installer's place. |
| 3 | US1 (receiving) | The ability, the Releases screen, receiving in parts and picking up, and the statement of FR-010. A package can be received and inspected; it cannot yet be installed. |
| 4 | US1, US3 | Unpack, verify, copy tables, swap under WordPress's maintenance answer, the recovery plugin, finish with the database step, the record. |
| 5 | US4, US5 | Going back from the screen, the kept release and table copies' lifetime, the record on the screen. |
| 6 | all | The deployment guide, the first-installation runbook as it stays by hand, and the hosting package's docs. |

## Risks

- **A request that replaces the code it is running.** The swap request has every class it needs
  loaded before the first rename and does nothing after the last one but clear OPcache and
  answer. The next request is the new release's. This is what WordPress's own upgrader does;
  here it is tested by swapping a real plugin folder under a running WordPress in the
  integration suite, and it has to be watched once on the host before it is relied on.
- **Renames across a mount.** `wp-content/corex-releases/` is beside the folders it trades with,
  so a rename stays on one filesystem. Where it does not (a host that mounts `plugins`
  separately), a rename fails and the swap is undone from its journal; the screen says why.
- **The finishing request is answered by code that did not exist when the installation
  started.** Its route and answer are a versioned contract. A release that changes it must
  still answer the previous protocol.
- **A network.** Files are swapped for every site at once; the database follows site by site in
  the finishing requests. Until the last site is done `.maintenance` stays up, bounded by
  WordPress's ten minutes: a network too large to migrate in that time finishes with the
  remaining sites migrated by their own first admin request, and says so.
- **Space.** Staging and the kept release are each a second copy of the release's folders.
  `ReleaseHostFacts` refuses when the host reports too little; many shared hosts report
  nothing, and the file allowance is not readable from PHP.
- **Three add-ons migrate themselves.** Their tables are outside the registry, so they are not
  copied and not listed. Moving them into the registry is their own change, noted here and not
  made.
- **Nothing here has run on the host it is for.** Each slice says what was and was not seen.
  The first installation on that host should be of a release that changes nothing important.
