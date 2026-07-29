# Changelog

All notable changes to Corex are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/) (pre-1.0: the API may still move).

## [Unreleased]

## [0.41.0] — 2026-07-29

Five specs, opened by three defect reports. The thread through them: **two of the three were worse
than reported, and the third had been broken since before anybody looked at its design.**

### Fixed

- **The published documentation site had 85 broken links, not the seven reported.** v0.40.0 gave
  `docs-app` a `base` because a GitHub project page serves from a repository subpath. Astro rewrote
  what it owns — asset URLs, and Starlight's `slug:` sidebar — and left every hand-written markdown
  link alone. `dist/index.html` shipped `href="/corex/project-status/"` from the sidebar and
  `href="/project-status/"` from the body, on the same page. Applied at build time rather than typed
  into 84 links, so `COREX_DOCS_BASE` stays a real override. (DECISIONS #210)
- **The Guides support email arrived as one run-on paragraph.** Its body was `"
"`-joined plain
  text, which is right for `wp_mail()` — and Corex Mail's driver stamps `Content-Type: text/html` on
  every message, so on any site with that add-on active the newlines collapsed. The rendering now
  follows the transport. (DECISIONS #212)
- **Admin "Documentation" links opened raw Markdown.** `DocsUrl` fell back to a GitHub `blob` URL
  into the docs source — correct when written, because no published site existed. It now resolves to
  the site. (DECISIONS #211)

### Added

- **A documentation link test**, asserting the *built* output. This is the deliverable of spec 092;
  the rewrite is what makes it pass. v0.40.0's own verification checked six URLs and every one was a
  form Astro rewrites — a real check that sampled only the working half. The CI job now builds the
  docs, because a link check that silently skips is the condition that published 85 broken links.
- **A branded support email** carrying the CoreX admin brass, replacing the plain-text body on the
  Corex Mail path. Degrades to plain text where that add-on is absent (Principle IX). (DECISIONS #213)
- **The in-admin guide now covers every CoreX admin screen** — **16 guides, 40 topics, 14
  screenshots**, up from 4 guides, 6 topics and 2 screenshots.
  - An orientation guide for somebody who has never seen the framework, gated on `read`: a
    contributor with two capabilities is *more* lost than an administrator, not less. (DECISIONS #214)
  - Every one of the **42 settings fields**, plus the three things the screen cannot say about
    itself — a password field that looks empty may already be set, six captcha fields appear and
    disappear with the driver, and Advanced stores nothing.
  - The six specialist screens, written for somebody who was *sent* there: read is not resolved,
    clear the cache layer you mean rather than everything, an export of personal data is personal
    data.
  - **One screenshot per screen, not per topic.** Forty images would be forty things to keep true,
    and a drifted screenshot is worse than none — it teaches a control that is not there.
- **A coverage test binding the settings guide to the registry**, matching on the field's *label*
  because that is the string on the screen. It failed on its first run, on two fields whose
  parenthetical suffix the prose had dropped. (DECISIONS #215)

### Changed

- **One fixture user per browser spec that signs in.** `admin-errors.spec.js` and `guides.spec.js`
  both signed in as `corex-editor`; CoreX locks an account out after repeated logins from one
  address, and from a CI runner every spec shares one IP. The failure landed in whichever file ran
  second, as "could not sign in", and took down a pull request that changed no browser test at all.
  `guides.spec.js` already carried a comment predicting exactly this about a different user — the
  diagnosis was right and the remedy had never been generalised. **The lockout policy is untouched**:
  it is the product working, and turning it down to suit the suite would remove a real protection
  from the thing under test.
- Two browser assertions were **broader than their intent** and only said so when the page
  legitimately changed: one counted guides instead of naming them, the other matched text across the
  whole document including a contextual Help tab. Both are now scoped to what they mean.

### Verification

Pest unit **1722** · integration **356** · Jest **433** · Playwright **132** · docs site **286
pages** · dependency gate PASS with 0 findings and 0 exceptions · all lints clean.


## [0.40.0] — 2026-07-29

Every dependency advisory closed. The policy file that held **24 bounded exceptions** now holds none,
and `npm run verify:dependencies` passes across Composer, the root npm workspace and the docs site.

### Security

- **Root npm workspace: 64 advisories → 0.** Every vulnerable package was a *transitive* dependency
  whose parent pinned it below the patched version, which is why `npm audit fix` moved none of them.
  What npm proposed instead was a **downgrade** — `@wordpress/scripts@19.2.4` against an installed
  `^33.0.0`, and `@wordpress/env@11.8.0` against `^11.11.0` — and `CONTRIBUTING.md` forbids applying a
  suggested downgrade. The fix is npm `overrides`, which raises a transitive dependency without
  touching its parent: `brace-expansion`, `serialize-javascript`, `uuid`, `webpack-dev-server`,
  `@opentelemetry/core`, `markdown-it`, `linkify-it`, `adm-zip`, and a scoped
  `markdownlint-cli → minimatch` for one nested copy on an old v3 line. (DECISIONS #206)
- **Docs site npm workspace: 7 advisories → 0**, via the Astro 7 migration below.

### Changed

- **`docs-app` runs on `astro@7.1.5` and `@astrojs/starlight@0.41.5`**, building the same 286 pages
  with no configuration change. This had been recorded as blocked by a packaging question —
  regenerating `docs-app/package-lock.json` expands the `corex-framework "file:.."` workspace root
  into the docs tree, which was measured at taking npm-docs from 5 findings to 17. That was only ever
  true while the *root* tooling was dirty: with the root at zero there is nothing for the expansion to
  drag in. The two exception groups had been recorded as independent and were not.

### Fixed

- One override was **backed out**: raising `minimatch` to `^10.2.6` removes its CommonJS default
  export, which `eslint-plugin-jsx-a11y` calls — `lint:js` died with
  `TypeError: (0, _minimatch.default) is not a function`. The block build and the whole Jest suite
  passed with it in place; only the linter caught it. `minimatch` is instead raised only inside
  `markdownlint-cli`, where the vulnerable copy actually was, and stays on the v3 line its parent
  expects.

### Verification

Pest unit **1711** · integration **356** · Jest **431** · Playwright **120** · `verify:dependencies`
PASS with 0 findings and 0 exceptions · docs site 286 pages on Astro 7 · dist builds and verifies ·
all lints clean.


## [0.39.0] — 2026-07-29

Two specs. The first fixed five things an owner reported after using the admin; the second made the
repository describe itself accurately enough to be handed to somebody who has never seen it.

The thread through both: **four of the five reported defects sat behind code that reads correctly**,
and the documentation pass then found three false numbers in the very files written to argue that
this project's claims are checkable.

### Added

- **Contact support from the Guides screen.** A reader who did not find their answer can send a
  category, a message and their email to the site's support address. The address ships in
  `addons/corex-guides/config/guides.php` — one line — and is editable at *CoreX Settings → Guides*,
  which layers over the file. Delivery goes through Corex Mail when it is active and `wp_mail()` when
  it is not: a help form must not stop working because a site does not use the mail add-on.
  (DECISIONS #200)
- **`PROJECT-STATUS.md`** — every module as stable, partial or planned, and every known gap citing the
  file that records it: the 24 bounded dependency exceptions, the three excluded browser specs with
  their ruled-out causes, the 1px RTL overflow, Arabic typography proved for layout but not for type,
  GitHub Pages not enabled, and five of six CI checks not required in branch protection. Mirrored into
  the docs site **above** Getting Started, because somebody deciding whether to adopt a framework
  should meet its limits before its tutorial. (DECISIONS #203)
- **A `corex_submission` deep link** for the Submissions inbox, so an assignment notification opens
  the row it is about rather than an unfiltered list.

### Fixed

- **Notification call-to-actions never worked, in three independent ways at once.** The server
  serialized `label_key` while the card read `label`, so every authored label fell through to a
  hardcoded "Open" — and the JavaScript test covering it fed a payload shape the server has never
  sent. The `ability` gate documented since spec 072 was never enforced, so a viewer could be handed a
  link to a screen that would refuse them on arrival. And **seven of eight producers set no action at
  all**, writing the destination into their body text as prose. All eight now carry a real link, the
  title is a second route to it, and the server withholds an action the viewer may not use rather
  than trusting the client to hide it. (DECISIONS #197, #198)
- **Every snooze click answered HTTP 422.** The client posted `snoozed_until` — the name of the
  database column — while the route reads `until`, so `futureDate('')` returned null every time. A
  dead control that looked alive, on a route with no test.
- **Icon and pager buttons were invisible on the dark admin surface.** Only the `.components-button`
  variants were styled, so a Gutenberg `<Button>` with no variant kept its own `#1e1e1e` ink — the
  submissions detail close, its two Close buttons, and the inbox pager.
- **A blue focus ring appeared after every mouse click.** `.wp-core-ui .button:focus` is specificity
  (0,3,0) and fires on a mouse click; the CoreX rule meant to answer it sat inside `:where()` at
  (0,2,0) and never could. Answered explicitly per family, with the keyboard focus ring preserved —
  removing one without the other would trade a cosmetic complaint for a WCAG 2.4.7 failure.
  (DECISIONS #199)
- **The submission retention panel's eyebrow and title collided.** The markup emitted a bare `<div>`
  where the inbox header emits `<div class="corex-inbox__heading">`; the grid that fixes it already
  existed and simply never applied.
- **Plugin `Update URI` headers pointed at a repository that does not publish releases.** Four plugin
  headers, `composer.json` and `theme/style.css` named an organisation the release pipeline does not
  use. `Update URI` is how WordPress decides where an update comes from, so this was a functional
  defect shipping in every installed copy, not a documentation typo. (DECISIONS #202)
- **`wp corex version` did not stamp everything it promised to.** Its own docblock said the
  version-bearing files are stamped together "so none of them drifts from the release tag", while
  `package.json`, `README.md`, `ROADMAP.md` and both status pages were bumped by hand — which is how
  `ROADMAP.md` came to sit three releases behind a correct `README.md`. All five are stamped now, by
  patterns anchored to the sentence that declares the *current* version, so historical references
  ("released as v0.38.1") are left intact.

### Changed

- **`ROADMAP.md` describes the released version again**, with the real verification counts, specs
  086–088 recorded, and a stated rule for which file wins when it and `PROJECT-STATUS.md` disagree.
- **`README.md`** leads with current status and a link to `PROJECT-STATUS.md`, and explains what
  `specs/`, `DECISIONS.md` and `PROGRESS.md` are — so the working record reads as a method rather
  than as clutter.
- **`CONTRIBUTING.md`** documents the branch model and CI that actually exist. It had described a
  `develop` integration branch that does not exist, and a CI running one suite when it runs five.
- The notification drawer's close control meets the WCAG 2.2 AA target size and carries the body ink
  rather than a muted one, via a new `--corex-admin-touch-target` token.

### Known open

Listed rather than resolved, with sources, in `PROJECT-STATUS.md`: 24 bounded dependency exceptions
(6 high severity), three browser specs excluded from a fresh-install run, a one-pixel RTL overflow on
the access screen, and Arabic typography with layout proof but not type proof. Enabling GitHub Pages
and marking the remaining CI checks required in branch protection are repository settings, not code.

### Verification

Pest unit **1711** · integration **356** · Jest **431** · Playwright **120** · CodeQL and the
dependency-advisory gate green · docs site builds 286 pages.


## [0.38.1] — 2026-07-28

A patch release for one defect that is worse than the rest put together, plus three found alongside
it. All four were found by standing up a plugin that followed this project's own published
documentation, against the real site that reported the issues v0.38.0 closed.

**None of them was visible from the code.** Every one had passing tests around it.

### Fixed

- **A site plugin following the guides documentation could take the whole site down.** `Boot::app()`
  **throws** when Corex has not booted, and Corex boots on `plugins_loaded` at priority 10 — the same
  hook and priority as the site starter this framework generates. Which one WordPress runs first
  depends on the plugin's *directory name*, and the loser got a `RuntimeException` on every request.
  It could not be guarded against either: `app()` throws rather than returning null, so
  `Boot::app() === null` — the check a developer naturally writes — *is* the crash.
  `Boot::booted()`, a `corex_booted` action and `Corex::onReady()` remove the race rather than
  documenting it. (DECISIONS #195)
- **A required file field could never be submitted.** The runtime skipped file inputs when collecting
  values, so `required` saw nothing: the file was attached, the browser said it was missing, and no
  request ever left. Both halves had passing unit tests; only an end-to-end upload found it.
- **The `accept` attribute on a file field never applied.** It matched `mime:` against the declared
  rule *string*, and the schema resolver parses rules into structures long before the renderer sees
  them. Silent, because a file picker with no filter still works.
- **The Data list rendered a stored file as a bare integer** while the detail modal for the same
  column on the same table rendered it as a link. The paged-list path carried its own copy of the
  row-shaping loop and missed the attachment hydration.
- **Following a link to a stored file while signed out** reached `admin-post.php`'s generic
  invalid-action path — HTTP 400 and "Something went wrong" — instead of being told to sign in. The
  delivery route had no logged-out arm.

### Changed

- **Spec 079 is closed.** Its last two tasks shipped: the Data explorer, Submissions inbox and Access
  requests panel render failures through `CorexErrorState`, and the denied surface has a real
  acceptance matrix — 16 cells across 375px/1280px, LTR/RTL, light/dark and 100%/200% zoom, which
  **measures** horizontal overflow rather than photographing it. None overflows. (DECISIONS #196)


## [0.38.0] — 2026-07-28

Four specs, and the thread running through them is that a framework can describe a capability it
does not have. Spec 083 completed an error experience whose unifying half was never built. Spec 084
turned a planned documentation page into an add-on a client site extends. Spec 085 closed three
production reports — and found that two of the eleven items no longer needed work. Spec 081 built
file uploads, which the framework had documented and could not do.

**Verification changed the work in every one of them.** #150's correction to an earlier issue was
stale; #149's second defect had already been fixed; `Table::managed()` did not exist and the
integration suite refused to boot rather than accepting it. The recurring lesson is now recorded
three times: a reported defect is a hypothesis about a tree that has since moved.

### Added

- **Files, end to end.** A CoreX form can ask for a file, store it, show it and send it. Uploads land
  in a protected directory as private attachments and are readable only through a capability-checked
  route — the deny rules are defence in depth, not the guarantee, because `.htaccess` is a promise
  about server configuration the framework cannot verify. A `file` field type, `mime:` and
  `max_size:` rules that read the file rather than the browser's description of it, an attachment
  `DataField` that renders as an openable link, and attachments through to `wp_mail()`'s fifth
  argument, surviving the queue. (Spec 081, issue #138, DECISIONS #192–#194)
- **Corex Guides** — an add-on that ships CoreX's own in-admin user guides *and* the registry any
  site built on CoreX extends with guides for its own content types and flows. Registration is
  deferred to first read, because CoreX and a site plugin both boot on `plugins_loaded` at priority
  10 and the winner otherwise depends on the plugin's directory name. Guides gate on capability, and
  an inactive add-on contributes nothing by construction. Includes contextual Help tabs — a
  WordPress surface this repository had never used — and `wp corex make:guide`. (Spec 084,
  DECISIONS #189; supersedes spec 082)
- **A per-message sender.** `?string $from` threaded end to end, so a site with `info@`, `noreply@`
  and `contact@` properly configured can actually send from all three: SMTP relays route on the From
  address. Survives the queue, so an immediate and a queued message leave from the same mailbox.
  (Spec 085, issue #150)
- **A `phone` validation rule**, with a client mirror that matches the server exactly, plus the
  missing `url` client mirror. Formatting characters are stripped before the pattern runs, so
  `+20 101 699 9700` is not rejected for its spaces.

### Fixed

- **Every human-facing admin refusal is now a CoreX page.** Measured before the fix: nine of eleven
  admin addresses rendered WordPress's white box to a real subscriber, including CoreX's own Careers
  screen. Spec 079 shipped titled "Unified Admin Error and Access Request Experience" with the
  unifying half unbuilt, and nothing caught it because the only browser test touching a refusal
  visited the one URL that could not fail. (Spec 083, DECISIONS #187)
- **The Data record detail modal, which had never displayed a value for any source.**
  `useDataExplorer.detail()` unwrapped a `record` key the endpoint does not send. Spec 080 made it
  *harder* to see: the modal went from rendering em dashes, which reads as broken, to rendering
  "This record has no readable fields" — a sentence that reads as a true statement about the record.
  (Spec 085, issue #149, DECISIONS #190)
- **Silent data loss on every multi-value field.** A `<select multiple>` stored only its first
  selected value. Both halves of the fix ship together, because sending the real list alone blanks
  the field entirely — `sanitize_text_field()` returns `''` for an array. (Spec 085, issue #148,
  DECISIONS #191)
- **A validated CV that was thrown away.** `ApplicationService::apply()` took a fourth
  `int $cvAttachmentId = 0` parameter no caller supplied, so `cv_attachment` was `0` on every
  application ever submitted. The applications table was also created, migrated and never registered
  as managed, so the rows went somewhere no admin surface reads. (Spec 081, issue #138 item 8)
- **Client-side form validation was shadowed by the browser.** Rendered forms had no `novalidate`,
  so a `required` field produced the native bubble and never the runtime's schema-mirrored
  validation, its error markup or its `aria-invalid` handling. (Spec 085, issue #148)
- **Validation messages could not be translated.** They resolved through `wp.i18n`, which needs a
  built JS catalogue that nothing in this repository generates — so every message stayed English on
  a translated site and nothing reported why. They now render into the form and go through the
  site's existing `.mo`. (Spec 085, issue #148)
- **A stored URL an operator could see and not open**, in the Data detail and the Submissions
  drawer. Only absolute `http(s)` becomes a link; `javascript:` and `data:` stay inert text.
  (Spec 085, issue #149)
- **A denial that named the wrong capability.** The denied surface claimed `manage_options` on every
  screen, which is false on Notifications, Submissions, Data Models and every option page — and a
  unit test asserted the string was present, holding the falsehood in place. (Spec 083,
  DECISIONS #188)
- **A loading spinner that did not spin** on any theme not defining two non-standard custom
  properties: an unresolved `var()` inside a shorthand makes CSS discard the whole declaration.
  (Spec 085, issue #148)
- **Errors that stayed on screen while being corrected**, and `corex:form:error` firing on the
  server failure path but not the client one. (Spec 085, issue #148)

### Changed

- **`COREX-EMAIL-ADDON.md` describes what exists.** It documented `attach()`, `attachMedia()`,
  `attachGenerated()` and an `AttachmentResolver` while `WpMailDriver` called `wp_mail()` with four
  arguments and could not send a file at all. Two are now built; the other two are recorded as not
  implemented, with the reason, rather than quietly deleted. (Spec 081)
- **Spec 082 is superseded by 084.** A Markdown manual in a docs tree can express CoreX's own guide
  and can never express a client's, which ships with the client's plugin. (DECISIONS #189)


## [0.37.0] — 2026-07-28

Four specs about the same thing: telling the truth on screen. Spec 076 gave the admin one date and
time contract, 077 made the Operations & Security screen say what it will actually do before it does
it, 078 replaced a four-line cache command with a classified inventory that cannot delete a security
control, and 079 fixed an access request that succeeded without anyone being able to tell.

Each spec began by reproducing the defect on a running install. Three times that changed the work:
what looked like a bad error page in 079 was a *successful* request rendered as an operation
envelope; the "obvious" cache sweep in 078 would have reset brute-force protection; and a CoreX
address that did not exist was telling administrators they lacked access.

### Added

- **Access requests reach the administrators who decide them.** Pending requests are listed for real,
  naming who asked, for what, when and why, with working Approve and Deny. Previously
  `AccessRequestStore::pending()` had no production caller: the REST route and the screen both
  returned a hardcoded empty array, so a request was created, audited and notified — and no surface
  ever read the table, while the denied screen promised a review. (Spec 079, DECISIONS #177)
- **Cache & Performance**, reporting seven layers from real checks, with `wp corex cache:status`,
  `cache:doctor` and scoped `cache:clear`. Every entry is classified, and clearing walks declarations
  rather than matching patterns — a `DELETE … LIKE 'corex_%'` is not refactorable into the design.
  (Spec 078, DECISIONS #171–#173)
- **One admin date and time contract**, shared by PHP and JavaScript against a common fixture, with
  semantic `<time>` markup, the site timezone as the single source of truth, and relative times whose
  exact value is readable rather than hover-only. (Spec 076)
- **Mode disclosure on Operations & Security**: every operations mode says what changing to it will
  do, and the confirmation it requires, before it is applied. (Spec 077)

### Fixed

- **Requesting access no longer navigates the browser to a JSON document.** The form posted to
  `rest_url('corex/v1/access/requests')`; submitting it rendered `operation_id`, `state`,
  `affected_ids` and `audit_event_id`. The request *succeeded* — the person asking for help simply had
  no way to know. It now posts to an admin endpoint calling the same service, and the confirmation is
  read from stored state, so a refresh creates no second request and nothing can be faked in the URL.
  The REST route is unchanged and still answers JSON. (Spec 079, DECISIONS #174–#175)
- **A CoreX address with no screen behind it answered 403** and told the viewer — administrators
  included — that their role lacked `manage_options`, then offered a form to request access to a
  screen that does not exist. Now 404, with no capability sentence and no form. (Spec 079,
  DECISIONS #176)
- **`wp corex cache:clear` deleted one transient and reported success** for "Corex asset cache",
  under a name that implies all of them. (Spec 078)
- **A no-op operations-mode change reported "Saved".** It now says what happened. (Spec 077)
- **A login slug colliding with an existing page or route was accepted.** (Spec 077)
- **`lint:css` and `lint:js` now gate CI.** Their absence is how 44 stylelint errors entered
  unnoticed; the 38 outstanding ones are cleared and ESLint is scoped so the run terminates.

### Changed

- `wp_cache_flush()` is refused on sites with a persistent object cache, because it would delete the
  rate-limit counters and spent-captcha records that live there as transients. (DECISIONS #172)
- Dates across the admin now read as dates rather than as ISO strings or raw integers.

## [0.36.0] — 2026-07-27

Three specs about the same thing: making the admin do what it looks like it does. Spec 073 was a polish
and correctness pass, Spec 074 closed the gap between what the CoreX admin claimed and what it could
actually do, and Spec 075 turned Blog Pro from a read-only dashboard into a workspace you can act in.

No invented capability anywhere in the three. Several surfaces stop misreporting, three workspaces stop
being dead ends, and where a requirement appeared to need a new service or route, the constraint was
recorded instead — see DECISIONS #155–#162.

### Fixed

- **Blog Pro looked like a workspace and was a poster.** Its services, seven REST routes, and client
  state module were complete and tested; the screen used almost none of them. It rendered five
  read-only cards, called `useReducer` and threw the dispatch away — so the entire client state module
  was unreachable from the running app while its tests passed — and never called a single one of its
  own routes. It also computed analytics, editorial state, comments and sharing for whichever post
  sorted first and named that post nowhere, under a heading that read as a site-wide total. You can now
  choose the post (and link to it), move it through review with a note, and approve, spam, or trash the
  comments waiting on it (DECISIONS #161).
- **Reading a notification was treated as dealing with it.** "Requires attention" filtered on your own
  unread state, so opening a production-readiness blocker took it off the attention list while the
  blocker was still true. Whether something still needs somebody is now derived from the record's status
  and severity on the server, never from whether you have looked at it — and *read* and *resolved* are
  separate things with separate controls (DECISIONS #157).
- **A form registered in code was invisible to the framework.** Discovery read database flows only, so a
  form registered through `Corex\Forms\FormRegistry` reached the submission filters only if the site
  added a `corex_submission_filter_options` hook, and Forms & Flows never listed it at all. v0.35.1 made
  such forms *filterable once injected*; the framework now **finds** them. The filter still applies, after
  the merge, for anything the catalog cannot see.
- **Data → Import and Data → Migrations were permanent dead ends.** Every registered source declared
  `import_dry_run` and `migrations` false, so both tabs could only ever print "No registered model
  provides an import adapter." Managed tables can now declare writable fields, import aliases, validation
  and a migration path; `corex_subscribers` is the first to do so, with a real dry run → mapping →
  rejections → commit → audit path and preview → apply → history → rollback. A tab appears only when the
  actor may open it **and** an eligible source exists.
- **The Submission Inbox heading lines collided.** The eyebrow, title, and count fought each other with
  per-element margins that also collapsed differently once a translation wrapped a line. They are one
  stack with a token grid gap now, which cannot collapse.
- **The Data Models screen lost its icons.** It alone rendered an empty notification bell and dropped its
  brand mark and rail glyphs, because it echoed the shell through `wp_kses_post()`, whose allowed-tags list
  excludes `<svg>`. The CoreX admin chrome is already escaped at the point each dynamic value is interpolated,
  so filtering it again removed correct output rather than adding safety; the screen now echoes it directly,
  as every other CoreX screen does (DECISIONS #155).
- **Operations & Security showed two mode controls and a badge that could lie.** A client-side "mode preview"
  (target-mode selector, a *Type PRODUCTION* box, a maintenance checkbox) sat above the real, nonce- and
  capability-gated server form and applied nothing. The "Production readiness" badge read its blocker count
  from that preview, which reported zero for every mode but Production — so the header could say **Ready**
  while blockers were listed directly beneath it. The inert preview is gone, the server form is the only mode
  control, and the badge reads the readiness snapshot it was always describing (DECISIONS #156).
- **A submission's answers rendered as em dashes in the record detail modal.** The modal read fields off the
  top level of the record, but a submission nests every answer under `fields`, so the submitted content — the
  reason for opening the record — never appeared. The record is now authoritative over its own content
  (declared fields, then the nested pairs, then anything undeclared it still carries), and flat table records
  are unchanged.
- **The notification toolbar entry stated its count twice** ("Notifications, 7 unread 7"). The visible label
  is the plain word now, the count lives in the badge, and the counted phrasing stays on the accessible title.

### Added

- **Blog Pro tells you when it has nothing to tell you.** "No reading data yet" is now a different
  thing from a zero — a post nobody has opened and a post analytics has never seen called for opposite
  reactions and looked identical. Every panel names the post and the period it covers, and the
  editorial and comment states read as words instead of `ready_for_review` and `publish`.
- **"What this site can do" — a capability summary under the Data Models catalog.** Everything CoreX has
  registered and where it came from, what each model supports, which add-ons are running, which
  notification producers are wired, and anything selected but unfinished — a captcha provider with no
  keys, or a missing update endpoint — each linking to the screen that fixes it. Capability used to be
  discoverable only by walking into it. A fact carrying a credential-shaped key is dropped rather than
  redacted, because a redaction still tells a reader where to look (DECISIONS #160).
- **A notification says what it is, and what you can do about it.** The item now carries category,
  severity, source module, environment, when it last happened, how many times, and whether the condition
  is still live — with mark read, mark unread, snooze, dismiss, resolve, and its own primary action. It
  used to show a title, a body, and "Mark read". The header drawer and the screen share one component, so
  they can no longer tell different versions of the same record.
- **A state filter on the Add-ons screen.** Mutually exclusive, exhaustive All / Active / Inactive / Not
  installed buckets keyed on the same status that prints each card's badge, rendered as the shared CoreX tab
  strip with a real count on each tab, a distinct "no add-ons in this view" state, and a fallback to All for an
  unknown filter so a bad query string cannot render an empty grid that reads as "no add-ons".

### Changed

- **Blog Pro's share-click recorder and its unused analytics shaping were removed rather than wired
  up.** Recording a share click claims a *visitor* shared a post, and only the visitor-facing surface
  can honestly claim that; firing it from an admin screen would have written analytics nobody
  generated. The chart and top-posts shaping had no server sending it and never had. Both are gone
  with their tests — code that cannot be reached but whose tests pass reads as working software
  (DECISIONS #162).
- **The Notifications screen offers three views instead of eight.** Action needed · Updates · History,
  plus Preferences. Inbox, Requires attention, Assigned to me, Submissions, Security, and System are gone
  as tabs — category, severity, and assignment are refines that apply within whichever view you are in,
  which is what they always were. Snoozing moves an item to Updates rather than History, and it comes
  back on its own when the snooze elapses (DECISIONS #158).
- **A control you may not use is hidden rather than shown and refused.** "Mark resolved" appears only for
  actors holding the same ability the REST route enforces, so the interface cannot advertise a capability
  and then answer with a permission error (DECISIONS #159).
- **The Settings dropdowns and the Notifications severity filter are the approved CoreX control** rather than
  native `<select>` elements the OS draws its menu for (DECISIONS #141), and the severity filter shows
  translated labels instead of raw English keys.
- **Un-styled admin prose has a consistent baseline rhythm.** Zero-specificity `:where()` rules give
  paragraphs, headings, and lists a tokenized baseline margin that any component rule still overrides, so the
  same element is no longer spaced differently depending on the screen it appears on.

## [0.35.1] — 2026-07-22

A correction release. Three fixes, no new capability: a form filter that could not filter the forms a
developer registers in code, two rough edges on the login screen, and a release command that could not
reach the one file telling the documentation site which version it describes.

### Fixed

- **A form registered in code could be listed but not filtered**
  ([#114](https://github.com/MustafaShaaban/corex/issues/114)). The form filter on the
  Submissions Inbox and the Data explorer was built only from database flows, so a form registered through
  `FormRegistry` never appeared in it — and because the inbox matched `corex_flow_id`, which those
  submissions do not carry, it could not have been filtered even if it had. A site that registers its forms
  in code saw an empty dropdown above a list of submissions it could not narrow. Sites can now append their
  own entries through the `corex_submission_filter_options` filter, where `id => 0` means "this form has no
  flow row, match it by `corex_form_slug`", and both screens accept `slug:<form-slug>` alongside a numeric
  id. Injected entries are re-normalised rather than trusted: an entry with neither an id nor a slug cannot
  be matched by either screen, so it is dropped instead of rendering a row that filters to nothing. A
  filtered export honours a code-form selection too — it previously cast the value to an integer, quietly
  covering every form rather than the one that was asked for.
- **The login screen's remember-me row and language selector.** The checkbox sat flush against its label and
  tight under the password field; it is a spaced, vertically centred row now. Scoped deliberately through
  `#login form`, because WordPress sets `#login form p { margin-bottom: 0 }` — an ID selector that silently
  beat a class-only CoreX rule and dropped the bottom margin while letting the top one through. The language
  dropdown WordPress prints at the foot of the page (the control Polylang populates, when it is installed)
  had no rule at all and kept the browser's default chrome while every control above it followed the theme;
  it now matches the text inputs in height, border, radius and focus ring, and mirrors correctly in RTL. It
  stays a native `<select>` on purpose — a fourth boundary alongside the three in DECISIONS #141, since
  `wp-login.php` loads no admin bundle and `CorexSelect` does not exist there.
- **`wp corex version` now stamps the documentation site.** It covered 16 files and missed
  `docs-app/src/version.ts`, which declares its version in TypeScript rather than a plugin header or a
  `COREX_*_VERSION` constant, so neither existing pattern matched it. The failure was quiet — the release
  completes, every header is correct, and the docs site advertises the previous version with nothing to
  catch it. v0.35.0 shipped that way and was corrected by hand. The command stamps **17** files as of this
  release, and this is the first release stamped entirely by it.

## [0.35.0] — 2026-07-22

Spec 072 — the Notification Center and the Dashboard Command Center, plus the release where continuous
integration started telling the truth. CoreX can now say what needs your attention instead of leaving you to
find out by stumbling onto it. Alongside it, CI went from gating one test suite to four — and doing that
uncovered a set of defects nobody could have hit locally, including a build that could not run on Linux and a
setup script that could not complete on a fresh clone.

### Added

- **A Notification Center: CoreX can now tell you what needs your attention.** The framework already recorded
  what happened — activity, jobs, access grants, email attempts — but nothing said *this needs you*. A failed
  email, a submission assigned to you, a job that died or a readiness blocker left no signal you could act on;
  you found out by stumbling onto it. Notifications are recipient-aware and resolvable, deliberately not a
  second activity log: activity is the durable record of what happened, a notification is a targeted nudge for
  specific people. Eight producers feed it — new and assigned submissions, notification-email failure, Email
  Studio delivery failure, job failure, export ready, access request, login lockout, and readiness blockers —
  each publishing through one service rather than reaching into another module's tables. Repeat occurrences of
  the same condition merge into a single escalating item by dedup key instead of a hundred rows, a resolved
  condition reopens if it recurs, and one user dismissing a shared notification never resolves it for everyone
  else. Every read re-checks a single visibility predicate (`NotificationRecipient::canBeSeenBy`), so losing an
  ability stops you seeing what it granted; recipients are targetable by user, ability, assignment or category
  administrators, never by hard-coded WordPress roles.
- **Where it appears.** A keyboard-operable bell in the CoreX header on every screen, showing your real unread
  count and opening a focus-trapped drawer that returns focus where it came from; a full `CoreX →
  Notifications` screen with saved views (Inbox, Requires attention, Assigned to me, Submissions, Security,
  System, Updates, History) each a bounded server-side filter; an admin-toolbar entry off CoreX screens, which
  never appears at the same time as the header bell; and an *Attention Required* card on Overview beside Recent
  Activity. Access is governed by a new **Manage notifications** ability, which administrators inherit.
- **Per-category preferences, with a floor.** You can mute categories you do not want in-app, but security,
  system and operations are mandatory and cannot be switched off — enforced in the value object rather than by
  whatever happens to be stored, so a hand-edited record cannot silence them either. Preferences live in user
  meta rather than a managed table (DECISIONS #152).
- **The framework's first recurring job.** A daily WP-Cron retention sweep prunes notifications older than 90
  days, and only ones already resolved or expired — an unresolved condition is never pruned out from under you.
  Permanent audit evidence stays in Activity, not in unbounded notification history.
- **A Command Center on the WordPress dashboard.** Site operating state, your attention count and the readiness
  blocker count, each a navigation link into CoreX and never an action. It runs local checks only: rendering it
  makes no outbound HTTP request, which is asserted by a test rather than assumed. Two further widgets —
  *Attention* and a Development-only one — are opt-in under `CoreX → Settings → Dashboard`, are never registered
  for someone with no data to show, and the Development widget never appears outside Development.
- **`GET /corex/v1/notifications` and its two-tier gate.** Reading and acting on your own notifications needs a
  signed-in user and a REST nonce; resolving a shared condition additionally needs Manage notifications. The
  service re-checks visibility on every call, so one user can never touch another's.
- **Continuous integration now gates four suites instead of one.** Only the PHP unit tests ran before. CI now
  also runs the JS suite, the integration suite against a real WordPress it provisions itself (MySQL plus
  WordPress, wired the way `scripts/setup-wordpress.ps1` wires a developer machine), and the Playwright browser
  suite against that site served by nginx. Provisioning lives in one composite action shared by both WordPress
  jobs so the two environments cannot drift apart.

### Changed

- **Every pull request is checked, whatever its base.** The workflows filtered on `branches: [main, develop]`,
  so a stacked PR — one opened against another feature branch — ran no checks at all. GitHub renders "no checks"
  and "all checks passed" almost identically, so that read as green. Two PRs in this release had never been
  tested when they were queued for merge, and between them they carried eight real unit failures that had been
  recorded in the project's own notes as "pre-existing". They were not: `main` had none of them.

### Fixed

- **The admin bundle could not be built on Linux or any case-sensitive filesystem.** `src/admin/index.js`
  imported `../access/` and `../blog/` while the committed directories are `Access` and `Blog`. Windows and
  macOS resolve that; Linux does not. Since the built bundles are deliberately not committed — they are rebuilt
  from source — this meant no contributor on Linux, and no Linux build environment, could produce the admin
  JavaScript at all. It went unnoticed because the build had only ever run on Windows.
- **`scripts/setup-wordpress.ps1` could not complete on a fresh clone**, which is the one situation it exists
  for. It piped a here-string into `wp config create --extra-php`, and PowerShell 5.1 prepends a UTF-8 BOM when
  piping to a native command, so `wp-config.php` received an invisible character before its first `define(` and
  WordPress died on load three steps later, blaming WP-CLI. `wp config create` had exited 0, so the script's own
  guard saw nothing wrong. Existing installs never hit it because the script skips config creation when the file
  is already there. It also activated plugins in one alphabetical call, which fails because `corex-blocks` and
  `corex-config` require `corex-core` and WP-CLI activates in the order it is given, and it never checked an
  exit code — so a broken run and a healthy one looked the same. All three are fixed and verified by running the
  script end to end against an isolated install.
- **The integration suite no longer runs against no WordPress.** Its bootstrap required `./wp/wp-load.php`
  inside an existence check and simply carried on when it was missing — and `wp/` is not in the repository, so
  that is the state of a fresh clone. Tests that touched a core function died with a confusing undefined-function
  error, and any test that did not could still pass, making a green integration run that proved nothing. It now
  exits with a message saying what is missing and what to run instead.
- **Dependency advisories are back inside a bounded policy.** The audit had drifted to 49 findings with 25 of
  them unbounded, which failed the security gate on every pull request that touched a manifest. Compatible
  upgrades cleared 28 of them; the remainder are recorded as explicit, time-limited exceptions with their
  exposure, compensating control and upstream trigger, because the only fixes on offer were a downgrade of
  `@wordpress/scripts` or a major Astro migration that belongs in its own reviewed change.

- **A notification filter that was advertised and never applied.** `GET /notifications?status=read` was accepted
  at the REST boundary, validated against the status vocabulary, documented as a per-user status filter — and
  then ignored by every read, returning everything with a `200` and no indication the filter had done nothing.
  `NotificationStatus` likewise described itself as derived from the record plus the user's state row while
  nothing derived it, leaving each consumer to invent the precedence. There is now one derivation
  (`NotificationStatus::derive`) with the collisions pinned by tests — resolved outranks expired, which outranks
  a dismissal, then an unelapsed snooze, then read — the actor-scoped read applies it before pagination so the
  total and the page agree, and every item carries its derived status so nothing re-implements it. Two surfaces
  had been compensating for the gap: the drawer refetched unfiltered while its own mark-read removed items, so
  anything you read reappeared when you reopened it, and it disagreed with the bell beside it; and the unread
  count excluded only read and dismissed items while the list also excluded snoozed ones, so the badge could
  promise work the screen refused to show. Both now ask for the same derived status. "Mark all as read" was also
  marking snoozed items read, silently cancelling a reminder you had deliberately set.
- **Editing an email template no longer fails with "Something went wrong."** Two defects stacked. WordPress
  resolves a request's JSON body before its URL parameters, and the editor was posting the stored version's own
  `id` alongside the fields it edits — so a save aimed at one template looked up a *version* id as if it were a
  template, found nothing, and answered 404. Only templates that already had a saved revision could hit it,
  which is why it looked intermittent. The message never reached the screen because the client assumed
  `wp.apiFetch` resolves on an error status when core in fact rethrows the response, so every server error was
  replaced with a generic one. Both ends are fixed: route identity is now read from the path where nothing can
  shadow it (`Corex\Http\RouteParam`, applied across the Email Studio, Flows, Submissions and Data
  controllers), and the transport reads error responses instead of discarding them. Per-field validation
  messages the server was already sending now appear too.
- **A hidden `/wp-admin` renders the theme's 404 properly styled.** It was correctly routed but visually
  broken: `wp_common_block_scripts_and_styles()` returns early on `is_admin()`, so the response carried
  `theme.json` tokens and no block CSS at all — no per-block sheets, no `wp-block-library`, and
  `enqueue_block_assets` never fired. On a block theme whose `style.css` is metadata only, that is an unstyled
  page. Spec 069 documented this as unreachable; that is true of a different gate, while this one is hooked to
  `wp_enqueue_scripts` and runs long after the guard does. Measured on a real install: the hidden admin went
  from **46,587 bytes to 79,711**, against a genuine 404's **79,964** — visually identical, with computed
  typography, layout and header geometry matching exactly. It remains ~250 bytes apart rather than
  byte-identical, because `wp_should_load_separate_core_block_assets()` genuinely cannot be reached on an admin
  request, so this response gets the combined stylesheet where a front-end 404 gets separate ones
  (`Corex\Config\Security\LoginProtection\LoginRouteGuard`).
- **The site header no longer stretches edge to edge when its stylesheet loads as a file.** `.corex-header__inner`
  carried a `max-inline-size` rule with the same specificity as WordPress's constrained-layout rule, so which
  one won depended entirely on stylesheet order — inert on an ordinary page view, and wrong wherever the order
  inverts.

## [0.34.0] — 2026-07-20

Spec 069 — Admin correctness and login-hiding parity. A correction release. Login hiding now behaves the way the
reference plugin does instead of announcing itself, the Insights and Overview grids hold one coherent column
rhythm, and every selection control in the admin is the approved accessible component rather than a native
`<select>` whose dropdown the operating system draws and CSS cannot reach.

### Fixed

- **A hidden `/wp-admin` no longer announces itself.** With login hiding enabled, the endpoint returned the theme's
  404 — and printed `Function print_emoji_styles is deprecated since version 6.4.0!` into the body, which tells a
  scanner far more than the 404 conceals. WordPress unhooks that shim through a branch on `is_admin()`, and
  `WP_ADMIN` cannot be unset, so on a hidden admin request core inspected the wrong hook and the deprecated
  function ran. The shim is now moved to the hook core actually inspects, which both silences the notice and lets
  core enqueue the emoji styles a genuine 404 carries. Measured on a real install: a missing page returns
  **79,968 bytes**, the hidden `/wp-login.php` is **byte-identical**, and the hidden `/wp-admin` returns
  **46,587 bytes with no diagnostic**. The remaining difference is WordPress's per-block stylesheets, which no
  plugin can reach — `wp_should_load_separate_core_block_assets()` returns `false` on `is_admin()` before its own
  filter runs. Size comparison can therefore still reveal that the admin address is handled specially, but never
  where the login moved to (`Corex\Config\Security\LoginProtection\LoginRouteGuard`).
- **The dark-mode dropdown highlight, properly this time.** Two previous attempts tried to fix it in CSS. It has no
  CSS fix: the open menu of a native `<select>` is painted by the operating system, so no `option:hover` or
  `option:checked` rule reaches it. Every selection control in the admin is now `CorexSelect`, the in-DOM ARIA
  listbox the component inventory has listed as the approved Select since it was written. Unselected options sit
  in the muted tone and lift to full contrast on hover — the signal the design always carried in the text colour,
  which the previous implementation had moved onto a background barely distinguishable from the menu behind it.
- **Admin stylesheets were being served stale.** Three different cache-busting spellings had grown up across the
  screens, two of which never change when a stylesheet is edited — Insights sat on a hardcoded `1.1.0` through
  every restyle, so returning visitors kept the old sheet and each fix looked like it had not landed. All screen
  assets now version by file modification time (`Corex\Config\AdminUi\ScreenAsset`).
- **The Insights screen presented one grid.** The informational widgets were being placed inside a container that
  was itself a cell of the screen grid, so all five collapsed into a single narrow column beside the run cards.
  Cards and widgets are now siblings in one two-column grid, ordered by what needs attention rather than by the
  order they happen to be built in — an unconfigured integration used to sit above everything that had something
  to report.
- **The Overview held one column rhythm.** The status tiles used an automatic track count that changed with the
  viewport, so the row re-flowed to three or five uneven tiles at ordinary widths and drifted against the cards
  below. Fixed to the four tracks the approved design specifies. Cards in each row now fill their row rather than
  leaving a block of empty canvas beside a shorter neighbour.
- **The front-end form select was themed.** No rule anywhere targeted it, so it kept the browser's default chrome
  while every field beside it followed the theme. It now uses the theme's own colours and spacing, with the
  dropdown following the page's light or dark scheme.
- **Two long-standing test failures** that were time-dependent rather than real regressions: one seeded a page view
  at a fixed date and then asked for "the last 7 days"; the other assumed its fixtures would stay on the first
  page of results regardless of how much real activity the install had accumulated.

### Changed

- **One Data destination.** The standalone Data screen rendered the same explorer as the Data Models Records tab;
  it is retired and its address redirects there, with every tab now deep-linkable by URL.
- **Filter submissions and records by form name.** Both screens asked for an identifier nobody knows — a numeric
  flow ID in one, a typed slug in the other. Both now offer the real form names. Forms remains an optional add-on:
  when it is absent the filter simply does not appear.
- **Data models are an accordion**, so a long model list stays scannable.
- **Selection controls report the same accessibility role as the native control they replace**, so assistive
  technology sees no change. Block-editor controls are deliberately unchanged: they render in the WordPress editor
  sidebar, where the editor's own design language is the correct one.

## [0.33.0] — 2026-07-03

Spec 065 — Admin product completion. The required completion pass after Spec 063 (truthful surfaces) and Spec 064
(partial Overview fidelity): every admin surface now shows real data/state or an honest empty/error/unavailable
state — no safe feature left as a vague "future" placeholder. Company-site recommendations are paused; only
WooCommerce, advanced AAM / full capability-editor, and commercial/Pro/marketplace/licensing remain deferred. The
truthfulness invariant is unchanged — real behaviour or an honest state, never a fabricated one.

### Added

- **Operations Mode** — real, safe operating-mode switching (development / staging / production / maintenance),
  persisted, capability + nonce gated, with confirmation for production and maintenance, an Overview + Operations
  badge, a mode-change audit log, and mode-specific warnings. Maintenance mode shows an accessible 503 to anonymous
  visitors but **never locks out a signed-in administrator** and renames no WordPress core files
  (`Corex\Config\Operations\*`).
- **Submission retention** — a real retention window with a **real dry-run preview** of how many submissions are
  older than it, and a capability + nonce + confirmation-gated prune that moves records to trash (recoverable) —
  never a do-nothing setting, never a deletion without a preview (`Corex\Config\Retention\*`).
- **Data Models — CSV import dry-run + migration overview** — a real CSV import dry-run that validates an uploaded
  file against a model's columns and reports accepted/rejected rows + unknown columns while writing nothing, and a
  truthful migration overview from the real managed-table registry. Committing an import is gated with the exact
  reason (the read-only data sources expose no write adapter) (`Corex\Config\DataModels\DataImportValidator`,
  `DataModelsImportController`).
- **Access & Abilities baseline** — a new read-only screen with a real role × capability matrix (real WordPress
  roles × the capabilities CoreX actually checks), the current user's permissions, and the `manage_options`
  requirement per CoreX area. Advanced AAM / a full capability editor remains deferred (`Corex\Config\Access\*`).
- **Blog** — a designed reading experience across `single`, `archive`, and `index`: category + author/date meta,
  featured image, content, tags, a real `corex/social-share` bar, a `corex/newsletter-signup` CTA, and a "More from
  the blog" grid; the archive/home use a post-card grid with a real no-results empty state and pagination.

### Changed

- The Overview environment badge now reflects the declared operations mode (falling back to the WordPress
  environment type when undeclared).
- Docs corrected: ROADMAP §17 + PROGRESS + DECISIONS #112 reframe the Spec 063/064/065 milestones and remove all
  company-site next-step recommendations. Portfolio's exact next scope is recorded (planned after Blog).

## [0.32.1] — 2026-07-02

Spec 064 — Admin design fidelity. A corrective pass after an owner review of the v0.32.0 admin: the Overview was
visually unfaithful to the approved design, confusing, and full of unintended white space. No new features; the
truthfulness invariant is unchanged (real state or honest empty/gated — no fakes).

### Fixed

- **Overview rebuilt to the approved readiness grid** (`Corex Admin Overview.dc.html`): a single
  `Corex\Config\Overview\OverviewRenderer` + pure `OverviewModel` now render a dense two-column dashboard — stat
  tiles (posts/pages/submissions/add-ons), a Launch-readiness checklist (N of M) from real brand/kit/front-page/
  mail/captcha/hardening signals, an Analytics & Security panel with honest connected/not-connected chips, a real
  Data-sources summary, a Forms summary, and an honest empty Recent-activity state. This replaces the previously
  stacked site-status + control-panel + activity panels, **removes a duplicated submission read-out, and fixes the
  unintended white space** with a dense grid layout. Verified dark + light against a live install.
- **Admin rail navigation:** every registered CoreX screen (including the Spec-063 Forms, Submissions, Email Studio,
  Data Models, and Operations & Security screens) now has a distinct icon and a correct active state — no generic
  option-page fallback and no dead entry point.
- Fixed a double-encoded `&amp;` in two Overview `esc_html__()` strings ("Analytics & security", "Forms & Flows").

### Changed

- Extracted `Corex\Config\Security\HardeningFacts` so the Operations & Security screen and the Overview compute the
  WordPress hardening signal one way (DRY).
- Removed the superseded Overview panel renderers (`SiteStatusCardRenderer`, `SiteStatusCard`, `ControlPanelView`,
  `OnboardingChecklist`, `OnboardingStep`) and the pure `OverviewSummary`, now that the single `OverviewRenderer`
  produces the whole dashboard. The `tests/e2e/render-admin.mjs` harness now covers all six new admin screens.

## [0.32.0] — 2026-07-02

Spec 063 — New Design Gap Implementation. Closes the implementation-ready gaps from the "Corex Final Design
Gap-Closure" design package under one invariant: **every surface communicates its real state — no fabricated data,
integrations, Pro/marketplace/licensing behavior, and no dead entry points.** Where a design feature has no backing
in the framework it is surfaced as an honest, labelled future capability, never a fake.

### Added

- **Truthful CoreX Overview summary:** an "At a glance" strip built only from real signals — environment/mode badge
  (`wp_get_environment_type()`), add-on active/total, form submissions (honest "not available" when the source is
  off, never a fabricated zero), media delivery, a readiness pointer, and a documentation link
  (`Corex\Config\Overview\*`).
- **Forms & Flows admin screen** (`corex-forms`): a read-only inventory of the real code-defined forms
  (`FormRegistry`) and their fields/validation; the visual builder is labelled a future capability
  (`Corex\Config\Forms\*`).
- **Submissions Inbox** (`corex-submissions`): a business-friendly view over the real `corex_submission` records —
  list + a server-rendered detail view (`?submission=ID`) + a capability + nonce-gated CSV export; honest
  empty/not-found/permission states (`Corex\Config\Submissions\*`).
- **Data Models catalog** (`corex-data-models`): a schema catalog over the real `DataRegistry` sources (fields +
  record counts) + per-model CSV export. CSV import (with dry-run) and a pending-migrations view are honestly
  deferred — the data layer has no generic write path or migration-history tracker (`Corex\Config\DataModels\*`).
- **Operations & Security overview** (`corex-operations-security`): the real environment plus real WordPress
  hardening checks (HTTPS, `DISALLOW_FILE_EDIT`, debug-display hidden, no default "admin"). Operations-mode
  switching, login protection, and a capability editor are labelled future — CoreX never renames WordPress core
  files (`Corex\Config\Security\*`).
- **Email Studio** (`corex-email-studio`): a truthful overview of the transactional-email engine — gated on the
  optional CoreX Email add-on, the real registered templates (`TemplateRegistry::names()`, added), and an
  environment-derived delivery advisory (`Corex\Config\Email\*`).
- **`corex/social-share` block:** a privacy-friendly, permalink-aware Blog share bar. Links work without JavaScript;
  copy-link (Clipboard API) and native-share (Web Share API) are progressive enhancement. No share counts, no
  third-party scripts; accessible, RTL, reduced-motion aware.
- **`corex/newsletter-signup` block:** a double opt-in signup form wired to the real
  `corex/v1/newsletter/subscribe` REST route (CoreX Newsletter add-on). Gated on the add-on, required consent + an
  accessible honeypot, and the endpoint's truthful "check your email to confirm" outcome — no fabricated success.
- **Company section patterns:** `corex/section-services-grid`, `corex/section-process-steps`,
  `corex/section-logo-cloud`, and `corex/section-contact-info` — native FSE patterns (core blocks + `theme.json`
  tokens), neutral placeholder content, RTL-correct, auto-registered under the CoreX pattern category.
- **Docs:** a new docs-app page *Design gap surfaces (Spec 063)* documenting every new admin screen, block, and
  pattern; the design intake at `design/handoffs/063-new-design-gap-implementation.md`; and Spec 063 in
  `specs/063-new-design-gap-implementation/`.

### Notes

- All new admin screens are gated by the shared `AdminGuard` (capability + nonce for any write), load assets only on
  their own hook (Principle VI), convey status by text + tone (never colour alone), and support RTL, dark, and light.
- Optional add-ons (Media, Captcha, Email, Newsletter) are detected behind a seam and degrade to an honest
  "unavailable" state when inactive (Principle IX). Secrets remain write-only. WooCommerce stays dual-gated.
- Future-only areas (Blog Pro, Portfolio, WooCommerce, Pro/commercial, Auth, advanced AAM) remain deferred, not
  built.

## [0.31.0] — 2026-06-26

### Added

- **Curated CoreX font collection for the WordPress 7 Font Library (spec 062 Priority 2):**
  `Corex\Assets\FontCollection` registers the framework's self-hosted brand typefaces — Space Grotesk, JetBrains
  Mono, and IBM Plex Sans Arabic (all OFL) — as a **CoreX** collection in Appearance → Fonts, installable in one
  click via `wp_register_font_collection`. Optional editor tooling; the production path for client brand fonts
  stays source-controlled fonts in the client theme's `theme.json`.

### Changed

- **CoreX UI image blocks opt into optimized WebP delivery (spec 062 Priority 2):** the Hero, Gallery, and Team
  block renderers now pass their image through the `corex_media_optimize_image` seam, so when Corex Media is active
  and a *gated* WebP sibling exists they emit a `<picture>` (and the original `<img>` otherwise) — with **no hard
  dependency** on the add-on. `PictureRenderer` preserves the block's `class` + `loading`, and each block's styles
  set `picture { display: contents }` so the optional wrapper never changes layout.

## [0.30.0] — 2026-06-23

### Added

- **Pre-site asset & media hardening (spec 062):** the minimum-safe asset + media foundation for building a real
  company website.
  - **First-class asset helpers** — `Corex\Assets\Style` / `Script` / `Image` / `Picture` (facades over the
    `AssetManager`/`BuildManifest`, with an `AssetRegistry` for named theme/plugin/client bases). They resolve
    URL + cache-busting version (manifest-hashed when present), support deps/in_footer/defer|async/module/version
    and a sibling wp-scripts `*.asset.php`, render source-controlled `<img>`/`<picture>`, and **refuse a `.scss`**
    passed to an enqueue helper (SCSS is source only — the compiled CSS is enqueued).
  - **Generated client SCSS/JS/image pipeline** — `make:site --starter` now scaffolds
    `assets/src/{scss,js,images}/` → `assets/{css,js,images}/` with `styles`/`scripts`/`images`/`build` npm scripts
    (project-local `sass` + wp-scripts) and a `functions.php` that enqueues the compiled output through the CoreX
    asset helpers — never hardcoded paths.
  - **WebP activation gate** — a WebP is no longer served just because it exists: after conversion the derivative
    is measured and served only if it is present + valid, dimensions match, and it is smaller than the original by
    at least a configurable threshold (default 5%). The result is tracked per attachment (`_corex_webp` meta), and
    a WebP that came out larger is generated but quietly not served.
  - **WebP reset/cleanup** — `wp corex media reset-webp [--dry-run] [--all] [--attachment=<id>] [--limit=<n>]`
    deletes only tracked CoreX-generated derivatives (never originals, manually-uploaded WebP, or untracked files),
    clears the meta, and reports counts; deleting an attachment removes only its tracked derivative.
  - **Dist client-asset verification** — `verify:dist` flags a half-built client theme (SCSS/JS source present but
    no compiled `assets/css`/`assets/js`), so a deploy can't package an unbuilt theme.
  - Docs draw the line: `Corex\Assets\*` is for source-controlled theme/plugin/client assets, `Corex\Media\*` is
    for Media Library uploads.

## [0.29.0] — 2026-06-23

### Added

- **Team-safe company-site readiness (spec 061):** CoreX is now safe for a team (and AI agents) to build real
  client sites on.
  - **Role Gate** — every session classifies into one of four modes (CoreX Framework / Client Site / Deployment /
    Docs-Planning) that decide *where* it may edit, documented in root `AGENTS.md` / `CLAUDE.md` /
    `COREX-WORKING-GUIDE.md` §G, the team-workflow docs, copy/paste start prompts, and the generated client stubs.
    Rule hierarchy: Role Gate (where) → Spec Kit (what) → Guard Gate (safe to ship) → UI/UX ProMax (UI good
    enough). A standard SUMMARY/…/NEXT STEP handoff format is required of every response.
  - **Team source layout** — framework source stays in `plugins/`/`addons/`/`packages/`/`theme/`/root `specs/`/
    `docs/`/`docs-app/`; client source lives in `sites/<client>/` (`<client>-site` + `<client>-theme` +
    governance + specs/docs). `wp/wp-content/` and `dist/` are runtime/build output, never edited as source.
  - **Shared-host `dist` builder** — `npm run build:dist [-- --client=acme] [--dry-run]` assembles a flat,
    deployable WordPress tree in `dist/` from repo source (de-symlinked), excluding dev/runtime/secret paths, with
    a `corex-release.json` manifest; `npm run verify:dist` checks it. `dist/` is git-ignored, never committed.
  - **Azure Pipelines** (`azure-pipelines.yml`) — builds `dist/` + publishes the artifact, then an approval-gated,
    parameterised SFTP deploy stage (secrets only, production runtime files protected). GitHub Actions stays the
    PR/quality gate. Deployment docs + checklists under `docs/en/05-deployment/`.
  - **CoreX Media settings + regeneration** — a Media settings panel (enable WebP, quality, JPEG/PNG toggles, a
    live GD/Imagick/WebP/uploads-writable read-out; originals always preserved) with filter seams + sanitization;
    `wp corex media regenerate-webp [--dry-run] [--limit] [--attachment]` backfills WebP siblings (never deletes
    originals); a `corex_media_optimize_image` delivery seam blocks can opt into for `<picture>` output.
  - **`make:site` flat client layout** — generates `<slug>-site/` + `<slug>-theme/` directly under the output dir
    (so `--path=sites/acme` → `sites/acme/acme-site` + `acme-theme`), with header/footer/front-page override
    scaffolding (brand-via-tokens vs structure-in-client-theme) and, in `--starter`, a project-local image
    pipeline (`assets/src/images/` → built `.webp` via `npm run images`). Older nested sites keep working.

## [0.28.0] — 2026-06-22

### Added

- **CoreX admin design implementation landed (spec 060, milestone M6 — merged via PR #59):** the approved CoreX admin
  design is now applied end to end and render-verified (Playwright, dark + light) against the design captures:
  - **Real `wp-login.php`** carries the CoreX login design for logged-out users — branded mark/wordmark, ambient
    grid + glow, a separate SSO slot (truthfully disabled, "SSO is not configured yet.") above the form card with an
    "or" divider, leading user/lock field icons with the password reveal kept on the right, a brass sign-in button,
    and entrance/focus motion that respects `prefers-reduced-motion`. The login is dark-first; the saved CoreX
    appearance (System/Light/Dark) controls it for logged-out users. WordPress still owns all authentication.
  - **Every CoreX admin screen** (Overview, Settings, Add-ons, Data, Insights, Setup Wizard, and declarative option
    pages) uses the shared, full-bleed shell — the dark product surface fills the wp-admin content area with the
    six-item `COREX FRAMEWORK` rail; scoped to CoreX screens only, no global wp-admin restyle.
  - **Data explorer:** the Sources/Models rail drives the active source, a real per-day 14-day records chart (from
    submission timestamps, zero-filled), a derived field schema, search/source-filter/reset/export, row selection
    with a bulk delete (nonce-confirmed), an accessible record drawer (focus trap, Escape, focus return), and a
    designed table. Provider-/value-aware controls use an accessible custom listbox readable in dark mode.
  - **Settings tabs** (Brand → Mail → Forms → Captcha → Insights) with a live admin appearance setting, admin-logo
    preview, footer-text value, and an SSO-slot toggle. Asset CSS/JS is filemtime-versioned so edits bust the cache.
  - **Captcha settings are provider-specific** and reactive to the selected driver — **None** (disabled notice),
    **Honeypot** (no-key spam-trap, no keys shown), **reCAPTCHA** (site/secret keys + v3 score/action + Google
    references), **hCaptcha** (keys + hCaptcha references), and **Cloudflare Turnstile** (keys + Cloudflare
    references); each driver shows only its own fields, descriptions, and official links. Secrets stay write-only.

### Fixed

- **Render-verified CoreX admin visual fidelity (spec 060, milestone M6):** every CoreX admin surface was rendered
  (headless Chrome, authenticated, dark + light) and compared against the approved design captures, exposing defects
  the earlier source-only pass missed. Form inputs no longer render with WordPress's white field background and
  buttons no longer render in WordPress blue — controls (inputs, selects with a single RTL-aware chevron, textareas,
  primary/secondary and Gutenberg buttons) are now styled with specificity that wins over core wp-admin CSS, with a
  brass focus ring and dark input wells. Card and section headings take an explicit CoreX text colour (a contrast/WCAG
  fix on dark surfaces). The `--corex-admin-*` adapter (dark + light) was realigned to the approved tokens, the Data
  table gained monospace uppercase headers and themed pagination, and the login screen gained an ambient grid backdrop
  with a brass checkbox and a muted reveal control. Truthful-state, security, and markup contracts are unchanged.

### Added

- **Company-site readiness & onboarding:** a central docs URL resolver (`Corex\Config\Docs\DocsUrl`) so the
  Add-ons screen's "Documentation" links resolve to an absolute URL — the `docs.base_url` config key (filterable
  via `corex_docs_base_url`), else the framework's canonical GitHub docs source — and never resolve against the
  active client WordPress domain. Add-on **tier badges** (Recommended / Optional / Site kit / Requires
  WooCommerce) plus a "Where to start" note (the always-on foundation: corex-core/blocks/config/forms) make
  add-on choice clear without weakening the truthful state model. New onboarding docs: an end-to-end *Start your
  first company site* guide and a *Using AI agents safely* guide, named-local-site (WAMP) + safe-reset, add-on
  tiers, what-each-layer-owns (CoreX UI vs Company Kit vs parent theme vs generated client theme) + header/footer
  ownership, the WordPress 7 Font Library strategy, a flat-`dist/` deployment warning (never ship the symlinked
  `wp/`), a "Start here" README map, and docs-app-is-optional guidance. Neutral *Acme* placeholders throughout.

- **CoreX Admin Product Experience (spec 060, milestone M6):** the shared CoreX wp-admin now presents a truthful
  add-on/settings state model. `AddonStatus` + `AddonStatusResolver` resolve every add-on to one of seven honest
  states (not installed / inactive / feature off / dependency missing / WooCommerce missing / Pro required / active);
  the Add-ons screen renders each as an accessible labelled badge and offers enable/disable for installed add-ons
  only (no marketplace/install-from-admin). Settings sections reflect the same model — not-installed sections are
  hidden behind a notice, inactive sections are disabled, active-but-unconfigured sections prompt for configuration —
  with reCAPTCHA as the worked example, and **secret fields (captcha secret, API keys) are now write-only** (never
  rendered back; an empty submit preserves the stored secret). The admin styling consumes only the scoped
  `--corex-admin-*` adapter and loads only on CoreX admin screens — no global wp-admin restyle, no public-frontend
  branding. Docs: design-system → Admin experience.

- **Corrective full CoreX admin visual implementation (spec 060, milestone M6):** PR #58 is recorded as the
  truthful-state foundation, not the completed visual rollout. The corrective implementation applies the approved
  design across native WordPress login, Overview, Add-ons, Data, Settings, Setup Wizard, and Readiness/Insights. It
  adds the `COREX FRAMEWORK` grouping, shared page shell/headers, stat and add-on cards, settings sections, data
  tooling/states, readiness cards, setup progress, and visible permission-denied/empty/error/success/warning/
  disabled states. The layer is dark-first with a complete light mapping, responsive, RTL-first, keyboard/focus
  visible, reduced-motion aware, and allow-listed to CoreX admin/login assets only.

- **Company Site Kit v1 page coverage (spec 059, milestone M4):** the `corex-kit-company` `CompanyBlueprint` now
  provides the full v1 content-page set (Home, About, Services, Single Service, Work, Case Study, Industries, FAQ,
  Blog, Team, Testimonials, Locations, Contact, Privacy/Terms/Cookie, Maintenance), composed only from registered
  `corex/*` patterns + the M3 header/footer + core blocks (token-only, RTL, accessible). System surfaces stay on the
  universal templates. Demo content levels (`minimal`/`standard`/`full`) keep the same structure with varying depth;
  each page carries editable, plugin-compatible SEO starter metadata. Applied through the existing preview/apply
  provisioning with safe `reset`/`adopt`/`skip`/`conflict` handling. Section blocks with no dedicated pattern yet
  (services/team/case-study/locations grids) are the recorded M5 batch. Docs: guides → Company Site Kit v1.

- **Header, mobile navigation, mega menu, and footer system (spec 058, milestone M3):** reusable FSE template parts
  and block patterns — six header variants (`corex/header-*`), four mega menus (`corex/megamenu-*`, native
  `<details>` disclosures), and six footer variants (`corex/footer-*`) — built only from WordPress core blocks and
  the M2 brand tokens, registered under a new **CoreX** pattern category. The core navigation block supplies the
  accessible mobile overlay; a small buildless behavior script adds single-open mega menus, Escape/outside-click
  close with focus return, and a transparent→solid sticky-header state. Keyboard/focus/Escape/outside-click,
  WCAG 2.2 AA focus, RTL (logical properties), `prefers-reduced-motion` gating, and a no-JS fallback throughout.
  Assets load only where a CoreX header/footer renders (Principle VI); three layout-only `theme.json` custom tokens
  added, no new brand values. No builder, commerce logic, kit pages, or Pro scope. Docs: design-system → Navigation
  & footer.

- **CoreX brand tokens and logo system (spec 057, milestone M2):** canonical `theme.json` color/typography token
  vocabulary with accessible dark/editorial style variations and RTL/mixed-script typography; a four-file self-hosted
  font package (Space Grotesk, JetBrains Mono, IBM Plex Sans Arabic) with provenance, `font-display: swap`, and no
  unmeasured preload; the approved Core X logo package (five SVG variants + provenance manifest); a `brand.json`
  brand-override validator that enforces complete wholesale list replacement with one-release compatibility aliases;
  a scoped `--corex-admin-*` admin token adapter loaded only on CoreX admin screens; and updated design-system and
  branding documentation. Client brandability is preserved — CoreX product identity does not become a hardcoded
  client-site identity. Merged via PR #54.

## [0.27.0] — 2026-06-19

### Added

- **Exposure-aware dependency security (spec 056):** `npm run verify:dependencies` audits Composer, root npm, and
  docs-app npm together, validates exact expiring development-tool exceptions, fails closed on unavailable audit
  evidence, and is enforced by a weekly/on-demand/dependency-change GitHub Actions workflow. High or critical
  shipped-runtime/CI findings cannot be excepted; forced audit downgrades remain prohibited.
- **Stable client readiness (spec 055):** `wp corex readiness` now reports add-on runtime gating, release metadata,
  CI/security repo-file controls and environment-gated GitHub settings, `make:site` validation, deployment profiles,
  native-first component coverage, Free/Core vs Pro boundaries, and multi-agent workflow readiness with exact
  evidence and next actions.
- **Runtime add-on gating:** optional first-party providers are resolved before boot from shared provider metadata,
  activation state, dependency state, installed-file checks, feature flags, and external gates. Disabled or missing
  optional add-ons are excluded before they can register unsafe behavior; Woo remains gated by both Corex state and
  WooCommerce availability.
- **Client-site readiness checks:** generated minimal and starter `make:site` scaffolds are validated for isolated
  client plugin/theme folders, namespace and prefix separation, governance files, specs/docs placeholders, token
  strategy, and starter example files.
- **Deployment and component matrices:** deployment profiles distinguish local passable checks from environment-gated
  shared-host, Azure/container, Docker, and wp-env checks; company-site UI needs are classified by native Corex or
  WordPress mechanisms without starting the final Corex visual redesign.
- **Product boundary matrix:** adoption and trust basics stay Free/Core, while advanced newsletter, bookings,
  careers/ATS, Woo, email/media/data automation, white-label, starter-kit, Azure/DevOps, governance dashboard,
  multi-company identity, and client portal scope are Pro candidates.
- **Repo-owned security controls:** CODEOWNERS, Dependabot, and CodeQL workflow files are present. GitHub branch
  protection, the required CI context, Dependabot security updates, and secret scanning were verified before this
  release.

### Notes

- Release verification passed Composer validation, PHP lint across 392 files, 620 Pest tests with 2239 assertions,
  88 Jest tests across 16 suites, all workspace builds, the 39-page docs build, dependency-policy verification, and
  local WordPress readiness against the v0.26.1 baseline.
- Docker/wp-env, browser automation, and external deployment evidence remain explicitly environment-gated. They are
  not represented as passing release checks.

## [0.26.1] — 2026-06-15

### Fixed

- **Junctioned add-on block assets 403 in the editor (spec 040 gap):** add-ons loaded via Corex's Boot provider list
  rather than WordPress's `active_plugins` (e.g. `corex-careers`, `corex-kit-portfolio`) emitted malformed block
  asset URLs (`…/wp-content/plugins/C:/…/addons/…/style-index.css`) on a symlinked/junctioned dev or CI layout,
  because WordPress only learns a symlinked plugin's real location for plugins it activates itself. A new
  `Corex\Blocks\PluginRealpathRegistrar` replays `wp_register_plugin_realpath()` for every junctioned mount at boot,
  so `plugins_url()` resolves correctly for every add-on. Caught by the spec-052 console-error sweep on its first
  live run; the env-gated Playwright suite was also hardened (WP 7.0 inserter selector, native-validation-aware
  contact-form assertions, `storageState` auth, deterministic editor-ready waits). DECISIONS #90.

## [0.26.0] — 2026-06-14

Closeout + design language — reconciling the platform claims with the code (spec 053) and turning the thin DLS
catalog into a full, native-first Design Language System (spec 054).

### Added

- **make:site `--starter` slice (053):** `wp corex make:site --starter` scaffolds a runnable example slice
  (model → repository → service → controller-on-envelope → block → option → test + `REMOVE-EXAMPLE.md`) plus a
  starter block theme (`@wordpress/scripts` build, `Assets` helper); `--minimal` and the default omit it.
- **Data admin UI (053):** the `corex-config` Data screen now ships search, source/form filter, sortable headers,
  pagination, a CSV-export button, a detail drawer, and loading/error/empty states over the existing query/detail
  routes (built on pure `dataClient.js` helpers).
- **Captcha Test button (053):** `corex-captcha` ships `captcha-admin.js` wiring the Test button to
  `POST /captcha/test` with a classified, secret-safe result message; Insights "Run check" surfaces failures.
- **Foundations tokens (054):** `theme.json` gains the genuinely-missing `custom.motion` (duration + easing),
  `custom.focus` (width/color/offset), and `custom.z` (z-index scale) groups as runtime CSS custom properties.
- **`corex/modal` block (054):** the one justified new block — a native `<dialog>` with `aria-labelledby`,
  focus-trap, ESC/backdrop close and focus return; degrades without JS; token-only (consumes the focus + z tokens).
- **DLS block styles + skeleton (054):** `register_block_style` entries for card / section / striped-table /
  button-secondary / button-ghost / empty-state, plus a token-only `.corex-skeleton` loading utility.
- **Patterns + page templates (054):** section-header, content-split, stats, FAQ, and latest-news patterns
  (composing only real blocks, drift-tested) + `page-landing` / `page-contact` / `page-form` FSE templates.
- **Design-system documentation (054):** the `DesignSystemCatalog` expanded to the full six-category taxonomy with
  a `mechanism` field (drift-checked both directions), a published gap analysis, and a docs-app design-system
  section (foundations + a page per component with when-to-use / when-not-to-use + patterns + templates).

### Changed

- **Honest README + reconciled status (053):** the README was rewritten as a truthful entry point and stale
  PROGRESS / 045 / 049 completion checkboxes were corrected; a documentation-in-every-PR rule (§D.5) was added.

### Notes

- 563 Pest + 55 Jest green; docs build 268 pages. Guard Gate clean across both specs. DECISIONS #87–#88. The
  native-first finding (most candidate "components" are WordPress core blocks or Corex block styles, not new
  blocks) is the deliberate scope outcome. The modal's visual a11y sweep runs in the spec-052 E2E workflow.

## [0.25.0] — 2026-06-14

The "platform" release — the leap from a framework to a platform you build client sites on with a team + AI agents
(roadmap specs 043–052, all merged via PR/CI).

### Added

- **Response contract + frontend runtime (043):** `Corex\Http\ResponseEnvelope` + `EnvelopeResponder` and a
  buildless `window.Corex` runtime (api/forms/loading/notices); forms + Insights + Data migrated onto it.
- **Admin control panel (044):** per-domain status cards + an onboarding checklist; captcha config + a Test
  verification action; specific PageSpeed diagnostics (local-URL detection); rich add-on manifests; authorship.
- **Data management pro (045):** search / filter / sort / paginate, CSV export, a readable detail view, and a
  `SubmissionStore` storage seam.
- **REST resources & headless (046):** `wp corex make:api-resource`, `routes:list`, `api:docs` (OpenAPI), headless
  docs (nonce / application-password auth).
- **Asset manager & environments (047):** `AssetManager` url/path/version helpers with per-environment cache-busting
  (filemtime / manifest hash / version) + `assets:doctor` / `cache:clear`.
- **Media optimization (048):** an optional `corex-media` add-on — WebP on upload (original preserved) + an
  optimized `<picture>` helper (lazy / async / LCP / srcset) + an advisory image-support probe.
- **make:site client-site platform (049):** `wp corex make:site` scaffolds a correctly-namespaced client plugin +
  theme + governance (AGENTS/CLAUDE, the client-only edit boundary, one-feature-one-PR, AI/cache `.gitignore`).
- **Team ops & distribution (050):** `compliance:check` (enforces the client/framework boundary), `package:update`
  (the spec-034 release manifest), `docs:sync` / `docs:serve`, and Azure DevOps deployment docs.
- **Design Language System (051):** a drift-checked catalog (Components/Blocks/Patterns/Templates/Guidelines) in
  `corex-ui` + new `corex/alert` + `corex/badge` components.
- **Visual & E2E in CI (052):** a Playwright E2E workflow + a console-error sweep (the item-20 gate) + the
  browser-verification Definition of Done.

### Notes

- 544 Pest + 40 Jest green; Guard Gate clean across all ten specs. DECISIONS #77–#86. Browser/live behaviour runs in
  the new E2E workflow (nightly + on-demand) and locally via wp-env.

## [0.24.0] — 2026-06-13

Connectivity — making kit activation visible and transparent, from a user-driven deep review that found the
framework "felt disconnected" (enabling kits seemed to do nothing; submissions were hard to find).

### Added
- **Prompt-to-apply kit activation** (spec 042): enabling a kit add-on (Corex → Add-ons) now surfaces a
  dismissible prompt previewing exactly what applying would do (pages created / filled in / left unchanged, the
  front page, the modules) — **read-only** until you choose **Apply**, which runs the one shared apply path and
  shows a "what changed" summary. A corex-core `KitProvisioner` seam (with a `NullKitProvisioner` default) lets the
  Add-ons screen and dashboard drive activation without depending on a kit add-on (Principle IX).
- **Corex dashboard "Site status" card** (spec 042): shows which kits are applied, the live contact-submission
  count linked to **Corex → Data**, and the current front-page status — with an actionable empty state, degrading
  gracefully when the forms add-on or kit framework is inactive.
- **Block-assets health check** (spec 040): `BlockAssetsProbe` flags any registered `corex/*` block whose
  script/style URL embeds a filesystem path, in **Site Health** and `wp corex doctor`, so a misconfigured mount is
  diagnosed instead of showing as a blank editor panel.

### Fixed
- **Kit apply never leaves a blank front page** (spec 041): page seeding now classifies each declared page
  **create / adopt (populate an empty or placeholder page) / skip (existing user content)** instead of skipping
  any slug that already exists, and the front page is set whenever the declared home was created or adopted. A
  soft reset deletes pages the kit **created** but only **empties** a page it **adopted** (a pre-existing page the
  user owned is never deleted). Applying the Company kit creates the previously-missing About/Contact pages.

### Changed
- **Junction/symlink-safe block asset URLs** (spec 040): `DynamicBlockRegistrar` normalizes each discovered block
  directory back under `WP_PLUGIN_DIR` before `register_block_type` (pure `BlockPathResolver` + `PluginMountMap`),
  so editor/view/style URLs resolve correctly on any mount (Windows junction, POSIX symlink, realpath-resolved/CI).
  A no-op for the already-correct junction case (no regression). Preventive hardening.

## [0.23.1] — 2026-06-12

### Fixed
- **Boot regression** introduced in 0.23.0: `ConfigServiceProvider` failed to boot
  (`Cannot resolve [FieldSections]: it is not instantiable`) because the spec-039 `FieldSections` interface had no
  concrete binding, so the container could not autowire `SettingsForm` for the settings screen. Bind
  `FieldSections` → `SettingsRegistry`, and add a container-wiring regression test that exercises the autowire
  path the unit tests had bypassed.

## [0.23.0] — 2026-06-12

Admin extensibility — making custom data and custom settings pages first-class, from a deep-review follow-up.

### Added
- **Custom tables in the admin** (spec 038): mark any Corex-managed table **managed** (one `ManagedTable`
  declaration) and it appears in **Corex → Data** automatically — browsable, paginated, deletable — like a
  post-type list, with no admin code. The `$wpdb` reader is **prepared** (`%i`/`%d`) and **bounded** (`LIMIT`);
  opt-in, so Corex never enumerates arbitrary tables.
- **Easy option pages** (spec 039): declare an `OptionPage` (title, menu, capability, fields) + register it to get
  a real, secured admin settings screen — rendered by the existing settings controls and saved cap + per-page
  nonce gated, with no form/nonce/save code. Reuse is enabled by a `FieldSections` seam shared with the built-in
  settings. Scaffold one with **`wp corex make:option-page <Name>`**.

### Fixed
- The `wp-dataviews` "dependencies that are not registered" notice on the Data screen — the handle is now declared
  only when WordPress actually registers it (the React already falls back to a plain table).

### Versioning
- All framework plugin/theme headers + `COREX_*_VERSION` constants aligned to 0.23.0 (`wp corex version`).

## [0.22.0] — 2026-06-12

The second round of deep-review work (specs 033–037) plus release hygiene — finishing the initiative a user
review surfaced, and adding a user-requested insights dashboard.

### Added / Changed
- **Design system overhaul** (spec 033): a real `theme.json` design system — expanded palette + state colors, a
  full type + spacing scale, **shadow presets** + **radius tokens**, `styles.elements`, and an **Editorial**
  style variation. The card blocks gained depth (token-only).
- **Self-update + distribution** (spec 034): Corex updates through WordPress's own plugin-update flow (an
  `Update URI` + a configured manifest endpoint), **fail-safe** and never phoning home unless configured. A
  documented **safe-edit boundary**: updates replace framework files only — never `corex-app/`, `brand.json`,
  content, or data.
- **Block library v2** (spec 035): five new inline-edited, server-rendered blocks — **hero, cta, team, gallery,
  tabs** — with media-library images and a **no-JavaScript** CSS-only tabs widget. Enough to build a full
  landing page from Corex blocks.
- **Health, versioning, i18n & OSS hygiene** (spec 036): a **Site Health** integration + `wp corex doctor`
  (non-zero exit on critical); `wp corex version <semver>` stamps every framework header/constant in one step; a
  shared `corex` text domain + `composer i18n:pot`; and `LICENSE`, `CODE_OF_CONDUCT`, `SECURITY`, `.editorconfig`,
  and GitHub templates.
- **Insights dashboard** (spec 037): a **Corex → Insights** screen with two Run-on-demand cards — **Performance**
  (PageSpeed Insights / Lighthouse) and **Readiness** (agent-readiness signals + an optional Cloudflare scan) —
  scored, graded, cached, cap+nonce-gated, and gracefully degrading with no keys.

### Fixed
- Resolved a fatal (`FormsListController` resolved to the wrong namespace, breaking `rest_api_init`/the site
  editor) and made dynamic block registration **idempotent** (no more "already registered" notices on a
  double-firing discovery hook).

### Versioning
- All framework plugin/theme headers + `COREX_*_VERSION` constants are now aligned to the release (0.22.0),
  ending the `0.1.0` drift — stamped with the new `wp corex version` (spec 036).

## [0.21.0] — 2026-06-12

The first round of deep-review fixes (specs 029–032) — addressing real gaps a user review surfaced.

### Added / Changed
- **Inline-editable blocks** (spec 029): the component blocks (`stat`/`testimonial`/`pricing`/`accordion`) are
  now **edited inline on the canvas** (RichText) while staying dynamic; the `corex/form` block **picks a form
  from a dropdown** (the cap-gated `corex/v1/forms` route) instead of a typed slug.
- **Corex → Data admin** (spec 030): a `@wordpress/dataviews` screen to **view and delete form submissions**
  (and any registered custom-table source), via the cap-gated `corex/v1/data` REST. A `DataSource` abstraction
  lets custom tables plug in.
- **Kits build a real site** (spec 031): applying a kit now **creates its pages** (composed front page + About/
  Contact, etc.) idempotently and reversibly (tracked so `wp corex reset` removes exactly them).
- **Modern settings UX** (spec 032): the logo is set with a **media picker** (no URL typing), the captcha driver
  is a **dropdown**, and the configured **logo shows in the settings header**.

## [0.20.1] — 2026-06-12

### Fixed (documentation)
- Refreshed the **docs-app** product site for specs 025–028, which it had not yet reflected:
  - Regenerated the per-class internals reference (`wp corex docs:generate` → 231 pages) so it includes the
    reset CLI, add-on manager, the four new component blocks, `AdminGuard`, and the corrected
    `orderBy`/`orderByNumeric` signature.
  - **CLI guide**: documented `wp corex docs:generate` and `wp corex reset` (soft + gated full).
  - **Blocks guide**: listed the built-in `corex/*` library, including `stat`/`testimonial`/`pricing`/`accordion`.
  - **Configuration guide**: documented the Add-ons and Setup Wizard admin screens.
  - **Getting-started overview**: cross-links the in-repo Developer & Operations Handbook (`docs/`).

## [0.20.0] — 2026-06-12

### Added
- **Developer & Operations Handbook** (`docs/`, spec 028): an in-repo, GitHub-native Markdown handbook for
  setting up, dockerizing, deploying, and contributing to Corex — built spec-first across 12 phases and split
  by audience from the published `docs-app/` site (which keeps the product docs + the generated class
  reference; the handbook links to it, never duplicating).
  - **Getting started**: five OS guides (Windows WAMP/XAMPP, Linux, macOS, wp-env), grounded in the real setup
    script + `wp-env.json`.
  - **Docker**: a one-command dev stack (`docker-compose.yml` with nginx/php-fpm/MariaDB/redis/mailpit and a
    monorepo-mapping entrypoint) and a multi-stage production `Dockerfile`.
  - **Deployment recipes**: Azure App Service, Azure VM, AWS Elastic Beanstalk, AWS EC2+RDS, and cPanel — each
    covering provisioning, deploy-from-tag, HTTPS, secrets, backups, rollback, zero-downtime, and CI/CD, with a
    Mermaid topology diagram.
  - **Team workflow** (onboarding, git-flow-lite, Conventional Commits, the Spec Kit loop, quality gates),
    **cookbooks** (Woo detect-and-defer, multisite, headless, AI-agent flows, paid add-ons), **troubleshooting**,
    and **contributing**.
  - A **glossary**, a **translation-memory** of locked English terms, and an `en/` → `ar/` placeholder mirror
    for the future Arabic translation phase.
- Docker/CI config at the repo root (`docker-compose.yml`, `Dockerfile`, `docker/`, `.dockerignore`) — dev/deploy
  tooling only; no framework runtime dependency added.

## [0.19.0] — 2026-06-11

The forward specs 025–027, each built spec-first via the full Spec Kit flow, tested, guarded, verified on
real WordPress, and merged via its own PR (CI green).

### Added
- **`wp corex reset`** (spec 025): return a Corex site to a clean state. **Soft** mode deactivates Corex
  add-ons, clears `corex_*` options + feature flags, and removes the wizard-seeded demo — touching only
  Corex's footprint. **Full** mode (`--hard`) wipes the DB to a fresh Corex starter, gated behind a typed
  `--yes-i-mean-it` safeguard (+ WP-CLI confirm) so it never auto-runs. `--dry-run` previews either mode.
  Pure `ResetPlanner` + fail-closed `ResetGate`; the wipe lives only in `ResetExecutor`.
- **Corex Add-ons screen** (spec 026): a "Corex → Add-ons" admin screen (in `corex-config`) to enable/disable
  each add-on — toggling its plugin and feature flag together — with dependency awareness (refuse + explain,
  no silent cascade). Pure `AddonRegistry` + `AddonManager`; gated by the shared `AdminGuard`.
- **New `corex/*` component blocks** (spec 027): `corex/stat`, `corex/testimonial`, `corex/pricing`, and
  `corex/accordion` — server-rendered, token-only, RTL, accessible (the accordion uses native `<details>`,
  no JS). Auto-discovered with no engine change.

## [0.18.0] — 2026-06-11

The "Finish Corex" initiative — brought into full spec-first compliance (retrospective specs 018–024)
and remediated (P1–P6), merged via PR #1 with CI green.

### Added
- **Front-end build pipeline** (spec 018): `@wordpress/scripts` across npm workspaces; every `corex/*`
  dynamic block now has editor-side registration (`registerBlockType` + `ServerSideRender` +
  InspectorControls), compiled token-only styles + auto RTL, and a "Corex" inserter category.
- **CLI** `wp corex make:block` + `wp corex docs:generate` (spec 019): a one-name dynamic-block
  scaffolder and an AST-based (no class loading) per-class internals reference generator.
- **Shared form validation + flexible builder** (spec 020): one PHP schema is the source of truth —
  exported to the block, mirrored by `validation.js`, re-validated server-side (authoritative); a
  per-type `FieldRenderer` (SRP) renders every input type accessibly + token-only with whitelisted attrs.
- **QueryBuilder complex queries + feature flags** (spec 021): `orWhere`/`whereMeta`/`whereBetween`/
  `whereTax`/`whereDate`/`search`/`orderByNumeric`/`paginate` (pure, capped, value-bound) and a
  feature-flag layer (`config/features.php` + `FeatureFlags` + `Config::enabled()`).
- **Documentation web app** (spec 022): Astro + Starlight under `docs-app/` — getting-started, guides,
  architecture, FAQ, troubleshooting + the generated reference, client-side Pagefind search, RTL-ready.
- **Portfolio + WooCommerce site kits** (spec 023): `corex-kit-portfolio` (`corex_project` CPT +
  `corex/projects` block + FSE templates) and a gated, HPOS-safe `corex-kit-woo`; company-kit manifest
  drift-protection.
- **Deferred tail** (spec 024): gated mail queue (Action Scheduler, lazy worker), read-only WP 7.0
  Abilities, and a setup wizard (pure planner + admin-gated screen + `BlueprintActivator`).
- **Tests**: a block-`index.js` Jest test + a root `jest.config.js` scoping JS tests to Corex; an
  environment-gated Playwright E2E smoke (`tests/e2e/`).

### Changed
- `QueryBuilder::orderBy` no longer takes a boolean flag — use the new `orderByNumeric()` (clean-code P3).
- Admin-menu screens (`AdminDashboard`, `SetupWizardScreen`) route their cap + nonce check through the
  shared `Corex\Security\Admin\AdminGuard` instead of hand-rolling it (Principle VII scope, P5).
- `SetupWizardScreen` slimmed to render + gate, delegating side effects to `BlueprintActivator` (SRP).

### Fixed
- Mail-queue worker registers lazily, fixing an early-boot regression that loaded the `corex` textdomain
  too soon (zero-notice boot).

### Governance
- Constitution **v1.2.1** — Principle VII scope clarification (admin screens → `AdminGuard`).
- Retrospective specs **018–024** restore spec-first compliance (Principle X); DECISIONS #56–#58.

## [0.17.0] — 2026-06-10

### Added
- **Admin Dashboard / Settings** (`corex-config`, spec 017): a top-level Corex admin menu + a
  server-rendered settings screen (brand/mail/forms/captcha) that persists to the options the Config
  engine reads. (The React/DataViews UI is the deferred upgrade — needs a Node build + browser.)
- **Corex Brand Identity + Admin Branding** (`corex-config`, spec 016): Corex's SVG logo (navy + cyan)
  + login/footer admin branding, configurable, separate from client branding.
- **Call Request** (`corex-bookings`, spec 015): request-a-call flow → custom table + leader/visitor mail.
- **Careers** (`corex-careers`, spec 014): job CPT + taxonomies, `corex/jobs` block, secure application
  flow (CV validated, stored, notified) + a status pipeline.
- **Newsletter / Subscriptions** (`corex-newsletter`, spec 013): topic subscriptions, double opt-in
  (signed tokens), unsubscribe/suppression, GDPR consent, on-publish trigger.
- **Captcha + Secure uploads** (`corex-captcha` + core, spec 012): a fail-closed captcha driver system
  (honeypot/reCAPTCHA/Turnstile/hCaptcha) + a path-safe upload validator.
- **Custom Tables + TableRepository** (core, spec 011): a schema builder + `dbDelta` migrator + typed
  `TableRepository` (casts), the foundation for subscribers/applications/bookings.
- **Company Website Kit** (`corex-kit-company` + theme, spec 010): universal FSE templates + a Blueprint
  manifest composing the Corex blocks/patterns.
- **Corex UI block library** (`corex-ui`, spec 009): server-rendered `corex/*` blocks + token-only section
  patterns under a "Corex" category + a UI manifest.

> Spans v0.9.0–v0.17.0 (one tagged release per spec). The full per-spec history is in `PROGRESS.md` /
> `DECISIONS.md`. Tags: v0.9.0 (UI) · v0.10.0 (Kit) · v0.11.0 (Tables) · v0.12.0 (Captcha/Uploads) ·
> v0.13.0 (Newsletter) · v0.14.0 (Careers) · v0.15.0 (Call) · v0.16.0 (Branding) · v0.17.0 (Admin).
> Plus hotfixes v0.8.1 / v0.9.1 (cross-platform autoload casing, caught by CI).

## [0.8.0] — 2026-06-09

### Added
- **Corex Mail (MVP)** (new `corex-email` add-on, spec 008): a one-line `Mail` API; code-registered
  templates with whitelisted, escaped `{{ path }}` merge variables wrapped in a brand layout (from
  `theme.json`/`brand.json`, RTL-aware); a security gate (header-injection guard + recipient
  validation); fixed/role/dynamic recipient resolution; a `MailDriver` abstraction with a default
  `WpMailDriver` (`wp_mail`, from-identity from Config); and a queryable `corex_email_log` audit (CPT).
- **Mail seam** in corex-core (`Corex\Mail\Mailer` + `MailRequest`): a transport-neutral interface so
  modules send email without depending on a concrete engine (detect-and-defer via the container).

### Changed
- Forms (`SendEmailListener`) now delivers the contact notification through Corex Mail when active
  (templated + logged), falling back to `wp_mail` otherwise — no hard dependency either way.

## [0.7.0] — 2026-06-09

### Added
- **Forms engine** (new `corex-forms` plugin, spec 007): code-defined form schemas, a pure
  headless validator (bail-per-field; `required`/`email`/`max`/`min`/`numeric` returning i18n
  message keys) and schema resolver, a secured REST submit lifecycle
  (`POST corex/v1/forms/{slug}` → nonce → form-shaped sanitize → throttle → honeypot →
  validate → dispatch), store + email listeners (a non-public `corex_submission` CPT via the
  data layer; `wp_mail` to a configurable recipient), an example contact form, and the
  accessible, token-only `corex/form` FSE block with conditional assets.
- **Event seam** in corex-core (`Corex\Events`): `ListenerProvider` + `EventDispatcher` —
  ordered, once-each, best-effort dispatch — reusable by future modules (Corex Mail).

### Changed
- `Http\Middleware\Response::reject()` gained an optional payload argument so a rejection can
  carry a structured body (e.g. per-field validation errors). Backward compatible.

## [0.6.0] — 2026-06-08

### Added
- **Foundation** (specs 001–006): the self-booting engine — PSR-11 container, service-provider
  lifecycle, layered Config, declarative hooks, controller auto-discovery (spec 001); the data
  layer — read-only Models, repositories, ACF-optional field driver, capped QueryBuilder, eager
  loading (spec 002); `wp corex make:*` CLI generators (spec 003); the block engine — convention
  discovery, conditional assets, container-resolved render, Block-Bindings connectors (spec 004);
  the declarative middleware pipeline — `nonce`/`auth`/`throttle`/`sanitize` + `SecurityModule`
  (spec 005); the theme token source + `brand.json` runtime override resolver + style variations
  (spec 006).

[0.8.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.8.0
[0.7.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.7.0
[0.6.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.6.0
