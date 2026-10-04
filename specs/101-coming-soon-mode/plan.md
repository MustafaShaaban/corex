# Implementation Plan: Coming soon is a mode, not a maintenance page

**Spec**: [spec.md](spec.md) · **Branch**: `spec/101-coming-soon-mode`

**Depends on** spec 102 being on `main`: this plan changes `make:site`, which 102 also changed.

## Constitution Check

| Principle | How this change satisfies it |
|---|---|
| I theme is a skin | The theme supplies a template and nothing else. Who passes, what is redirected, the preview link and the mode itself all live in `corex-config`. Deactivating the theme changes how the page looks, not whether the site is closed. |
| II plugins boot themselves | The default page is registered by the plugin, so the mode works under any block theme, and under a theme with no block templates it falls back to a self-contained page. Nothing waits on the Corex theme. |
| III thin controllers / fat services | The rules for changing mode move out of `OperationsModeController` into one service, because the command line now needs the same rules. The controller and the command both become printers. |
| IV everything injected | The guard, the decision, the preview access and its store are container-resolved. The decision takes facts, not WordPress. |
| V runtime tokens | The default page, the notice and the preview banner use `theme.json` variables and the CoreX admin tokens. No raw colour or size. |
| VI conditional assets | The banner's styles load only on a response that shows a banner. A theme's page-specific assets get an action that fires only when the coming-soon page is being served. |
| VII declarative security | The preview-link actions are `admin_post` handlers behind `AdminGuard`. No hand-rolled capability or nonce check. |
| VIII RTL-first | Logical properties throughout; the page, notice and banner are in the RTL browser matrix. |
| IX no optional dep is hard | The home test has a filter for multilingual plugins rather than a call into one. |
| X spec is source of truth | Planning found two statements in the spec that the code contradicts. They are amended in the spec, in the same commit as this plan, and flagged for the owner. |

## The architecture, in one sentence

**One pure decision says what a request gets; a guard gathers the facts and acts on the answer; and
the page itself is rendered by WordPress, from a template.**

## What planning changed in the spec

**The default page cannot come from the Corex theme.** FR-008 and User Story 6 assumed a client
theme inherits it. It does not: `packages/cli/stubs/site/theme-style.stub` declares no `Template:`,
so a generated client theme is a standalone block theme, and the existing client site's is the
same. A default shipped in `theme/templates/` would be reachable only while the Corex theme itself
is active, which on a client site it is not.

WordPress has had the right seam since 6.7: `register_block_template()` lets a plugin register a
block template, and when templates are collected, a registered one is dropped if the theme has a
template file with the same slug (`get_block_templates()` in `block-template-utils.php`, read in
WordPress 7.1.2). So `corex-config` registers `coming-soon`, a client theme overrides it with
`templates/coming-soon.html`, and either can be edited in the Site Editor. FR-008 is reworded to
say that. Step 2 proves it in a test before anything is built on it.

**The preview link can only be shown once.** FR-013 says the secret is not stored in a readable
form; User Story 4 has the operator copy it. Both are true only at the moment of creation. Stated
as an assumption.

## What a request gets

The whole behaviour is this table, and `ComingSoonDecision` is this table as code. Rows are tried
in order; the first that matches decides.

| # | The request | Outcome | Cacheable |
|---|---|---|---|
| 1 | The mode is not Coming soon | pass | unchanged |
| 2 | Admin, login, REST, AJAX or cron | pass | unchanged |
| 3 | Carries a `corex_preview` value | claim it: valid → set the cookie, redirect to the same address without it. Invalid → carry on as if it were absent | no |
| 4 | Client code allowed it through (FR-018) | pass | unchanged |
| 5 | `robots.txt` | pass | unchanged |
| 6 | Signed in and can edit posts, asking to see the page as a visitor | serve the coming-soon page | no |
| 7 | Signed in and can edit posts | pass, with the notice | no |
| 8 | Holds valid preview access | pass, with the banner | no |
| 9 | The sitemap address | 200, a sitemap listing the home URL only | yes |
| 10 | The home URL | 200, the coming-soon page | yes |
| 11 | Anything else, feeds included | 302 to the home URL | no |

A signed-in user who cannot edit posts matches none of 6–8 and falls through as a visitor, which
is FR-007.

## Decisions

**1. The decision is a pure function of facts.** `ComingSoonDecision::for(ComingSoonRequest)`
returns one of four outcomes and reads nothing. `ComingSoonGuard` is the only class that asks
WordPress who is signed in, what the path is and whether a cookie is present. `MaintenanceGuard`
mixes the two in one method; here the table above is a unit test with one case per row. *Rejected:* extending `MaintenanceGuard` with a second mode — different
status, different audience, different page, and SC-006 requires its tests to pass unchanged.

**2. The page is rendered by WordPress's template canvas, not echoed by the guard.**
`MaintenanceGuard` writes a complete document and exits, which is right for a card that must
survive a broken theme. A launch page has blocks, a form and the theme's styles; it has to go
through the normal render. The guard decides on `template_redirect` at priority 0 and, for the
serve outcome, swaps the template on `template_include`. The self-contained `StandalonePage` is
used only when the active theme has no block-template support at all (FR-009).

**3. "Home" is decided by the request path, with a filter.** `is_front_page()` answers for the
query WordPress resolved, and a front page that is a static page, or redirects, is one of the
spec's edge cases. The path against the home URL's path is what the visitor asked for. A
multilingual plugin's per-language home is admitted through `corex_coming_soon_is_home`, so no
plugin is called by name.

**4. The preview secret is in the visitor's cookie and only its keyed hash is on the server.** The
link carries a random token. Claiming it sets an `HttpOnly`, `SameSite=Lax` cookie holding the
token for 14 days and redirects to the address without it. The option stores an HMAC of the token,
when it was issued and by whom. Regenerating replaces the hash, revoking deletes it, and leaving
the mode deletes it — each ends every access at the next request, with no list of sessions to
maintain. *Rejected:* a server-side session table (state to expire and clean up for the same
result), and a signed, self-expiring token with no stored state (cannot be revoked).

**5. One service changes the mode, for the screen and the command line alike.** FR-017 wants the
same confirmation rules and the same history from both. Today those rules live inside
`OperationsModeController::handle()`. `ModeChangeService::apply()` takes a request — target mode,
actor, what was confirmed — and returns what happened; the controller turns that into a redirect
and the command into an exit code. It is also the one place that removes the preview link on
leaving the mode (FR-012a), so that cannot be true of one entry point and not the other. This step
is a refactor with no behaviour change and lands first, with the existing controller tests as its
proof.

**6. Preview-link events go in the mode history, as their own kind of row.** The log holds
`{time, user, from, to}`. It gains an optional `event`, so creating, regenerating and revoking are
recorded with the operator and never with the secret (FR-012). A link removed by leaving the mode
is not a separate row: the mode change beside it is the record. `history()` keeps answering
mode changes for the consumers that only want those.

**7. The command is `wp corex mode`.** `get` prints the mode and whether it was declared or
inherited. `set <mode>` takes `--acknowledge` for Coming soon and Maintenance and
`--phrase=PRODUCTION` for Production, and fails with a non-zero exit, changing nothing, without
the one its mode needs. Registered through the lazy command map, so its dependencies cannot
unregister its neighbours.

**8. The notice and the banner are one front-end element with two messages.** WordPress's toolbar
can be switched off per user, so it cannot be what FR-016 relies on at the front end. A slim bar
rendered at `wp_body_open` says, to a signed-in user who passes, that visitors see the coming-soon
page, with a link to view it as a visitor and — for an administrator only — to the screen that
changes it; and to a preview holder, that this is a private preview the public cannot see yet,
with no link at all. In the admin the same message is a toolbar node, which there is always
present. Neither can be dismissed.

**9. A theme's page-specific assets load through an action.** `corex_coming_soon_enqueue_assets`
fires on `wp_enqueue_scripts` only when the coming-soon page is the response, and the body carries
`corex-coming-soon`. A designed page has its own stylesheet and fonts, and Principle VI says they
load only where they render.

**10. The sitemap is answered by the guard.** One `urlset` with the home URL, 200. Filtering core's
sitemap providers would leave their sub-sitemap addresses resolving; answering the index address
directly and redirecting the rest is the smaller surface and is exactly what the clarification
says.

**11. The browser spec runs in its own Playwright project, after the main one.** Turning the mode
on changes what every anonymous and subscriber request receives, and `access-request.spec.js`
drives subscribers. The suite runs files across workers, so a mode switched on inside one spec
would fail others at random. The coming-soon spec gets a project that depends on the main one,
runs alone, and restores the previous mode whatever happens.

**12. `make:site` generates `templates/coming-soon.html`.** It is the client theme's own file and
replaces CoreX's default, so a new site's page is the one in its repository from the start
(FR-020, SC-005).

## Files

```
plugins/corex-config/src/Operations/
  OperationsMode.php              + COMING_SOON; all, confirmation, affectsPublic, describe, warnings
  ModeDisclosure.php              acknowledgement, summary and consequences for the mode
  OperationsModeStore.php         log rows gain an optional event; record(); history filters
  ModeChangeService.php           NEW  the rules for changing mode; removes the preview link on leaving
  ModeChangeRequest.php           NEW  target, actor, what was confirmed
  ModeChangeResult.php            NEW  saved / unchanged / needs-confirmation / blocked / invalid
  OperationsModeController.php    request -> service -> redirect, and nothing else
  ComingSoonRequest.php           NEW  the facts about one request
  ComingSoonDecision.php          NEW  the table above
  ComingSoonGuard.php             NEW  gathers the facts; redirects, serves, or passes
  ComingSoonTemplate.php          NEW  registers the default; resolves the canvas; standalone fallback
  ComingSoonSitemap.php           NEW  the one-URL sitemap
  PreviewAccess.php               NEW  create, regenerate, revoke, claim, holds, clear
  PreviewAccessStore.php          NEW  interface, and the option-backed implementation
  PreviewLinkController.php       NEW  admin_post: create / regenerate / revoke, behind AdminGuard
  ComingSoonNotice.php            NEW  the front-end bar and the toolbar node
plugins/corex-config/templates/coming-soon.html   NEW  the default page, core blocks and tokens
plugins/corex-config/assets/css/coming-soon-bar.css   NEW  loaded only with the bar
plugins/corex-config/src/Security/OperationsSecurityScreen.php   the preview-link card; history rows for link events
plugins/corex-config/src/Security/securityCenterState.js         the mode list gains coming-soon
plugins/corex-config/src/ConfigServiceProvider.php               bindings and registration

packages/cli/src/Commands/ModeCommand.php          NEW  get / set, pure execute() + a thin run()
packages/cli/src/Commands/ModeCommandResult.php    NEW
packages/cli/src/CliServiceProvider.php            registration in the lazy map
packages/cli/src/Site/SiteScaffolder.php           the client theme's templates/coming-soon.html
packages/cli/README.md                             the command

theme/patterns/maintenance.php                     no longer describes itself as a coming-soon notice

tests/Unit/Operations/
  OperationsModeTest.php            the "coming-soon is invalid" assertion replaced by tests of the mode (FR-022)
  ComingSoonDecisionTest.php        NEW  one case per row of the table, and the order between rows
  PreviewAccessTest.php             NEW  against an in-memory store
  ModeChangeServiceTest.php         NEW
  ModeDisclosureTest.php            the new mode's confirmation and wording
tests/Unit/Cli/ModeCommandTest.php  NEW
tests/Unit/Cli/SiteScaffolderTest.php   the generated template
tests/Integration/Operations/
  ComingSoonModeTest.php            NEW  real WordPress: the guard, the template resolution, theme-over-plugin
  PreviewAccessIntegrationTest.php  NEW  the option store, and removal on leaving the mode
tests/e2e/coming-soon.spec.js       NEW  its own project
tests/e2e/playwright.config.js      the second project

docs/en/03-operations/coming-soon.md      NEW  the mode, the link, the command, the template, caches, REST
docs/ar/03-operations/coming-soon.md      NEW  translated
docs/en/03-operations/operations-and-security.md   the mode in the table, and a link
docs-app/src/content/docs/guides/coming-soon.md    NEW, and the sidebar entry
docs-app/src/content/docs/guides/client-site.md    the generated template
CHANGELOG.md   PROGRESS.md   DECISIONS.md   PROJECT-STATUS.md
```

## Order of work

Each step leaves every suite green and is useful without the next.

1. **Extract `ModeChangeService`.** No behaviour change; the existing controller and no-op tests
   are the proof. (Prepares FR-017, FR-012a.)
2. **Prove the template seam before building on it.** An integration test, written first, that a
   plugin-registered `coming-soon` template is found, and that a theme's template of the same name
   replaces it. If WordPress does not behave that way, Decision 2 changes before anything depends
   on it.
3. **The mode, the decision, the guard and the default page** (US1, US2, US3.1, US3.5 · FR-001 to
   FR-010, FR-015, FR-018, FR-019, FR-022). One slice, because a selectable mode that does nothing
   would be a false claim on the Operations screen.
4. **The notice and the visitor view** (US3.2 to US3.4 · FR-016).
5. **The preview link and its banner** (US4 · FR-011 to FR-014, FR-016a).
6. **The command** (US5 · FR-017).
7. **The generated template and the asset action** (US6 · FR-020).
8. **Documentation, release notes, and the browser project** (FR-021, FR-023, SC-007).

## How it is proved

**Unit, headless.** The decision is tested row by row and for the order between rows — an editor
asking for the visitor view gets the page, a subscriber is a visitor, a preview holder is not
redirected, an invalid preview value changes nothing. Preview access is tested against an
in-memory store: a claimed token holds; a regenerated, revoked or cleared one does not; nothing
stored equals the token. The mode-change service is tested for every confirmation rule from both
callers' points of view, and for removing the link on leaving.

**Integration, real WordPress.** The template seam in step 2. The guard's facts — a subscriber, an
editor and an administrator each resolved from real users. A form submitted to the REST endpoint
while the mode is on (US2.3). The option store, and that leaving the mode by the service empties it.

**Browser, in its own project.** Signed out: home is 200 and shows the page; a published page, a
post and an address that does not exist each answer with a 302 to home and none of their content;
`robots.txt` is served; the sitemap lists one URL; a feed redirects. As an administrator and as an
editor: every address is the real site and the bar is present, with the Operations link only for
the administrator. As a subscriber: redirected. With the preview link: the real site, the banner,
the secret gone from the address bar; then regenerated, revoked, and the mode left, each ending
access at the next request. The page, the bar and the banner in LTR and RTL, light and dark,
mobile and desktop (SC-007).

**Unchanged, and asserted to be.** Maintenance mode's unit and integration tests pass without a
line changed (SC-006).

## Risks, and what was done about each

- **The template seam is an assumption until step 2 runs.** That is why step 2 exists and comes
  before the slice that depends on it.
- **A page cache in front of the site.** The spec's headers are set — non-cacheable for every
  response that depends on who is asking. What headers cannot do is evict what a cache already
  holds, so a page cached while the site was open can be served after the mode is switched on.
  The guide says to purge on switching. No purge hook is added: nothing in the framework would
  call it today.
- **The mode is named in four places outside `Operations/`** — the screen, its state script, the
  company kit's blueprint (a page slug, unrelated) and the theme pattern. Each is in the file list;
  step 3 ends with a search for any `match` on the mode that lacks the new value.
- **Published content is still readable through the REST API.** The spec says so and the guide
  will. This mode closes the front end; it is not a confidentiality control.
- **An editor who can edit posts sees the real site.** That is the owner's clarification, and it
  means a site that hands out contributor accounts freely is more open than "administrators only".
  The mode's description on the screen states who passes.
- **The browser spec changes global state.** Its own project, run last and alone, restoring the
  previous mode in a `finally`.

## Deliberately not done

- No change to `MaintenanceGuard`, and no shared base class with it.
- No purge hook, no countdown, no scheduled launch, no built-in form — the last three are the
  spec's own out-of-scope list.
- No Spec Kit agent-context edit to `CLAUDE.md`, for the reason recorded in spec 102's plan.
- No default in `theme/templates/`. It would be reachable only with the Corex theme active, and
  two defaults for one page is how they come to disagree.
