# Changelog

All notable changes to Corex are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/) (pre-1.0: the API may still move).

## [Unreleased]

### Added

- **An export can be a PDF** (spec 103, slice 6), from the Submissions inbox and from the Data
  screen. "PDF document (.pdf)" is a third choice under Format. The document is A4, set wide. It
  opens with what was exported, how many records, and who exported it and when. Every page is
  headed by the site's name and the export's title, and signed at the foot as CoreX's: the CoreX
  logo, "Exported with CoreX", the copyright line, and the page's number out of how many. The
  owner, of what "signed off with the identity" meant: "pdf exported should has the corex logo on
  it as it has the copyrights of the tool". The heading row repeats on each page. More than eight
  columns are set out one record at a time, a question beside its answer. Arabic is shaped and
  joined, a right-to-left site's page reads right to left, and a phone number, a date or an
  English answer inside it keeps its own order.
- **A PDF holds up to 500 records.** The dialog says so under the choice, and names the limit in
  place of starting when the choice holds more; the server refuses the same request. An export's
  file is written in one request. On the development machine five hundred rows took 6.6 seconds
  and 68MB, and a thousand took 17.3 seconds and 116MB.
- **The export's preview answers say which formats the server can write**: a `formats` key with
  `available` and `pdf_most_records`. PDF is offered only where the library and PHP's `gd` and
  `mbstring` are present.

### Changed

- **The Data screen has one export dialog** (spec 103, slice 7b), and it is the Submissions
  export's. "Export records" on the Records tab and the Export tab both open it: what to export
  with a count for each choice, the columns, the format, one action, the export's progress, and
  the file when it is ready. The Records tab had a WordPress modal that closed and said "The export
  was queued." The Export tab had a second form, with one scope, that said "Refresh history when
  the job completes." Neither handed over a file.
- **The Export tab's history reloads when an export was made.** Its "Refresh" button is gone.

### Security

- **With the login hidden, three well-known addresses handed the hidden address to anyone who
  asked.** Signed out, `/wp-signup.php`, `/wp-register.php` and `/wp-admin/customize.php` each
  answered with a redirect to the custom login address. On a single site the first two only
  forward a visitor to the registration URL, and the Customizer sends a signed-out visitor to the
  login before CoreX answers the request; every one of those URLs is rewritten to the custom
  address. The first two now get the same "not found" page as `/wp-login.php`, byte for byte, and
  the third is answered like the rest of the admin area. On a network `/wp-signup.php` is the
  public sign-up page and is left alone. Reported from a production site; the other two were
  found by probing. In every release since v0.34.0, on a site with "Hide wp-login.php and
  wp-admin" on. Hiding is obscurity, and the failed-login throttling was not affected.

### Fixed

- **A Data export stopped when a record arrived in the source while it was being written**, with
  "Bounded job counters are inconsistent." The last batch then held more than what was left to
  export, and the job refuses to count past its total. A batch is cut to what was counted when
  the export was asked for, as the Submissions export's has been. On a site that receives
  submissions while somebody exports all of them, that was every such export.
- **In the export dialog, a choice's name was smaller than the line describing it**: 13px,
  WordPress's size, above a line set to 14px. The dialog has its own text size. In the Submissions
  export since v0.43.2.

### Client impact

- **A site that hides its login should take this**, and may treat its login address as known if
  the site has been public: anyone who asked for `/wp-signup.php` was told it. Choosing a new
  address on the Security screen after updating is the remedy.
- **`/wp-signup.php` and `/wp-register.php` answer 404 on a single site with the login hidden.**
  A link or a server rule that sent visitors to either has to point at the registration address
  WordPress gives (`wp_registration_url()`), which still works.
- **CoreX cannot hide `/wp-activate.php`, `/wp-admin/install.php` or `/wp-admin/upgrade.php`**:
  WordPress runs them without plugins. They do not name the custom address. A site that wants
  them answered differently needs a rule in its web server.
- **A new production dependency: `mpdf/mpdf` ^8.3** (GPL-2.0-only), with its own dependencies. Run
  `composer install` after taking this. It adds about 94MB to `vendor/`, 88MB of it fonts. **The
  shared-host package is about 191MB unpacked with it, 92MB of that mPDF**, so it nearly doubles
  what is uploaded. The fonts are kept whole: they are what writes Arabic and every other script
  a form can be answered in.
- **The shared-host builder removes from the packaged `vendor/` what a package may never hold**
  (`.github`, `tests`, `.git`, `node_modules` and the rest of its forbidden list). Composer
  installs a package as its author shipped it, mPDF ships a `.github`, and the package was
  refused for it. A production package that loads code from a directory named `tests` would lose
  it; none does today, and the verifier already refused such a package.
- **PDF needs PHP's `gd` and `mbstring`.** Without them CoreX works as before and does not offer
  PDF. mPDF keeps a cache of font data in `uploads/corex-private/exports/pdf-working/`, beside
  the exports and behind the same protection.
- **Signatures changed in `Corex\Config\Export`**, for code that builds exports itself:
  `ExportDocument` takes a third argument, the facts a document opens with; `ExportWriters` is
  constructed with the PDF writer and has `available()` and `describe()`;
  `SubmissionExportFiles` and `DataExportFiles` take the new document builders. Nothing changes
  for a site that only uses the screens.
- **The Data screen's export looks and behaves differently**: a dialog titled "Export <model>",
  which waits and saves the file, where there was "Create export" and "Queue export". Anything
  that drives that screen by its labels has to follow.
- **The export dialog's styles moved** from `plugins/corex-config/assets/submissions-admin.css` to
  `plugins/corex-core/assets/css/corex-admin-shell.css`. The class names are unchanged.
- **`.corex-data__column-picker` and `.corex-data-models__columns` are no longer styled**: nothing
  CoreX draws uses them.
- **The Data explorer's `createExport` is gone**, with `admin/data/ExportDialog.js`. They are
  internal to the admin bundle.
- **The admin bundle changed.** `build/` is git-ignored: rebuild `plugins/corex-config` after
  taking this.

## [0.43.5] — 2026-10-08

One change on top of v0.43.4. The 500 that failed the browser job now and then was PHP refusing
a correct argument, and nothing in CoreX or in anything stored: the job now says what threw in
its own output and runs without PHP's JIT, which the runner had switched on unasked. Found while
ruling flows out, and fixed with it: one stored flow that cannot be read no longer empties every
list of flows, and storing a flow can no longer leave half of one.

For a site it is a patch, unless its own code builds a `FlowRepository`, calls
`FlowService::search()` or reads the text of the pipeline's log line. Client impact has those.

### Fixed

- **One stored flow that could not be read emptied every list of flows.** A flow is a post and
  three rows of post meta. A flow whose payload was missing, or held a date that would not parse,
  made `GET corex/v1/flows` answer 422 or 500 for all of them, and the Forms screen listed none.
  The form filter on Submissions and on Data, and the flow counts on Overview and Insights, read
  the same list and showed no flows without saying why. Such a flow is now left out, the others
  are listed, and PHP's error log names the record and what was wrong with it
  (`Stored flow record 31267 could not be read and was left out (...)`). A flow that points at a
  draft that was never stored is left out of the Forms list the same way.
- **Storing a flow could leave half of one.** The row a list selects a flow by was written first
  and the payload last, so a request that stopped between them left a flow no reader could build.
  The selecting row is written last. A write that fails stores nothing and the request is
  refused.
- **The log line for a 500 behind a CoreX REST route says what threw.** It was
  `Middleware pipeline error:` and the exception's message. It now carries the exception's class
  and the file and line as well.
- **The browser job prints what the server said, and serves the suite without PHP's JIT.** It
  prints the last 40 lines of PHP's error log and php-fpm's OPcache status in its own output on
  every run. Eight inbox specs failed on 2026-10-08 on a 500 whose cause was one line in an
  artifact. That line showed PHP refusing a correct argument against a type it could not name,
  and nothing wrong in CoreX or in anything stored (DECISIONS #268). The first status the job
  printed showed the runner's php-fpm running the tracing JIT, which nothing had asked for. The
  job now switches it off and checks that it is off. That the JIT caused the fault is not proved.

### Client impact

- **`GET corex/v1/flows` leaves out a flow it cannot read**, where it answered 422 or 500 and
  listed none. A site that has such a record sees its other flows again on the Forms screen and in
  the form filters. The record is still stored and its slug is still taken, so a new flow cannot
  use that slug. PHP's error log names the record's post id; to free the slug, delete that
  `corex_flow_record` post.
- **`GET corex/v1/flows/{id}` for such a flow answers 422 with "Stored flow record N could not be
  read."** It answered 422 with the reader's own message, or 500.
- **`FlowRepository` takes a `BootLogger` as its second constructor argument, and
  `FlowService::search()` is now `listing()`**, which returns each flow beside its current draft.
  Code that takes either from the container is unaffected. Code that builds the repository itself
  or calls `search()` has to change.
- **`WpFlowStore::create()` throws a `DomainException` when a row cannot be written.** It returned
  the post id of a record without that row.
- **Anything that matches the text `Middleware pipeline error: ` followed by a message** now finds
  the exception's class before the message and `(File.php:line)` after it.

## [0.43.4] — 2026-10-08

The shared-host `dist` package loads the framework, which it never did, and that is why this is
released today: a client's move to a host with no shell was waiting on a package that boots. A
package built by an earlier version has to be rebuilt, and its layout changed.

It also holds what had landed since v0.43.3: the export history with expiry and delete, the Data
export written as a readable CSV or Excel file kept on disk, mail leaving from the sender a
message names with its attachments, and WordPress's own notices drawn inside the CoreX shell. Like
the two releases before it, it carries a patch number and is more than a patch for a site: two
interfaces changed, exports already on a site start expiring, and a site that names a mail sender
starts sending from it. Read Client impact before taking it.

### Added

- **The export history says what each export was** (spec 103, slice 5). It was a date, a count
  and a link. Each entry now says what it covered (the ticked rows, the filters in words, or
  everything), its format, how many submissions, how large the file is, who made it and when, and
  what became of the file. The five newest are listed, and a link under them shows the rest.
- **An exported file expires.** It is kept for 30 days from the day it was made. The daily
  retention sweep then removes the file and leaves the entry, which says it expired. An export is
  a copy of people's answers on the server, and nothing removed one before.
- **An export can be deleted.** "Delete" on an entry asks first, removes the file and leaves the
  entry, which then says who deleted it and when. The activity log records it as
  `submission.export.deleted`. `DELETE corex/v1/submissions/exports/{id}` is the route.
- **The Data export writes an Excel workbook** (spec 103, slice 7a), for submissions and for every
  managed table. The screen has offered "XLSX" only where a source declared it, and no source
  CoreX ships did, so no site has ever been offered it. The workbook has the source's name on its
  sheet, the heading row kept in view, filtering on, and numbers and dates written as such.
- **The Data export can be asked how much it would export**, and can take its steps on request:
  `POST corex/v1/data/{source}/exports/preview` answers the count for the selected rows, the
  filters and everything; `POST corex/v1/data/{source}/exports/{id}/advance` takes one step now.
  The screens that use them come with slice 7b.

### Fixed

- **The shared-host `dist` package loads the framework.** It never did. `npm run build:dist`
  copied the checkout's `vendor/` to the package root, and every CoreX plugin looks for the
  autoloader in its own directory or two directories up, which in the package is
  `wp-content/vendor/`. No plugin found one, so all of them stayed dormant. A `vendor/` moved
  there by hand would not have helped: Composer's map names `addons/<name>/src/` and
  `packages/cli/src/`, and the package holds add-ons under `plugins/` and did not hold the CLI
  package at all. Reported from a real build of a client package on v0.43.3. The builder now has
  Composer generate the autoloader inside the package, at `wp-content/vendor/`, with the
  repository's namespace map translated to where the package puts each directory (DECISIONS #267).
- **`wp corex` exists in the package.** `packages/cli/` is packaged at `wp-content/packages/cli/`
  with its generator stubs.
- **No dev package is shipped into a web root.** The copied `vendor/` was whatever the checkout
  had, which on a working checkout includes PHPUnit, Pest and Mockery. The package's `vendor/` is
  now installed by Composer from `composer.lock` with `--no-dev`, inside `dist/`. The checkout's
  own `vendor/` is not read and not changed, so there is no longer a reason to run
  `composer install --no-dev` on a checkout before packaging.
- **The package no longer contains the development install's `.htaccess`.** It was copied from
  `wp/` as if it were WordPress core, although the deployment guide lists `.htaccess` among the
  files a deploy must never overwrite.
- **`npm run verify:dist` fails for a package that cannot load.** It reported "OK" for all of the
  above: it checked folders, forbidden names and the manifest. It now runs
  `scripts/shared-host-dist-probe.php` in a PHP process of its own, which includes the packaged
  core plugin as WordPress would and requires that it loaded the packaged autoloader, that one
  class from every packaged plugin, add-on and the CLI package loads from the package, and that
  nothing was loaded from outside it. It also fails on a `.htaccess` in the package root and on a
  `vendor/` installed with dev packages. CI builds the package for a generated client site and
  starts WordPress from it.
- **The cPanel guide no longer tells you to assemble the tree by hand.** Its `cp -r` commands
  produced the same package that could not load. It uses the builder and the verifier.
- **The export dialog printed `[object Object] to [object Object]`** for "Current filters" when
  the inbox was filtered by date. It read a date through a helper that answers the text together
  with its machine form, and printed the pair. In v0.43.2 and v0.43.3.
- **A download from "Recent exports" that failed said nothing.** It says so now.
- **An export whose file is no longer on the server is not offered for download.** It was listed
  with a "Download" that could only fail. It reads "No file to download."
- **A Data export wrote a list as the word "Array"**, with a PHP warning: every value was written
  as `(string) $value`. A value is now written as what its field is declared to be: a number as a
  number, a date and time as one, a switch as Yes or No, a list as its items.
- **A Data export's CSV opened as mojibake in a spreadsheet** for any text outside ASCII: it had no
  byte-order mark. It has one, and a choice of separator, from the writer the Submissions export
  uses.
- **A Data export of no records wrote a file with a heading row.** It is refused: "There is
  nothing to export."
- **The same Data export asked for twice in one second was one export.** Its hash is salted, as
  the Submissions export's has been since v0.43.2.
- **`scripts/setup-wordpress.ps1` installs the browser suite's fixtures, as two files said it
  did.** The docblock of `tests/e2e/fixtures/corex-e2e-client-guide.php` and a comment in
  `tests/e2e/admin-help-tab.spec.js` both said the script copied that fixture into
  `wp/wp-content/mu-plugins/`. The script had no such step, so on a development install "a guide
  registered by a client plugin still appears" failed until somebody copied the file by hand. The
  second fixture, `corex-e2e-core-notices.php`, said to copy it by hand. The script now copies
  every `tests/e2e/fixtures/corex-e2e-*.php` there, on every run, so a re-run refreshes a fixture
  that was edited. The browser job in `.github/workflows/ci.yml` copies the same pattern in one
  step where it named each file in a step of its own, so a fixture added to the directory reaches
  both. The two docblocks now say the same thing, and `CONTRIBUTING.md` and the header of
  `tests/e2e/playwright.config.js` say the script makes the copy.

  Two installs get no copy. `-Multisite` does not, because that install mirrors
  `.github/actions/provision-wordpress`, which installs no must-use plugin. A run that links a
  client site does not either, and prints the commands instead.
- **A message's sender and its attachments never reached the mail driver** (#150). A sender set
  with `MailRequest::$from` or `MessageBuilder::from()`, and files added with `attachments` or
  `attachMedia()`, were accepted, kept across the queue, and dropped on every send: the mail left
  from `mail.from.address` with nothing attached. `MailService::deliver()` rebuilds each message to
  remove invalid recipients, and rebuilt it from the seven fields the message had before either
  was added. In every release from v0.38.0, which announced both, to v0.43.3. What `wp_mail()` was
  handed on a real install, before and after: `From: Corex <the configured address>` and no
  files, then `From: Corex <noreply@example.com>` and the file. (DECISIONS #264)
- **A sender is inspected for header injection** beside the subject and the reply-to. It was not,
  which did not matter while it never arrived.
- **WordPress's update notice no longer sits above the CoreX admin.** While a core update is
  pending, WordPress prints "WordPress 7.1.3 is available! Please update now." before the page. On
  a CoreX screen that was a pale box in a band of its own above the shell, and every screen
  started 54px lower. It is now printed under the page header, in the content column, drawn like a
  CoreX alert in dark and in light, with its two links as they were. It is moved, not hidden: an
  administrator still sees it on every CoreX screen.
- **Every notice printed on `admin_notices` or `all_admin_notices` goes to the same place** on a
  CoreX screen, a plugin's and CoreX's own (the result of switching an add-on, the kit prompt).
  WordPress's script used to move these into the page header, between the title and its
  description, in WordPress's light colours in both appearances. They are stacked under the
  header, 12px apart, with the tone on the edge reading starts from and the dismiss button on the
  far side, in both directions.
  With nothing to show, the page is laid out as it was. Something printed on those hooks that is
  not a notice, Hello Dolly's line for one, is in the same place as a line of text. It used to
  float in the top corner beside the shell, and the whole shell was drawn narrower to make room
  for it.

It was not seen in CI because CI installs the latest WordPress, where no update is pending.
`tests/e2e/admin-core-notices.spec.js` asks for one: a fixture answers the update check for the
request, so WordPress prints its own notice whatever version the install runs.

- **`CONTRIBUTING.md` sent a contributor to a browser-test workflow deleted in 0.41.0.** "Browser
  verification" said the Playwright suite ran nightly and on demand from
  `.github/workflows/e2e.yml`, on wp-env, and that a pull request was gated by the unit suites
  only. It is the `e2e` job of `.github/workflows/ci.yml`: on every pull request, on a push to
  `main` or `develop` and nightly, required on `main`, with no on-demand trigger, against a
  WordPress the job provisions and serves with nginx and php-fpm. The section now says that, and
  that the job skips three tests by title, two of them the block-editor tests the section names.
- **The local run it described did not drive the site it started.** `npm run env:start` then
  `npm run test:e2e` starts wp-env and drives `http://corex.local`, the suite's default. The
  section now lists what a local run needs: a
  served install with the add-ons active, the built bundles, the `COREX_*` variables with their
  defaults, the content and users the CI job seeds, and every
  `tests/e2e/fixtures/corex-e2e-*.php` copied into `wp-content/mu-plugins/`. The copy is
  documented there once, for any install. The Docker entrypoint, `wp-env.json` and the Linux and
  macOS guides are unchanged and install no fixture; the section says so.
- **The comments in `tests/e2e/` that said the same things are corrected**: the header of
  `playwright.config.js` (the suite was to be "wired into CI behind a job that boots wp-env") and
  its list of why tests are excluded, and the headers of `smoke.spec.js`, `console.spec.js` and
  `global-setup.js`. Comments only.

### Client impact

- **A shared-host package has to be rebuilt, and its layout changed.** A `dist/` built by v0.43.3
  or earlier does not load CoreX on a host and should not be uploaded. Rebuild it after taking
  this: `npm ci && npm run build`, build the client theme, then
  `npm run build:dist -- --client=<client>` and `npm run verify:dist`. What moved:
  `vendor/` is at `wp-content/vendor/` and no longer in the package root; `wp-content/packages/cli/`
  is new; there is no `.htaccess`; `corex-release.json` has an `autoload` section naming the
  autoloader and every namespace the package loads.
- **Building a package needs `composer` on the `PATH` and, the first time, the network**, and
  verifying one needs `php`. The build stops if Composer cannot install. It no longer needs the
  checkout's `vendor/` and never changes it.
- **Do not run `composer install --no-dev` on a client checkout to prepare a package.** The guide
  said to. It strips the test tools from the working tree, and the builder no longer reads that
  directory.
- **If a package from an earlier version was ever uploaded, delete `vendor/` from the site root
  on the host.** Nothing reads it and it holds dev tools inside the web root.
- **The first upload to a new host brings no `.htaccess`.** On Apache or LiteSpeed, save
  Settings → Permalinks once so WordPress writes it, or create it by hand. An existing host keeps
  the one it has.
- **A root `composer.json` with `files`, `classmap` or `psr-0` autoloading, or a namespace mapped
  to several directories, stops `build:dist`** with a message naming it. Only `psr-4` entries are
  translated into the package. A `psr-4` entry for a directory that is not packaged is left out
  with a warning.
- **In the package the project root is `wp-content/`.** That is where CoreX would read an optional
  `.env`, and it is inside the web root: do not put one there on a shared host.
- **`azure-pipelines.yml` lost its `composer install --no-dev` step.** A client pipeline copied
  from it can drop the step too; with it the build still works.
- **Mail starts leaving from the address a message names, with the files it names.** Nothing in
  the framework sets either, so a site that never set one sees no change. A site whose code sets
  `from` on a `MailRequest`, or calls `MessageBuilder::from()` or `attachMedia()`, has been sending
  from `mail.from.address` with nothing attached since v0.38.0, and after this sends what it
  asked for. Before taking it, check that each address so named is one the site's relay may send
  from (in FluentSMTP, a configured connection for that address) and that its domain's SPF and
  DKIM cover it: until now the bug stood between a wrong address and the relay. Attached files
  count toward the provider's size limit.
- **A sender containing a line break or another control character is refused.** The message is
  not sent and is logged as `rejected`, as it already was for a subject or a reply-to.
- **A client that patched `addons/corex-email/src/MailService.php` to carry these two fields**
  takes the framework's file as it is and removes its patch and its baseline exception. A patch
  that added a comment above the constructor call conflicts with this one there.
- **Exports already on a site start expiring.** The first daily sweep after this is taken removes
  the file of every submissions export made more than 30 days ago, and the stored text of any made
  before v0.43.2. The entries stay. Somebody who needs an old export has to download it first.
- **`GET corex/v1/submissions/exports` answers more for each export**: `file_size`, `state`
  (`ready`, `expired`, `deleted` or `pending`), `expires_at`, `actor_name`, `removed_reason`,
  `removed_by`, `removed_by_name` and `removed_at`. Nothing was removed.
- **`…/exports/{id}/download` answers 404 for an export that expired or was deleted**, where it
  answered the file.
- **`SubmissionExportService::history()` and `::download()` moved** to the new
  `SubmissionExportHistory`, as `entries()` and `download()`. Code that called them on the service
  has to ask for the history.
- **`SubmissionExportStore` has two more methods**, `removeFile()` and `holdingFilesBefore()`, and
  `file()` answers null for a file that is not on disk. Code that implements the interface has to
  add them.
- **`SubmissionControllerServices` takes one more constructor argument.**
- **A Data export's file is on disk**, in `uploads/corex-private/exports/`, where it was a base64
  string in post meta. An export made before this is still downloaded from where it is.
- **A Data export's CSV begins with a byte-order mark** and is named
  `<site>-<source>-<date>.csv`, where it was `corex-<source>-<id>.csv`. Anything that reads the
  file by its name or expects its first byte to be the first heading has to be adjusted.
- **In a Data export, a switch reads "Yes" or "No"** where it read `1` or nothing, and a date and
  time reads `2026-10-07 09:30` where it read as it was stored.
- **"XLSX" appears as a format on the Data screen** for submissions and managed tables.
- **`DataExportStore` changed**: `saveArtifact()` is gone, `saveFile()` and `file()` are new.
  **`DataExportJobQueue` has a second method, `advance()`.** `DataExportArtifact` and
  `DataExportArtifactWriter` are removed. Code that implements or uses them has to follow.
- **`WpDataExportStore` and `WpDataExportJobQueue` take one more constructor argument each**, and
  `DataExportJobHandler` takes `DataExportFiles` where it took the writer.
- **Strings in the history are new** in the `corex` text domain.
- **The admin bundle changed.** `build/` is git-ignored: rebuild `plugins/corex-config` after
  taking this.
- **A notice your plugin prints on `admin_notices` is drawn inside the CoreX shell on CoreX
  screens.** It is a child of `.corex-admin__notices` and takes the shell's colours. A stylesheet
  of yours that reached it as `#wpbody-content > .notice` on a CoreX screen no longer matches it.
  Other admin screens are unchanged.
- **The shell has one more element**: `<div class="corex-admin__notices">` between
  `.corex-admin__header` and `.corex-admin__content`, always printed, and of no height while it is
  empty. A selector written as `.corex-admin__header + .corex-admin__content` no longer matches.
- **`corex_admin_notices` is a new filter**: markup to print in that region, read by
  `AdminPage::open()`.
- **The notice change asks nothing of a site on update**, and nothing to rebuild: the shell's
  stylesheet is not a built file.
- **Re-running `scripts/setup-wordpress.ps1` in a repository with a site under `sites/` copies no
  browser-test fixture.** A must-use plugin is active as soon as the file is there, and the
  client-guide fixture adds a guide named "Client plugin guide" to the Guides screen. To run the
  framework's browser suite against such an install, use the two commands the script prints at
  the end.
- **A new client repository gets the fixtures on its first run**, because the procedure runs the
  script before `wp corex make:site` and there is no site to recognise yet. They are
  `wp/wp-content/mu-plugins/corex-e2e-*.php`; delete them if that install is ever shown to
  anyone but a developer. The dist builder packages neither `tests/` nor the install's
  `wp-content`, so they reach no artifact.


## [0.43.3] — 2026-10-07

The submission detail pane, rebuilt, and three faults the first client site reported within hours
of taking v0.43.2. Like v0.43.2 it carries a patch number and is more than a patch for a site: the
pane's markup changed, opening a submission now marks it read, and a refused request to an admin
route has a different body. Read Client impact before taking it.

One of the three is the reason it is released today. Since v0.43.2 the framework's own integration
suite failed on every pull request of a client repository with one site, on a test the client does
not own. Nothing a site runs was wrong, and the check was red all the same.

### Changed

- **The submission detail pane is reorganised** (spec 103, slice 4). The owner, of the old one: "the
  right pane opened i feel that it is not well organized". It put the answers fourth, under their
  field keys, showed an owner as a type and a key, and drew three sections that said "No data
  recorded." It now reads from the top down:
  - a header with the sender's name, their email address and phone number as `mailto:` and `tel:`
    links, the form, the submission's number, when it arrived, its status and whether it is read;
  - the answers, each under the question the form asked, with the stored key as the fallback;
  - reply and team notes, each field with a visible label;
  - one triage group: the status, and a single "Assigned to" control listing the people who can
    manage submissions, and Unassigned;
  - the notification's result, when, and the reason;
  - hidden fields, campaign data and the consent record as one closed group, drawn only for the
    parts that hold something, and one line when none does;
  - the history, closed until opened, as sentences: "Status changed from New to In progress" where
    it read "status success".
- **Opening an unread submission marks it read**, and the pane's header offers "Mark unread". A
  submission could be marked read and never unread.
- **The pane is a dialog.** Focus moves into it, stays in it, Escape closes it, and focus goes back
  to the row it was opened from. It sits over the admin toolbar and is as tall as the window, on a
  phone as well. Changing a status or adding a note no longer blanks the pane while it reloads.
- **A notification nobody recorded reads "Not tracked"**, in the inbox and in the pane, where it
  read "Delivery unavailable". That is every submission of a form defined in code, and the old
  wording looked like a fault on every row. The pane says why nothing was recorded.
- **An answer is written in the direction of its own language.** An English answer in an Arabic
  admin had its full stop on the wrong side.

### Fixed

- **The submissions and flow routes say at the route who may call them.** All 15 routes under
  `corex/v1/submissions` and the 10 under `corex/v1/flows` that the admin uses were registered with
  `permission_callback => __return_true` and refused a caller inside the handler. Nobody could call
  them who should not: an anonymous request was answered 403. But `wp corex routes:list` printed
  every one as "public", and `wp corex api:docs` gave them no security requirement. Each now has a
  permission callback that asks the same question the handler asks. The handlers still ask, and
  still ask for a nonce on a change. `POST corex/v1/flows/{id}/submit`, which a visitor submits to,
  is public and still is. Reported from the first client site.
- **A file named `support.js` is refused only where a design export is**: at the repository root,
  under `design/`, or beside a `.dc.html`. `tests/repo-hygiene.test.js` refused it anywhere as "a
  design-export helper script", including under `sites/<client>/`, where it was a client's own
  Playwright helper and failed "Lint + JS unit tests" on two of its pull requests.
- **The framework's integration suite passes in a client's repository again.** Since v0.43.2 the
  generators write into the one client plugin under `sites/`, under its namespace.
  `CommandRegistrationTest` still asserted the namespace is `App`, so "Integration tests (real
  WordPress)" failed on every pull request of a client repository with exactly one site, on a file
  the client does not own. The test asserts `App` where there is no client plugin, and a valid
  namespace anywhere else. Nothing a site runs has changed.

### Client impact

- **The submission's REST payload has three new keys**: `questions` (the form's questions, in
  order, with their wording and type), `owner_name`, and `owners` (the people who can be assigned).
  Nothing was removed.
- **`PATCH corex/v1/submissions/{id}` accepts `mark_unread`.**
- **`SubmissionOwnerNames` has a second method, `people()`.** Code that implements the interface
  has to add it.
- **`SubmissionQueryService` takes two more constructor arguments**, both optional.
- **The pane's markup and class names changed**: `.corex-inbox__drawer` is now a `<dialog>` and
  what is inside it is `.corex-pane__*`. Site CSS written against `.corex-inbox__fields`,
  `.corex-inbox__timeline` or the drawer's sections no longer matches anything.
- **Opening a submission in the inbox now writes to it**: it is marked read, and its history gains
  a line. A team that used "unread" to mean "nobody has dealt with this" should use the status.
- **Strings in the pane are new** in the `corex` text domain. A site with its own translation of
  that domain shows them in English until they are translated.
- **The admin bundle changed.** `build/` is git-ignored: rebuild `plugins/corex-config` after
  taking this.
- **A caller refused by a submissions or flow route gets WordPress's error body**, `{ code,
  message, data: { status } }`, where it got CoreX's envelope with `ok: false`. The status is still
  403, the `code` is still `forbidden`, and the message is the same. CoreX's own admin reads either.
  Code of a client's that calls these routes without the right to and reads `ok` from the answer
  has to read the status.
- **A client repository with one site gets its "Integration tests (real WordPress)" check back.**
  It has been red since v0.43.2 for a reason the client could not fix. Nothing to do but take the
  release; an exception recorded for it in the meantime can be removed.
- **A client's test helper may be called `support.js` again**, anywhere outside the root and
  `design/`. A client that renamed one to get past the rule does not have to rename it back.

## [0.43.2] — 2026-10-07

What the first client site found in its first week, and the first half of a rebuilt Submissions
export. It carries a patch number and is more than a patch: it adds an Excel export, a command and
a contract, changes the shape of the exported file and two interfaces behind it, and changes what
`max:N` and `min:N` measure on a text field. Read Client impact before taking it.

Three fixes stop a site turning people away. Choosing Turnstile or hCaptcha rejected every
submission of every flow. Behind a proxy, a form counted every visitor as one client, so one could
use up the allowance for all. And a mistyped email address was rewritten into a different one and
accepted, in forms and in five add-on endpoints, one of which created accounts.

The Submissions inbox is the other half. Any cell of a row opens the submission. The export is a
file a person can read, as an Excel workbook or a CSV, from a dialog that says what it will export
and hands the file over without a refresh. The detail pane, the export history, PDF and the Data
export are not in this release.

### Added

- **`wp corex security trusted-proxies`** shows, sets and clears the proxies a site trusts to report a
  visitor's address. The setting existed, was read by login protection, and could be set only by
  posting to the REST route by hand: the Security screen has no field for it. An entry that is not an
  address or a CIDR range refuses the whole list (#247).
- **Submissions export as an Excel workbook** (spec 103). It is what the dialog offers first. The
  submitted time is a date and a numeric answer is a number, so both sort and filter. The heading
  row is bold and stays in view, filtering is already on, each column is as wide as what it holds,
  and each form is a sheet of its own, named for it. A phone number keeps its leading zero. Text
  that begins with `=` is text. A right-to-left site gets right-to-left sheets. CSV is still
  offered, with its separator (DECISIONS #256).
- **`phone:national`** accepts a phone number written with its country's trunk `0`, such as
  `010 1699 9700`, as well as everything `phone` accepts. `phone` alone stays international (E.164)
  and still refuses it. The browser applies the same rule (#249, DECISIONS #251).
- **`Corex\Http\ClientAddress`**, the one answer to "who is making this request" for anything that
  counts or limits per client. corex-core answers with the connection's address; corex-config
  replaces that with the address the trusted proxies report.

### Changed

- **The export dialog is rebuilt, and the file arrives without a refresh** (spec 103). It says what
  each choice covers before anything is exported: the rows ticked, the submissions matching the
  filters in force with those filters in words, or everything, each with its number of
  submissions. Columns are named in words and the ones that hold personal data are marked. One
  button, labelled with how many submissions it will export, and when the file is ready the
  browser saves it. A large export shows how far it has got. It used to queue the export, show
  nothing until "Refresh history" was pressed, and need a second click on "Download"
  (DECISIONS #255).
- **A submissions export is a file a person can read** (spec 103). It had one column per group of
  data, with the whole group encoded into one cell: a lead's five answers arrived as a single cell
  of punctuation. It now has one row per submission and one column per answer, headed by the
  question the form asked. Before the answers: ID, Submitted (in the site's timezone), Form, Status,
  Assigned to (a person's name), Read and Test. Campaign data, hidden metadata and the consent
  record have a column per value. Names in Arabic or with accents open correctly in a spreadsheet,
  and an export of several forms arrives as one file per form in an archive (DECISIONS #254).
- **Any cell of a Submissions row opens the submission** (spec 103). Only the submitter's name did;
  a click on the form, the status or the date did nothing. The row still has one control and one
  keyboard stop: its button now covers the row, and the checkbox sits above it. The row of the open
  submission is marked with a tint and a bar on its leading edge, and says so to assistive
  technology (DECISIONS #253).
- **The Operations mode panel is reorganised.** It said what the current mode does three or four
  times, drew two unmarked lists that read as one paragraph, laid its form out as a wrapping row
  with the button far from the confirmation that enables it, and asked for a confirmation of the
  mode the site was already in. Now: the current mode with one sentence and, under it, only the
  cautions that sentence does not make, drawn as notices. The change form is one column. A proposed
  mode has a heading, a short list of what changes for visitors and for people signed in, the
  reference notes behind "More about this mode", then the confirmation with the button directly
  under it. Selecting the mode a site has declared proposes nothing, and the form says so and asks
  for nothing (DECISIONS #247).

### Fixed

- **Choosing Turnstile or hCaptcha rejected every submission of every flow.** Both can be chosen in
  Settings and given keys, and "Test verification" answered that the keys were accepted. No widget
  for either is placed on a form and no token is sent, so the server refused each submission for
  lacking one, with "Submission protection rejected the request". A submission is no longer
  refused for lacking a token that nothing was placed to produce; the trap field still guards it,
  and it is recorded as not challenged. A token that does arrive is still verified. "Test
  verification" now says the keys are good and challenge nobody, the admin lists it as a gap, and
  the setting says which provider places a challenge today (DECISIONS #258).
- **The Submissions filters were four different heights.** Search was 50 pixels tall, the two
  selects 40, the owner field 47 and the dates 54, with 16, 12 and 2 pixels of inner padding,
  because each input type brought its own from WordPress. Every text control on the screen is now
  40 pixels with the same padding. "To" no longer drops onto a row by itself: the date range is one
  item, and the filters are laid out by the width of their own panel, not the window's. The
  heading's checkbox stood two pixels off the column under it.
- **Checkboxes and radios in the export dialog stood above their labels**, five pixels and two,
  lifted by a margin WordPress gives them for sitting in a line of text. The format section was
  twelve pixels further from the next than the others, an empty band sat above the buttons when
  there was nothing to say, the recent exports were 18 pixels tall with small targets, and one read
  "1 submissions". All measured, fixed, and now measured by the browser suite.
- **The export dialog had no styles.** Every CoreX admin style is scoped under `.corex-admin`, and
  the dialog was drawn by the WordPress modal at the end of the page, outside it. Its column
  choices were raw keys on one line, never translated. Dialogs on this screen are now the browser's
  own `<dialog>`, drawn where they are written, so the screen's styles and tokens reach them. The
  bulk-action confirmation moved with it.
- **An export waited for the site's scheduler**, which on a quiet site may not run for minutes. The
  dialog now asks the server to take the export forward a step at a time while it is open. The
  scheduler still finishes an export whose dialog was closed.
- **Two runs of one job could do the same batch twice.** Nothing stopped the scheduler and a second
  caller from each reading a job at the same point. A job now has a lock for the length of a step.
- **The same export made twice produced one file.** An export was found by a hash of who asked and
  what for, so a second identical request was given the first one's job and the older entry never
  got a file. Each export now has a hash of its own.
- **The right-hand side of the Submissions inbox was cut off and could not be reached.** With the
  WordPress menu and the CoreX menu both open, a 1280-pixel window leaves the inbox 816 pixels. The
  filter row needed 958, the inbox's one column grew to fit it, and a container above it clipped
  what was left: the "Received" column and the "To" date were off the edge with nothing to scroll.
  The filters now wrap by the space they are given, and the table scrolls inside its own frame
  when it is wider than that.
- **In a client repository, `wp corex make:*` wrote outside the client's plugin** (#251). With no
  `app.path` configured the generators wrote to `wp-content/corex-app` under `App\`, while the
  generated `AGENTS.md` said they wrote into the client plugin. In a repository with exactly one
  site under `sites/` they now write into that site's plugin, `sites/<client>/<slug>-site/src`,
  under its namespace (DECISIONS #252).
- **A generated site's README described a build pipeline the site did not have** (#251). The "Theme
  assets" section, with `assets/src/`, `npm run build` and the `Corex\Assets\*` calls, was written
  for every site. It is now written only with `--starter`, which is what generates those files.
- **A generated site's `AGENTS.md` told the client to follow Spec Kit, which writes to
  framework-owned paths** (#251). It now says to write the site's specs by hand, and why. Making
  the `/speckit-*` commands work in a client repository is still open.
- **Behind a proxy, a form counted every visitor as one client** (#247). The rate limit of a form and
  of a flow was keyed on `REMOTE_ADDR`, and so was the address sent to the captcha provider. Behind a
  proxy, a load balancer or a CDN that is the proxy: one allowance for the whole site, which one
  visitor could use up. Forms now ask for the client's address the way login protection does, so a
  site that has named its proxies gets one allowance per visitor. A site that has not is unchanged
  (DECISIONS #250).
- **`max:N` and `min:N` measured whatever the answer looked like** (#250). An answer that was all
  digits was compared as a number on any field, so `max:300` on a message refused the answer `2025`
  and `min:3` on a name accepted `12`. What a bound measures now follows the field: the number on a
  field that is a number, characters on every other. A field is a number when its type is `number`
  or `rating`, or when it declares `numeric`. The form's schema is where that is settled, so the
  server and the browser are handed the same rule (DECISIONS #249).
- **A site that only inherits its mode could not declare it from the screen.** Choosing the mode the
  site already follows was treated as a non-change and the button disabled, while the same screen
  told the operator to "declare a mode" and the store records that declaration as a change. The
  button is disabled only for a mode the site has declared.
- **A client repository inherited four things from the framework that did not fit it** (#239).
  Dependabot opened weekly pull requests against framework-owned lockfiles; CODEOWNERS asked the
  framework's reviewer to review the client's work; CodeQL failed on every push in a private
  repository; and the dependency advisory check failed on findings only a framework release can
  clear. Now `.github/repository-ownership.json` lists the first two under `clientMayRemove`, and
  `npm run verify:framework` reports a deleted one as `REMOVED` instead of drift. The advisory
  check runs in the framework's repository only, and CodeQL there or in a public client repository
  (DECISIONS #248).

- **A form could store, and reply to, an email address nobody typed.** An `email` field was passed
  through `sanitize_email()` before it was validated, and that function does not refuse an address,
  it removes what it does not accept: `sal,ma@example.com` became `salma@example.com`, passed the
  `email` rule, and the submission was stored under it. An answer with no address in it was emptied
  first, so a required field answered "required" where it meant "not an address", and an optional one
  was dropped without a word. Forms and flows now hand the rules what was typed: an address WordPress
  would leave alone is kept as it is, and anything else reaches the `email` rule as text and is
  refused by name (DECISIONS #245).
- **Five add-on endpoints did the same, and one of them created accounts.** Newsletter subscribe,
  the bookings call request, a careers application, account registration and profile update, and the
  Guides support request each cleaned the posted address and handed it to a service that then found
  it valid. Registering with `name,x@example.com` created an account for `namex@example.com`. Each
  now takes the address only when WordPress would store it exactly as typed, and otherwise passes
  nothing, which its service already refuses (DECISIONS #246).

### Client impact

What in this release can change how a client site behaves or builds, whether or not the merge conflicts.
Read this before taking the release.

- **A site with Turnstile or hCaptcha selected starts accepting flow submissions again**, guarded by
  the trap field and not by a challenge. It was accepting none. Only reCAPTCHA places a challenge
  on CoreX forms; choose it, or leave the trap field as the guard.
- **`POST corex/v1/captcha/test` answers `no_widget`, with status 400**, for Turnstile and hCaptcha
  keys the provider accepts, where it answered `ok`.
- **The export dialog now opens on Excel, not CSV.** Somebody who exports from the screen and
  feeds the file to something that reads CSV has to choose "CSV (.csv)" under File type. A request
  to the route that names no `format` is still a CSV.
- **In a CSV, a value that begins with `+`, `-`, `=` or `@` is shown with an apostrophe in front**
  when it is opened in a spreadsheet: `'+20 101 699 9700`. That is what stops a spreadsheet running
  it as a formula. An Excel workbook does not need it and does not have it.
- **New routes**: `POST corex/v1/submissions/exports/preview` answers how many submissions each
  scope would export and whether the person may export personal data; `POST
  corex/v1/submissions/exports/{id}/advance` takes one step of an export now and answers its state
  and progress.
- **`SubmissionExportJobQueue` has a second method, `advance()`.** Code that implements the
  interface has to add it.
- **Every bounded job takes a lock per step**, an option named `corex_job_running_<id>` that exists
  only while a step runs. A step that finds it held does nothing and leaves the job for the run
  that holds it; a lock older than two minutes is taken over.
- **Strings on the export dialog are new** in the `corex` text domain, and the old dialog's
  "Create export" and "Refresh history" are gone. A site with its own translation of that domain
  shows the new ones in English until they are translated.
- **The submissions export file has a different shape.** Anything that reads it by position or by
  its old headings (`identity`, `workflow`, `submitted_fields`, each holding JSON) has to be
  changed: the headings are now words and each value has a column. The file begins with a UTF-8
  byte-order mark and its lines end in CRLF.
- **An export of several forms is a `.zip`**, holding one `.csv` per form.
- **An export of nothing is refused** with "There is nothing to export", where it used to produce a
  file holding only headings.
- **On a host where PHP cannot write to `uploads/`**, an export is written to the system's
  temporary directory instead. It is still saved by the browser when it is ready; it cannot be
  downloaded again once the system has cleared its temporary files.
- **Exported files are written to `uploads/corex-private/exports/`**, the directory a web server is
  told not to serve, and are no longer kept in the database. A backup that takes the database and
  not the uploads directory no longer contains them. An export made before this release still
  downloads.
- **`POST corex/v1/submissions/exports` takes `format` and `separator`**, and `columns` as column
  names (`id`, `submitted`, `form`, `status`, `assigned_to`, `read`, `test`, `answers`,
  `answer:<key>`, `hidden_metadata`, `utm`, `consent_snapshot`, `notes`). The three group names it
  took before are still accepted and stand for the same data. The download answers `artifact` with
  `filename`, `content_type` and `base64`; `csv` is sent only for an export made before this
  release.
- **`SubmissionExportStore` changed**: `saveArtifact()` is gone, `saveFile()` and `file()` are new.
  `SubmissionExportCsvWriter` is removed. Code that implemented or called either has to follow.
- **Text in a Submissions row can no longer be selected by dragging.** The row is one control now.
  Open the submission to copy from it. A long sender address is cut with an ellipsis in the row
  and shown whole in the pane.
- **`wp corex make:*` writes somewhere new in a client repository with one site**: the client
  plugin's `src/`, not `wp-content/corex-app`. Files already generated into `corex-app` are not
  moved. A repository with `APP_PATH` set in its `.env`, with several sites, or with none is
  unchanged.
- **The files `make:site` already generated are not rewritten.** A site's own `README.md` and
  `AGENTS.md` are the client's; correct them by hand if they describe a pipeline the site lacks,
  or say that the generators write into the client plugin when they did not.
- **A form that asks a local audience for a phone number can stop refusing local numbers.** Change
  the field's rule from `phone` to `phone:national`. Nothing changes for a form that is left alone:
  `phone` accepts and refuses exactly what it did.
- **A site behind a proxy has one command to run**: `wp corex security trusted-proxies` with the
  address or range of every proxy between the visitor and WordPress, for example `127.0.0.1 ::1`
  for a tunnel on the same machine. Until it is run nothing changes: forms and login protection
  both go on counting the proxy as the client. *Security operations* says which addresses to list
  and what makes the answer wrong.
- **A site that already trusts proxies gets the change without doing anything.** Its form rate
  limits start counting per visitor on the first request after the update. Allowances in progress
  under the old key are not carried over.
- **`SubmitController`, `FlowSubmissionController` and `FormChallengeContextFactory` take a
  `Corex\Http\ClientAddress`.** Code that gets them from the container is unaffected. Code that
  constructs one by hand passes `new Corex\Http\RemoteAddress()` for the old behaviour.
- **A `max:N` or `min:N` on a field that is not a number now counts characters, always.** Check any
  form that bounds a quantity typed into a `text` field: without `numeric`, `max:10` there now
  accepts `99999`, which is five characters. Add `numeric` to the field's rules or give it the
  `number` type. Going the other way, a text field now accepts all-digit answers it used to refuse
  as too large, and refuses short all-digit answers it used to accept. A form that uses
  `max_length`/`min_length` for text and declares its numbers is unaffected.
- **A form's resolved schema names the rule that runs.** `max`/`min` on a field that is not a number
  appear as `max_length`/`min_length` in `FieldSchema::$rules` and in the schema the form block
  hands the browser. The error keys are unchanged: both still fail as `max` and `min`.
- **A client repository should delete two files after taking this release**:
  `git rm .github/dependabot.yml .github/CODEOWNERS`, then close any open Dependabot pull requests
  without merging them. *Updating CoreX in a client site* has the steps. `npm run verify:framework`
  then prints two `REMOVED` lines and passes. A later release that changes either file conflicts
  with the deletion once; keep it deleted.
- **Two checks stop running in a client repository.** "Validate dependency advisories" no longer
  runs there at all, and CodeQL no longer runs in a private one. Both show as skipped. A repository
  that requires either check in its branch protection has to stop requiring it.
- **`ModeDisclosure::describe()` returns fewer `consequences` and a new `reference` list.** The lines
  about how a mode works and how to leave it moved from the first to the second. Code that prints a
  mode's consequences prints both to say what it said before. `OperationsMode::cautions()` is new;
  `warnings()` returns what it always did.
- **Six strings in the `corex` text domain are new** on Operations & Security: "Current mode",
  "Switching to %s", "Declaring %s", "More about this mode", "Caution:" and the line shown when the
  selected mode is the one the site has declared. A site that ships its own translation of that domain shows them
  in English until they are translated.
- **A form or flow with an `email` field refuses addresses it used to rewrite.** A mistyped address
  such as `sal,ma@example.com` or `josé@example.com` now fails with the `email` error where it was
  accepted under a different address. A malformed address on a required field answers `email`, not
  `required`, so a site with its own message for either sees the other. An optional email field
  with a malformed address now fails validation; it used to be accepted with the field empty.
- **An `email` field with no `email` rule stores what was typed.** It used to store a cleaned
  address or nothing. Declare the rule on any field whose value is used as an address; the stock
  contact form does. CoreX's own senders check an address before using it.
- **Subscribe, call-request, application and registration requests refuse a mistyped address.** They
  answer 422 with the reason each already gave for an invalid address (`invalid_email` for an
  account, `invalid_fields` for an application), where they used to succeed under a different
  address. A front end that posts to these endpoints should show that answer by the email field.
- **A Guides support request with a mistyped reply address is sent without one**, and says "no
  address given", where it used to carry a different address.

## [0.43.1] — 2026-10-07

A patch release for what the first client site built on 0.43.0 found, and for thirteen dependency
advisories published since. One fix is a security fix: behind a trusted proxy, a visitor could
choose the address login protection counted its failures against. Four more correct things a
running site got wrong — a readiness check that could not pass, length rules that did not measure
length, a notification email that left out a form's fields, and a retention form that could end in
an error page. Nothing a site serves is rebuilt differently; what does change for a running site is
listed under Client impact, and the entry to read first there is the one about trusted proxies.

### Fixed

- **A retention action the form does not offer ended in WordPress's critical-error page.** The handler
  passed any posted action to the retention service, whose exception nothing caught. No submission was
  touched, but the operator got a crash. The handler now refuses the action and the screen says "That is
  not a valid retention action. Nothing was changed." (DECISIONS #239).
- **The "No default "admin" account" check could never pass.** It compared WordPress's answer with
  `null`, and WordPress answers `false` for a login nobody has, so the check warned on every site
  — including one with no such account — and each readiness evaluation published a blocker for it.
  It now passes when no user has that login.
- **A readiness blocker's title read as the opposite of the fault.** A check's label states the
  condition that passes, and the title was "<label> is not ready for production": "File editing
  disabled is not ready for production", "No default "admin" account is not ready for production".
  It is now "Readiness check not met: <label>".
- **`max_length` and `min_length` did not measure length when the answer was all digits.** They were
  registered as the same classes as `max` and `min`, which compare a numeric-looking answer as a
  number: a phone field declared `max_length:32` refused `01016999700` as too long, and `12` passed
  `min_length:3`. They are their own rules now and count characters, on the server and in the form
  runtime, which had no arm for either. The stock contact form had the fault under the other name —
  its message was `max:2000`, so a message of `2025` was refused — and uses `max_length` now
  (DECISIONS #241).
- **With CoreX Mail active, a code-defined form's notification left out its fields.** The default
  listener named the `contact-notification` template for every form, and a mailer that has
  templates renders the template in place of the body it is also given. That template prints a
  name, an email and a message, so a form with any other field was emailed without it, under the
  subject "New contact form submission". Only the `contact` form names that template now; every
  other form sends the generated table of all its fields (DECISIONS #243).

### Security

- **Behind a trusted proxy, a visitor could choose the address login protection counted against.**
  With trusted-proxy mode on, `ClientIpResolver` took the first address in `X-Forwarded-For` that
  was not a trusted proxy, reading from the left. A proxy appends the address it saw to whatever the
  client sent, so the leftmost entries are the client's own: sending a different invented address
  with each attempt kept every failure under a fresh address, and sending somebody else's put the
  failures against theirs. The resolver reads from the right now — the nearest hop that is not
  itself trusted — and stops at an entry that is not an address. A site with trusted-proxy mode off
  was not affected (DECISIONS #242).
- **Dependency advisories, a second October pass.** Thirteen advisories were published against a
  tree that had not moved, eleven of them on 2026-10-05 and 2026-10-06, and the gate failed on
  every pull request. Six are closed by taking patched releases (`source-map-js`, `compression`,
  `proxy-addr`, `shell-quote`, `sharp`, `smol-toml`). Two are closed by overrides: `lighthouse` 13
  replaces the six `@opentelemetry` instrumentation packages that were flagged and removes
  `extract-zip` from the tree, so its two exceptions are deleted; `postcss-selector-parser` is held
  at 7.1.6. Five have no release this tree can take and are bounded: four on `simple-git`, which
  `@wordpress/env` cannot yet use at the fixed major, and one on `sprintf-js`. All are in build,
  test and local development tooling; none is in a built asset (DECISIONS #244).

### Client impact

What in this release can change how a client site behaves or builds, whether or not the merge conflicts.
Read this before taking the release.

- **One string in the `corex` text domain is new**: "That is not a valid retention action. Nothing was
  changed.", on the Submissions screen. A site that ships its own translation of that domain shows it in
  English until it is translated.
- **A site with no user named `admin` loses one readiness blocker.** On Operations & Security the
  "No default "admin" account" check turns to a pass, the count of hardening warnings drops by one,
  and the notification about it resolves at the next readiness evaluation. A site that does have
  such a user keeps the warning.
- **One string in the `corex` text domain is replaced**: "%s is not ready for production" becomes
  "Readiness check not met: %s". An open readiness blocker takes the new title at the next readiness
  evaluation; a site that ships its own translation of that domain shows it in English until it is
  translated.
- **A form field with `max_length` or `min_length` accepts and refuses different answers.** An
  all-digit answer is measured by its number of characters, where it used to be compared as a
  number: digits inside the limit that were refused are accepted, and digits shorter than a minimum
  that were accepted are refused. The browser now checks both rules before submitting. `max` and
  `min` behave as before.
- **The stock `contact` form accepts a name or message made only of digits**, up to the same 120
  and 2000 characters.
- **With trusted-proxy mode on, every proxy between the visitor and WordPress has to be in the
  trusted ranges.** Login protection now takes the nearest hop that is not trusted as the client.
  Behind one listed proxy nothing changes for an honest visitor. Behind two, with only the nearer
  one listed — a CDN in front of a local reverse proxy, say — the CDN's address used to be skipped
  by accident and is now taken for the client, so every visitor through that CDN node shares one
  lockout count until the CDN's ranges are added. Lockouts already recorded stay keyed to the
  address they were recorded under.
- **With CoreX Mail active, the notification for a code-defined form other than `contact` looks
  different.** Its subject becomes `New "<slug>" form submission` and its body lists every
  submitted field, where it was the contact template's three rows. A site that edited the
  `contact-notification` template and relied on it for its other forms gives those forms an Email
  Studio route on `forms.<slug>.submitted`, which is tried before this default. The `contact` form,
  and any site without CoreX Mail, sees no change.
- **The dependency pass changes nothing a site serves.** Every built script and stylesheet is
  byte-identical to the ones built before it. For a client repository it is `package.json`, the two
  lockfiles and `docs-app/package.json`, all framework-owned: run `npm ci` after the merge.
- **`npm run env:start` carries four open `simple-git` advisories**, two rated critical, that
  `@wordpress/env` has no fixed release for. They concern what `simple-git` is given, which here is
  `wp-env.json`. A client repository that adds a git source to its own wp-env configuration should
  name only repositories it trusts.

## [0.43.0] — 2026-10-04

Two features for building a client's site on CoreX, and the fixes found along the way.
**Coming soon** is a fifth operations mode: visitors get a launch page the theme owns while the
site is built behind it, with a preview link for people who have no account. And a client
repository can now take framework releases by merging them, with a check that proves its framework
files are unmodified. Coming soon changes nothing until it is selected. What does change on a
running site is listed under Client impact; the one to read first is that submission retention
selects different records.

### Added

- **Coming soon is an operations mode** (spec 101). Spec 063 named it and spec 065 shipped without it;
  Maintenance mode could not stand in for it, because a 503 held for weeks tells a search engine the
  site is failing, and its page is CoreX's and not the client's.
  - While the mode is on, a signed-out visitor gets the coming-soon page at the home URL with a 200,
    and a temporary redirect to it from every other front-end address. Anybody signed in who can edit
    posts gets the real site. The admin, the login page, the REST API, AJAX and cron are never
    intercepted; `robots.txt` and the favicon are served normally; the sitemap lists the home URL only.
  - The page is a block template named `coming-soon`. CoreX registers a default that applies under
    any block theme, a theme's own `templates/coming-soon.html` replaces it, and a theme with no
    block templates gets a self-contained page.
  - A notice bar on every front-end page tells somebody served the real site that visitors are not,
    with a link to the page as a visitor sees it (`?corex_visitor_view=1`). In the admin it is a
    toolbar node.
  - **A preview link** shows the real site to somebody without an account: created, regenerated and
    revoked on Operations & Security, shown once, good for 14 days per browser, and removed when the
    site leaves the mode. Only a keyed hash is stored, and each action is in the history.
  - `wp corex mode get` and `wp corex mode set <mode> [--acknowledge] [--phrase=PRODUCTION]`, held to
    the same confirmations as the screen.
  - `wp corex make:site` generates `templates/coming-soon.html` in every client theme.
  - For a theme: the action `corex_coming_soon_enqueue_assets` and the body class `corex-coming-soon`,
    both only on that page. For client code: the filters `corex_coming_soon_bypass` and
    `corex_coming_soon_is_home`.
  - Documentation: *Coming soon mode*, in English and Arabic.
- **A client site the framework can be updated underneath** (spec 102). The framework tells a team to put
  a client site under `sites/<client>/`, and its own checks rejected exactly that. A client repository now
  passes them untouched, and can prove its framework files are unmodified.
  - `.github/repository-ownership.json` names the client-owned paths — `sites/**` and
    `.github/workflows/site-*.yml`. Everything else is the framework's, the repository root included.
  - `npm run verify:framework` compares every framework-owned path with the release recorded in
    `sites/<client>/corex-baseline.json`, and exits 1 on anything that differs without a recorded
    exception. `--record <release>` writes a new baseline. Git and Node only — nothing installed.
  - `wp corex make:site` generates the baseline record, `UPDATING-COREX.md`, and — for a site at
    `<repository>/sites/<client>` — the client's own CI workflow, `.github/workflows/site-<client>.yml`.
  - `wp corex make:site --starter` now ships a `phpunit.xml.dist` and test bootstrap, so its example test
    can be run.
  - `scripts/setup-wordpress.ps1` links every client plugin and theme under `sites/` into the local
    WordPress, without activating them.
  - A CI job, `client-site-layout`, generates a real starter site and runs every root check against it.
  - Documentation: *Updating CoreX in a client site*, in English and Arabic.

### Fixed

- **The mode form could apply a mode other than the one on screen.** After a submission came back for
  a missing confirmation, the form showed the proposed mode and its confirmation while the select
  underneath still held the site's current mode; a script moved it on load. Submitted before that
  script ran, or with no script, it applied the wrong mode. The proposed mode is now selected in the
  markup.
- The scaffold readiness check called any `{{` or `}}` in a generated file an unresolved placeholder,
  so no generated block template with a nested attribute could pass. It now uses the renderer's own
  definition of a placeholder.
- **Submission retention stopped reaching records once 500 had been anonymized or archived.** Each run
  was handed the newest 500 private submissions older than the window, whatever had already been done to
  them. Anonymizing and archiving leave a submission private, so the next run got the same 500, applied
  the action to them again — a second timeline event, a new update time — reported them as handled again,
  and never reached an older submission that still held personal data. A run now skips the submissions
  its action has nothing left to do for and takes the oldest first, and the Inbox count no longer
  includes anonymized submissions (DECISIONS #232).
- **Every WP-CLI request on a site with `WP_DEBUG` on logged "Translation loading for the `corex` domain
  was triggered too early".** The CLI and Media providers built their command definitions on
  `plugins_loaded`, and a definition translates its help text. The commands are now registered on
  `cli_init`, the hook WP-CLI fires on `init`. Command names, options and help text are unchanged
  (DECISIONS #235).
- **`wp corex make:site --path=<dir>` never worked.** `--path` is a WP-CLI global, taken as the WordPress
  install before any command sees its arguments, so the site directory never arrived. The README, the
  guides and the readiness report all documented that form. The site directory is now `--dir`.
- `tests/repo-hygiene.test.js` forbade `sites/` in every repository, including a client's own. The rule
  now applies to the framework's repository only.
- The root linters, Jest and `npm run format` swept `sites/`. They read the ownership file now.
- Jest's `<rootDir>` ignore patterns silently stopped applying on Windows in a checkout whose own path
  holds a dot-directory, which is every agent session worktree. The ownership-derived patterns are anchored
  in a way that survives it.
- **The retention form on the Submissions screen said "trash" whichever action ran.** Its confirmation box
  read "Confirm moving due submissions to the recoverable trash" and its result notice "N submissions moved
  to trash", for Archive and Anonymize as well — and anonymizing cannot be undone. The box now asks to
  confirm the selected action and says that anonymizing cannot be undone, and the notice says what the run
  did: archived, moved to trash, or anonymized (DECISIONS #233).
- **A retention run refused for want of confirmation was shown as a success.** Applying retention without
  ticking the confirmation box runs nothing, and the notice that says so — "Confirm the retention action
  before applying it" — carried the success tick. It is now a warning (DECISIONS #237).

### Changed

- On Operations & Security, the overview row "Maintenance: Off" is now "Public site", with three
  answers — open to visitors, the maintenance page, the coming-soon page — and the history is headed
  "Mode and preview link history".
- Scheduled CI runs and the documentation deploy run in the framework's repository only. A client
  repository inherits the workflows and no longer runs them. Pull-request and push runs are unchanged.

### Client impact

What in this release can change how a client site behaves or builds, whether or not the merge conflicts.
Read this before taking the release. *(This section is required from this release on — see
`CONTRIBUTING.md`.)*

- **No front-end or admin runtime behaviour changes from spec 102.** Everything it adds is tooling, the
  site generator, CI and documentation.
- **Coming soon mode changes nothing until somebody selects it.** A site that never enters the mode
  serves exactly what it served before.
- **`OperationsMode::all()` returns five values, not four.** Client code that enumerates the modes, or
  matches on them without a default, now meets `coming-soon`.
- **Rows in the `corex_operations_mode_log` option can be events.** A preview-link event is a row with
  an `event` key and no `from` or `to`. `OperationsModeStore::history()` still returns mode changes
  only; code that reads the option directly has to skip rows without `to`.
- **`wp corex make:site` writes one more file**, `templates/coming-soon.html`, in a new client theme.
  An existing client theme is not touched: it serves CoreX's default page until it adds that file.
- **Names CoreX now uses on the front end**: the query arguments `corex_preview` and
  `corex_visitor_view`, the cookie `corex_preview`, and the option `corex_preview_access`.
- **`OperationsModeController` and `OperationsSecurityScreen` take different constructor arguments.**
  Both are resolved by the container; only code that constructs them by hand is affected.
- **Submission retention selects different records.** On a site that has anonymized or archived
  submissions, the "currently due" count on the Submissions screen drops by the number already
  anonymized, and the next Anonymize or Move to trash run acts on submissions earlier runs never reached.
  Three things an operator may have relied on change: Move to trash no longer trashes an anonymized
  submission, Archive no longer touches an archived or anonymized one, and a run takes the oldest due
  submissions first instead of the newest. Nothing runs by itself — retention still acts only when
  someone confirms it on that screen.
- **Four files conflict once, if your repository edited them** to make the framework's checks accept
  `sites/`: `tests/repo-hygiene.test.js`, `.stylelintignore`, `eslint.config.js`, `jest.config.js`. Take
  the framework's version of each — the edits are no longer needed.
- **A client repository created before this release has no baseline record.** Adopt it at this update:
  *Updating CoreX in a client site → A client repository that predates this page*.
- **`wp corex make:site --path=<dir>` is now `--dir=<dir>`.** The old form never ran, so no working
  script can depend on it.
- **`scripts/setup-wordpress.ps1` activates the framework's plugins by name**, not with `--all`. A plugin
  you installed into `./wp` by hand is no longer switched on by the script. It also leaves a linked client
  theme active instead of switching back to the Corex theme.
- **Scheduled CI and the documentation deploy no longer run in your repository.**
- **Four strings in the `corex` text domain are new**, all on the Submissions screen's retention form: the
  confirmation label, and the result notices for an archive, an anonymization and a run whose link names
  no action. A site that ships its own translation of that domain shows them in English until they are
  translated. "N submissions moved to trash" is unchanged and keeps its translation.

## [0.42.1] — 2026-10-04

A patch release for one defect that reached production installs, and for a month of maintenance
behind it. On a running site one thing changes: seven WP-CLI commands that a production install
lost are back.

### Fixed

- **`composer install --no-dev` removed seven WP-CLI commands, `wp corex migrate` among them.** Not
  an error — absent, with `wp help corex` still listing the namespace. A production install is a
  `--no-dev` install, so a site deployed by the documented path could not run its own schema
  migration. `nikic/php-parser`, which `ClassDocReader` uses at runtime, was declared nowhere and
  arrived only through a dev dependency; it is now a production dependency. And command registration
  is a lazy map rather than a construction sequence, so a command that cannot be built fails on its
  own invocation instead of removing every command declared after it. `MediaServiceProvider` had
  the same shape and was converted. (Issue #201, DECISIONS #225)
- **Dependency advisories, in two passes.** September's cleared the backlog, including a critical
  `astro` finding (GHSA-26w7-cxv4-gfx2) in the documentation site. October's took every patched
  release that existed, across ten packages. The gate passes with three bounded exceptions —
  `extract-zip` twice and `braces` — none of which has a patched release to take. All three are in
  build and test tooling. (DECISIONS #226, #227)
- **The browser suite failed on pull requests that changed no runtime code.** WordPress's login
  page moves focus to the username field 200ms after it renders; when that landed while the
  sign-in helper was typing the password, the password went into the username field and the form
  would not submit. The helper now confirms each field holds its credential before submitting.
  (DECISIONS #228)
- `npm run lint:js`, `lint:css` and `test:js` reported on WordPress core, or never finished, on a
  machine with the local multisite install or an agent session worktree. They no longer walk
  `wp-ms/` or `.claude/worktrees/`, and Jest's module map no longer indexes generated copies.

### Changed

- **The dependency gate runs on every pull request**, not only on those that touch a manifest. An
  advisory is published against a tree that did not move, so the path filter meant the gate was
  consulted only by the pull requests least likely to be looking for it. (DECISIONS #224)
- `@wordpress/components` 38 → 41, `@wordpress/element` 8.6 → 8.8, `@wordpress/i18n` 6.27 → 6.29
  and `@playwright/test` 1.62 → 1.63. No workspace bundles `@wordpress/components`: the build reads
  it from the copy WordPress ships, so the npm package decides what Jest renders against and
  nothing a browser loads. (DECISIONS #229)
- The browser job in CI keeps the server's logs when it fails, in a file it proves it can write to
  on every run. A 500 from `GET corex/v1/flows` on 2026-10-04 could not be explained because
  nothing had kept them; its cause is still unknown. (DECISIONS #228)

## [0.42.0] — 2026-09-04

The release that began as "close the open pull requests" and became finding out that three things the
repository said about itself had stopped being true: `main` was green, the dependency gate passed, and
nothing was in flight. None of them held, and none of them had been written dishonestly — each was
true when written and quietly expired.

### Added

- **Multisite is implemented, not advertised.** `README.md` claimed support and
  `docs/en/06-cookbooks/multisite.md` gave a page of advice about it; across four plugins, twelve add-ons and
  a theme there were exactly three runtime references to Multisite, one of them a diagnostics row rendering
  the word "Yes". Spec 100 builds the runtime floor: a `Corex\Multisite` context layer, per-site schema
  migration driven by `wp_initialize_site` and `wpmu_drop_tables`, a network configuration layer, and
  activation-scope awareness throughout. Single-site installs are unaffected by construction — the
  `SingleSite*` contexts make no WordPress calls and register no `switch_blog` listener.
- `wp corex migrate [--network]` — migrates one site or every site in a network, in batches, continuing past
  a failing site and reporting per-site outcomes.
- `scripts/setup-wordpress.ps1 -Multisite` — builds the three-site network the multisite suite needs, in
  `./wp-ms`, mirroring what CI provisions.
- A multisite integration suite (`composer test:multisite`), run in CI as `integration-multisite`.
- A nightly CI run on `main`. CI installs WordPress with `--version=latest`, so a result depends on two
  inputs and only one of them is in git; WordPress 7.1 broke two browser specs and `main` looked green for
  three weeks because nothing re-ran. `main` can now go red without a commit — that is the design
  (DECISIONS #221).

### Fixed

- **Network-activating a CoreX add-on silently disabled it on every site in the network.** `Boot` read
  `active_plugins` and never `active_sitewide_plugins`, so the provider was dropped and the add-on registered
  no bindings, hooks, blocks or REST routes — with no notice, no error, and the Network Plugins screen still
  reporting it active. Every activation call site now goes through `PluginActivationInspector`.
- Site-scoped state leaking across `switch_to_blog()` — asset manifests, branding, the HR notification
  address, queued-job site context, and notification preferences off the main site.
- `docs/en/06-cookbooks/multisite.md` told operators to put a network-wide default in `.env` "and let a site
  override it with its own option". The precedence runs the other way and always did: `.env` sits above every
  option, so a site cannot override it. The page carried `last_verified: null`, which was the only accurate
  thing on it.
- Two browser specs that WordPress 7.1 broke, neither of which was a CoreX regression, and one sign-in race
  that was: `security-access.spec.js` toggles login protection for the whole site while other spec files run
  on other workers, and the shared sign-in helper only looked for the login endpoint once
  (DECISIONS #222).
- Dependency advisories across all three ecosystems — `js-yaml`, `nanoid`, `fast-uri`, `qs` and the
  `express` chain. The `fast-uri` override had been pinned at `^3.1.5`, the vulnerable floor itself.

### Changed

- **`.env` feature flags now resolve through `DotenvSource` rather than `getenv()`.** A flag set in `.env`
  was previously read by a different mechanism than every other setting; it now follows the documented
  precedence chain like everything else.
- **A network-activated add-on now boots.** Stated separately from the fix above because it is a behaviour
  change for any install that had network-activated an add-on and worked around it being inert.
- **Schema is no longer installed on every boot.** It used to be created unconditionally as corex-config
  booted. It is now registered with `SchemaRegistry` and applied by `SchemaSelfHeal` from **admin or cron
  only** — a `wp plugin list` must not write tables (FR-035) — by `wp_initialize_site` for a new site on a
  network, or explicitly by `wp corex migrate`.

  For a site activated through wp-admin nothing changes. **For a site activated entirely through WP-CLI the
  tables do not exist until the first admin request, a cron run, or `wp corex migrate`.** Provisioning that
  never loads wp-admin — CI, containers, scripted deploys — should call `wp corex migrate` after activating,
  which is what `.github/actions/provision-wordpress` and `scripts/setup-wordpress.ps1` now do.

## [0.41.0] — 2026-08-05

Eight specs, opened by three defect reports and one look at what the repository was claiming about
itself. The thread through them: **a claim nobody checks stops being true, and the checking is the
deliverable.**

### Removed

- **The WordPress contextual Help tab, from every CoreX admin screen.** Spec 084 had filled it with
  guides, reasoning that a panel on every admin screen was a surface standing empty. Fair reading,
  wrong surface: the panel belongs to wp-admin, and a CoreX screen is a full-bleed product built to
  hide wp-admin — so it opened *above* the shell and pushed the whole product down the page.
  Unregistered rather than hidden with CSS, on `admin_head` at `PHP_INT_MAX`, which is the last point
  that beats the render and the first that is later than every point at which help can be
  registered — including by core or by a plugin CoreX has never heard of. It lives in `corex-config`,
  so deactivating the optional Guides add-on cannot hand the tab back. (Spec 097, DECISIONS #216)
- **`.github/workflows/e2e.yml`**, which had failed every night for at least a week and could never
  have passed: no `COREX_BASE_URL`, no admin credentials, no seeded fixtures. `ci.yml`'s browser job
  supersedes it in every dimension and is required on `main`. A red that is always red carries no
  information. (Spec 099, DECISIONS #219)

### Fixed

- **Three CoreX admin surfaces overran their own shell, and `overflow: clip` hid it.** The two
  browser failures blocking this release read as unrelated — a click that "intercepts pointer events"
  for sixty seconds, and a boolean overflow assertion at one width. They were one mistake in three
  places: a CSS length written as a preference and read by the engine as a floor. The Forms catalog
  row carried 37rem of track minimums, so it was clipped out of existence and
  `document.elementFromPoint()` at the button's own centre returned the wrapper — **the control was
  not there**. Blog Pro's `auto-fit` grid could not drop to one column, and `.corex-select` could not
  fit a toolbar narrower than its own minimum. Fixed with `minmax(0, …)` and `min(Xrem, 100%)`;
  neither assertion was changed. (Spec 097, DECISIONS #217)
- **The Guides screen introduced itself as "Corex / Framework".** The breadcrumb map never learned the
  `guides` section, so the one screen somebody opens *because they are lost* had the wrong name on it.
  Found by widening the browser route matrix from twelve routes to all fourteen. (Spec 097)
- **Two high advisories**, both transitive, both closed by an override rather than an exception:
  `brace-expansion` below 5.0.9 (DoS bypassing the CVE-2026-14257 mitigation) and `fast-uri` below
  3.1.5 (host confusion via a backslash authority introducer).
- **A fixture the repository silently refused to track.** `.gitignore` carried `tests/e2e/fix*/` for
  spec 060's throwaway render output, and that glob swallowed `tests/e2e/fixtures/`. `git add` said
  nothing and the commit looked complete; CI found it.

### Added

- **`tests/repo-hygiene.test.js`** — nine checks, asserted against what git actually *tracks* rather
  than against the working tree. It fails on a tracked generated directory, archive, raw design
  export, editor artifact or key; on root Markdown outside the canonical set; on a prose version
  statement disagreeing with `package.json`; on a reworded sentence the release stamper anchors to;
  and on **any document announcing a release for which no tag exists**. That last one was not
  hypothetical. (Spec 098, DECISIONS #218)
- **`tests/e2e/admin-help-tab.spec.js`** — twelve browser tests asserting absence across all fourteen
  CoreX routes, in light and dark, LTR and RTL, at four widths and at 200% zoom, including that
  forcing core's own wrapper visible by script still reveals no panel, that native WordPress screens
  keep their Help tab, and that a **client-registered** guide still appears. Deliberately not skipped
  when its fixture is missing: a browser check that opts out when its fixture is absent reports green
  for the one condition it exists to catch.
- **`scripts/sync-project-status.mjs`** — the docs-site status page is now generated from
  `PROJECT-STATUS.md` and held to it by a test, instead of being a hand-written second copy that was
  62 lines against the source's 123.
- **`screenshot: 'only-on-failure'`** in the Playwright config. It costs nothing on a passing run, and
  its absence is why this release's two layout failures arrived as a boolean and a timeout with no
  picture of the screen.

### Changed

- **`PROGRESS.md`: 4,589 lines → 68.** Forty stacked `RESUME HERE` blocks, append-only, of which only
  the top was ever read. Each root document now answers exactly one question and none answers
  another's; `README.md` carries the table. `ROADMAP.md` gave up a foundation table three releases
  stale and ~190 lines narrating merged specs. `DECISIONS.md` was deliberately **not** trimmed — it is
  the one file whose value is cumulative. (Spec 098, DECISIONS #218)
- **The four `COREX-*.md` working documents moved to `docs/internal/`**, leaving the root to the
  canonical set. This required a PATCH amendment to the constitution (1.2.1 → 1.2.2), whose
  source-of-truth hierarchy named `COREX-FRAMEWORK.md` by its old path, and 29 live files repointed.
  `specs/`, `CHANGELOG.md` and `DECISIONS.md` were not rewritten: what they say was true when written.
- **`@wordpress/components` 37 → 38 and `@wordpress/scripts` 33 → 34**, plus `@wordpress/i18n`,
  `@wordpress/env`, `@playwright/test` and two GitHub Actions. Seven of eight open Dependabot pull
  requests consolidated into one. Both majors were tested against the build, Jest, both linters and
  the full Playwright suite in a real browser, because **a green advisory check proves an advisory is
  closed, not that the software still works**. (Spec 099, DECISIONS #220)
- **The precedence between the four instruction files** is stated once, in `AGENTS.md`, and referenced
  rather than restated by the others.

### Earlier in this release

Specs 092–096 merged before the work above and are part of v0.41.0:

- **The published docs site had 85 broken links, not the seven reported.** v0.40.0 gave `docs-app` a
  `base`; Astro rewrote what it owns and left every hand-written markdown link alone. Applied at build
  time rather than typed into 84 links, with a test asserting the **built** output. (Spec 092)
- **The support email was broken before it was unstyled** — a newline-joined plain-text body stamped
  `Content-Type: text/html` by Corex Mail's driver, arriving as one run-on paragraph. The rendering
  now follows the transport. (Spec 093)
- **The in-admin guide went from 4 guides to 16**, 40 topics and 14 screenshots — every CoreX admin
  screen, all 42 settings fields, and an orientation guide gated on `read` because a contributor with
  two capabilities is *more* lost than an administrator. (Specs 094, 096)
- **One fixture user per browser spec that signs in.** Two spec files sharing one editor tripped
  CoreX's own login lockout from the CI runner's single address, and the failure landed in whichever
  file ran second as "could not sign in", nowhere near the cause. The lockout policy is untouched:
  it is the product working. (Spec 095)

### Verification

Pest unit **1727** · integration **356** · Jest **442** across 54 suites · Playwright **144** locally,
**141** on a fresh install · docs site **922 pages** (54 authored + 868 generated class reference)
with links checked against the built output ·
dependency gate **PASS**, 0 findings and 0 exceptions across Composer, npm-root and npm-docs ·
`wp corex readiness 0.41.0` PASS · dist builds and verifies · `composer validate --strict`, both
linters and `git diff --check` clean.


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
