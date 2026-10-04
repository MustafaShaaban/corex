# Tasks: Coming soon is a mode, not a maintenance page

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md)

Eight phases, in the plan's order. Each leaves every suite green and is useful without the next.
Tests are written first and seen to fail; a task that names a test names the one that fails before
its code exists.

**Before starting:** spec 102 has to be on `main`, and this branch merged up to it. Phase 7 changes
`make:site`, which 102 also changed.

## Phase 1 — One service changes the mode

A refactor. Nothing an operator or a visitor can observe changes.

- [x] **T001** `ModeChangeRequest` and `ModeChangeResult` in
      `plugins/corex-config/src/Operations/` — the target mode, the actor, the time and what was
      confirmed; and saved, unchanged, needs-acknowledgement, needs-phrase, blocked or invalid,
      with the mode applied and the mode proposed kept apart, as the screen's redirect keeps them.
- [x] **T002** Pest: `ModeChangeServiceTest` — every rule `OperationsModeController::handle()`
      held, stated against the service: an unknown mode is invalid; a mode needing an
      acknowledgement without one needs confirmation and writes nothing; production without the
      phrase needs it; production with it goes through the launch service; re-applying the declared
      mode is unchanged and writes nothing; declaring an inherited mode is a change. Twelve cases,
      all failing before the service existed.
- [x] **T003** `ModeChangeService::apply()` — those rules, moved out of the controller.

      "Unaltered" needed a correction the first version did not have. The controller evaluated
      readiness *before* checking the production phrase, and the evaluation announces itself so
      the Notification Center can reconcile readiness warnings. The first draft of the service
      checked the phrase first, which would have stopped a submit that still owed the phrase from
      refreshing those warnings. A test now pins the order, and failed against that draft.
- [x] **T004** `OperationsModeController` — reads the request, calls the service, redirects. No
      rule left in it: its constructor takes the guard and the service, and nothing else.
- [x] **T005** `ConfigServiceProvider` declares the service a singleton beside the launch service.
      The controller is still resolved by the container's autowiring, as it was.
- [x] **T006** Proof that nothing changed.

      - The existing tests pass with no line of them edited: Pest unit 1833; the integration suite
        on a freshly provisioned install, 359, including `OperationsModeControllerNoticeTest`,
        `OperationsModeNoOpTest` and `MaintenanceModeTest`.
      - The real `admin_post` handler was driven on that install through nine requests covering
        every rule — an unknown mode, a save, a repeat, maintenance without and with its
        acknowledgement, production with no phrase, a wrong one and the right one, and a change
        back — once with the original controller and once with this one. The nine redirects and
        the four history rows are identical, byte for byte.
      - `operations-security.spec.js` needs a served site and was not run here. It is unedited,
        and CI runs it.
- [x] **T007** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, applied by hand. The
      request sanitising stays in the controller, where the request is; capability and nonce stay
      with `AdminGuard`.

      One thing for Phase 6, found here: `ProductionLaunchRequest` refuses an actor id below 1,
      so a production launch from the command line needs a real user (`--user`), not WP-CLI's
      anonymous 0.

## Phase 2 — Prove the template seam before building on it

- [x] **T008** Integration: a block template registered by a plugin under the slug `coming-soon`
      is found by `locate_block_template()` for that slug, and resolves to the template canvas.
- [x] **T009** Integration: with a theme that has `templates/coming-soon.html`, the theme's content
      is what resolves, and the registered one is not.
- [x] **T010** Integration: with a theme that declares no block-template support, nothing resolves
      — the case the standalone fallback exists for.
- [x] **T011** The outcome: **WordPress behaves as Decision 2 assumes. The plan stands.**

      `tests/Integration/Operations/ComingSoonTemplateSeamTest.php`, against three fixture themes
      in `tests/Fixtures/Themes/`. These tests exercise WordPress and no CoreX code — there is
      none to exercise yet — so they were never going to be seen failing first; what they are for
      is to fail later, if a core release changes the seam.

      | Active theme | Resolves to | Content served |
      |---|---|---|
      | A block theme with no `coming-soon` template | the template canvas | the plugin's registered template |
      | A block theme with `templates/coming-soon.html` | the template canvas | the theme's own |
      | A classic theme | nothing | nothing |

      The same three, plus the Corex theme itself, were then run with each theme active from boot
      in a process of its own, and agreed. That mattered for the third row: the in-suite test has
      to set block-template support by hand after switching theme mid-request, because WordPress
      decides it once during theme setup, and a test that sets the flag it then reads proves
      little alone. In its own process a classic theme reports no support and resolves nothing,
      unaided.

      One thing learned that the plan did not know. WordPress fixes a registered template's id
      when it is registered, from the theme active at that moment — `<active theme>//coming-soon`.
      The first version of the test registered before switching theme and got the wrong theme in
      the id. It changes nothing in the design, since a plugin registers on `init`, after the
      theme is chosen; it does mean `ComingSoonTemplate` must not register earlier than that.

## Phase 3 — The mode, the decision, the guard and the default page (US1, US2, US3.1, US3.5)

One slice. A mode that could be selected and did nothing would be a false statement on the
Operations screen.

- [x] **T012** Pest: `OperationsModeTest` — the assertion that `coming-soon` is invalid is replaced
      by tests of the mode: valid, listed after maintenance, needs confirmation, affects the
      public, described with a tone and a detail, and carrying its warnings. (FR-001, FR-022)
- [x] **T013** `OperationsMode` — `COMING_SOON`, and its entry in `all()`,
      `requiresConfirmation()`, `affectsPublic()`, `describe()` and `warnings()`. The tone is
      warning, not maintenance's danger: the site is closed on purpose and is answering 200.
- [x] **T014** Pest: `ModeDisclosureTest` — the mode asks for an acknowledgement, not a phrase; its
      summary and each consequence name something the code does. (FR-002, FR-003)
- [x] **T015** `ModeDisclosure` — the confirmation, the summary and the consequences: the status
      served, who passes, what is never intercepted, how to leave. "How to leave" names the screen
      only. The command line is added to that sentence in Phase 6, when it exists.
- [x] **T016** `ComingSoonRequest` — the facts about one request, as a value object. Ten of them,
      each defaulting to "no", so the request with nothing said about it is the anonymous visitor
      at some other address.

      Two differ from what this task first listed. The preview value is carried as *whether a
      valid one is present*, not as the value: an invalid value must be indistinguishable from
      none (FR-014), and the surest way is for the decision never to be told about it. And
      `robots.txt` shares its fact with the favicon — see T022.
- [x] **T017** Pest: `ComingSoonDecisionTest` — one case per row of the plan's table, and one per
      pair of rows whose order matters. Twenty-four cases. (FR-004 to FR-007, FR-018, FR-019)

      The order cases could not be seen failing one by one, because every case failed together
      for the missing class. So the order was checked the other way: each pair of rows was
      swapped in the code and the suite run. Rows 2 and 3 swapped: 1 failure. 6 and 7: 2. 9 and
      10: 1. The home rule moved above the bypass: 5. Above the claim: 6.
- [x] **T018** `ComingSoonDecision::for()` — the table, as one `match`, read top to bottom. Reads
      nothing. Five outcomes, not the four this task named: claiming a preview link is its own
      outcome, since it redirects to the same address and not to home. Nothing can produce it
      until T042.
- [x] **T019** `plugins/corex-config/templates/coming-soon.php` — the default page: core blocks,
      spacing presets, translatable strings, no header or footer part. (FR-008, FR-021)

      A `.php` file, not the `.html` this task named: an HTML block template cannot be translated,
      and FR-021 requires that it can. No side padding is written into it: physical left and right
      padding is recorded by the token inventory as left-to-right only, so the inner group is a
      constrained one and takes the theme's own root padding instead.
- [x] **T020** `ComingSoonTemplate` — registers the default under `coming-soon` on `init`; resolves
      the canvas for that slug; and builds a self-contained `StandalonePage` document for a theme
      with no block templates. (FR-008, FR-009)

      `StandalonePage::document()` gained an `$indexable` argument. Every page it rendered before
      was an interstitial and carried `noindex`; this one is a public page. The fallback carries
      the site's name and not CoreX's mark, for the same reason.
- [x] **T021** `ComingSoonSitemap` — one `urlset` holding the home URL, well-formed for a home URL
      with characters XML reserves. (FR-019)
- [x] **T022** `ComingSoonGuard` — on `template_redirect` at priority 0 it gathers the facts and
      acts: redirects with a 302 and no-cache headers, answers the sitemap, or arranges for
      `template_include` to serve the page with a 200. The bypass is `corex_coming_soon_bypass`,
      and admits only a strict `true`, as Maintenance mode's does. (FR-004, FR-005, FR-015, FR-018)

      Four things were found here that the plan did not have. The first is a correction to the
      plan; the other three change what the spec says and are written up in its clarifications,
      awaiting the owner.

      1. **Home is the home path with no WordPress query variable on it.** `/?feed=rss2` has the
         home path, and WordPress renders a feed before it chooses any template — so the plan's
         path-only rule would have served the unfinished site's posts to anybody who asked for the
         feed that way. Found by writing the test for it; the rule was weakened afterwards to
         confirm the tests fail without it (4 failures).
      2. **The favicon passes**, like `robots.txt`. Redirected home, every visit would render the
         page twice.
      3. **No sitemap is published when WordPress is set to discourage search engines.** WordPress
         publishes none then, and the mode should not overrule the one control an operator has.
      4. **`/login` and `/admin` keep working.** They are WordPress's shortcuts to routes the mode
         never intercepts. Its redirect for them is given its turn before the guard redirects
         home, and is left alone where login protection has already switched it off.
- [x] **T023** The response for the page carries no noindex unless WordPress's own visibility
      setting adds one; asserted both ways, for the block template and for the fallback. (FR-010)
- [x] **T024** `ConfigServiceProvider` — three singletons, and registration beside
      `MaintenanceGuard`. The default page is registered in every mode, so it can be designed
      before the site is put into Coming soon.
- [x] **T025** `OperationsSecurityScreen` and `securityCenterState.js` — the mode is offered, its
      block renders with an acknowledgement that names this mode's consequence and not
      maintenance's, and the script's list of modes includes it. (FR-001, FR-003)

      The overview row that read "Maintenance: Off" now reads "Public site", with three answers:
      open to visitors, the maintenance page, the coming-soon page. With two modes that close the
      site, "Maintenance: Off" was true and unhelpful.
- [x] **T026** `theme/patterns/maintenance.php` — its description no longer calls itself a
      coming-soon notice.
- [x] **T027** Every `match` and every list of modes, searched.

      Beyond the four files the plan named, nothing needed the new value. Every other consumer
      goes through `OperationsMode::describe()` or prints the mode as it is — the Overview, the
      Command Center widget, the insight widget. `OptionalDashboardWidgets` compares with
      Development only, so its development-only widgets stay hidden in Coming soon, as they do in
      Maintenance. `CompanyBlueprint`'s `maintenance` is a page slug.

      One thing seen and left. The overview's "Environment and mode differ" notice appears
      whenever the declared mode is not WordPress's environment type — so always, in Coming soon,
      as it already does in Maintenance. It is accurate and mildly noisy; changing it would change
      the Maintenance screen, which this spec does not do.
- [x] **T028** Integration: `ComingSoonModeTest` — 29 cases against real users: anonymous at home is
      served; at eight other kinds of address is redirected; an administrator and an editor pass;
      a subscriber does not; the two extension points; the sitemap both ways; no filter is
      consulted in any other mode. A form posted through the real REST server while the mode is
      on is accepted. (FR-006, FR-007, US2.3) And `ComingSoonTemplateResolutionTest` — the real
      default under the three fixture themes.

      **An error of mine, recorded.** The first draft's helper cast the result of
      `wp_insert_user()` to an integer. When a run died before its clean-up and the user already
      existed, that result was an error, the cast made it 1, and the clean-up then deleted user 1
      — the administrator of the install the suite runs against — with every post they owned. It
      was this worktree's throwaway install and it was rebuilt from the setup script. The helper
      now refuses an error, and the clean-up deletes a user only if its login is one this file
      creates.

      Not covered by an automated test: what is actually sent for a redirect, the sitemap and the
      fallback page, since each ends the request. They were requested over real HTTP against this
      worktree's install, signed out and as each role, under plain and pretty permalinks and
      under each fixture theme, and behaved as the table says. The browser suite makes that
      repeatable in T057.
- [x] **T029** Maintenance mode's unit and integration tests pass with no line of them changed, and
      `MaintenanceGuard` is untouched. (SC-006)
- [x] **T030** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, applied by hand. One
      change came out of it: the `template_include` callback took a `string`, and another plugin's
      filter can hand it anything.

      Found and not fixed, because it predates this spec: some other integration test leaves the
      install's mode option set to `production`. Confirmed by running the previous commit's suite
      from a clean option.

## Phase 4 — The notice and the visitor view (US3.2 to US3.4)

- [ ] **T031** Pest: `ComingSoonNoticeTest` — what the bar says and links to for an administrator,
      for a user who can edit posts and cannot change the mode, and that it is absent for anybody
      who is not served the real site. (FR-016)
- [ ] **T032** `ComingSoonNotice` — the front-end bar at `wp_body_open`, and the toolbar node in
      the admin. The link to Operations & Security only for a user who can change the mode. Not
      dismissible.
- [ ] **T033** `plugins/corex-config/assets/css/coming-soon-bar.css` — logical properties, CoreX
      tokens, enqueued only on a response that shows the bar. (Principle VI, FR-021)
- [ ] **T034** The visitor view: a user who passes and asks for it is served exactly what an
      anonymous visitor at the home URL is served, marked non-cacheable. (US3.3)
- [ ] **T035** Guard Gate, and a WCAG 2.2 AA pass on the bar: contrast in light and dark, focus
      order, a name for each link.

## Phase 5 — The preview link and its banner (US4)

- [ ] **T036** `PreviewAccessStore` — the interface, and an in-memory implementation for tests.
- [ ] **T037** Pest: `PreviewAccessTest` — no link exists until one is created; a created link's
      token is held; a different token is not; regenerating ends the old token and starts a new
      one; revoking leaves none; clearing leaves none; and nothing in the store equals the token
      or can be turned back into it. (FR-011 to FR-014)
- [ ] **T038** `PreviewAccess` — create, regenerate, revoke, clear, and `holds(token)`. The store
      keeps a keyed hash, when it was issued and by whom.
- [ ] **T039** The option-backed store, not autoloaded.
- [ ] **T040** `OperationsModeStore` — a log row may carry an `event`; `record()` writes one with
      no `from` or `to`; `history()` keeps answering mode changes for the callers that want only
      those. Pest for both shapes, and that no row ever holds a token. (FR-012)
- [ ] **T041** `ModeChangeService` — leaving Coming soon for any other mode clears the link. Pest
      from the service, so it is true of the screen and the command alike. (FR-012a)
- [ ] **T042** `ComingSoonGuard` — claiming: a valid value sets the cookie (`HttpOnly`,
      `SameSite=Lax`, `Secure` over HTTPS, 14 days) and redirects to the same address without it;
      an invalid one changes nothing and is not acknowledged. (FR-011, FR-014)
- [ ] **T043** `PreviewLinkController` — `admin_post` handlers for create, regenerate and revoke,
      behind `AdminGuard`, each writing its history row. The new link is shown once, on the
      response to the action that made it. (FR-011, FR-012, Principle VII)
- [ ] **T044** `OperationsSecurityScreen` — the preview-link card, present only in Coming soon: no
      link and a Create button; or when it was created and by whom, with Regenerate and Revoke.
      History rows for link events render with the operator's name.
- [ ] **T045** The preview banner — the same bar, with the private-preview message and no link, for
      a browser that holds preview access. Never shown to an anonymous visitor. (FR-016a)
- [ ] **T046** Integration: `PreviewAccessIntegrationTest` — the option store against a real
      database; leaving the mode through the service empties it; a preview holder requesting
      anything under the admin gets what an anonymous visitor gets. (US4.4, US4.7)
- [ ] **T047** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`.

## Phase 6 — The command (US5)

- [ ] **T048** Pest: `ModeCommandTest` — `get` reports the mode and whether it was declared or
      inherited; `set` with the confirmation its mode needs has the same result and history row
      as the screen; without it, nothing changes and the exit is non-zero; an unknown mode fails.
      (FR-017)
- [ ] **T049** `ModeCommand` and `ModeCommandResult` — a pure `execute()` over
      `ModeChangeService`, and a `run()` that prints.
- [ ] **T050** Registered in `CliServiceProvider`'s lazy map; an integration test that the command
      is present under `composer install --no-dev`, beside the ones #202 restored.
- [ ] **T051** `packages/cli/README.md` — the command and its options.

## Phase 7 — The generated template and the asset action (US6)

- [ ] **T052** Pest: `SiteScaffolderTest` — a generated client theme has
      `templates/coming-soon.html`, and `--plugin-only` does not. (FR-020)
- [ ] **T053** `SiteScaffolder` — the template, as the client theme's own file.
- [ ] **T054** `corex_coming_soon_enqueue_assets`, fired on `wp_enqueue_scripts` only when the page
      is the response, and the `corex-coming-soon` body class. Integration test for both, and
      that neither is present on any other response.
- [ ] **T055** `client-site-layout` in CI — after generating the site, switch the generated theme
      on, set the mode from the command line, and require the home URL to serve the client
      theme's template. (SC-005)

## Phase 8 — Documentation, release notes and the browser project

- [ ] **T056** `tests/e2e/playwright.config.js` — a second project that depends on the first and
      matches only the coming-soon spec; the first ignores it.
- [ ] **T057** `tests/e2e/coming-soon.spec.js` — signed out: home is 200 with the page; a published
      page, a post and an address that does not exist each answer 302 to home with none of their
      content; `robots.txt` is served; the sitemap lists one URL; a feed redirects. Restores the
      previous mode whatever happens. (SC-002)
- [ ] **T058** Same file — an administrator and an editor each reach every address and see the bar,
      with the Operations link for the administrator only; a subscriber is redirected. (SC-003)
- [ ] **T059** Same file — the preview link: the real site, the banner, the secret gone from the
      address bar; then regenerated, revoked, and the mode left, each ending access at the next
      request. (SC-004)
- [ ] **T060** Same file — the page, the bar and the banner in LTR and RTL, light and dark, at
      mobile and desktop widths. (SC-007, FR-021)
- [ ] **T061** `docs/en/03-operations/coming-soon.md` — the mode and who passes, the preview link,
      the command, the template name and how a theme overrides it, the asset action, the note on
      purging a page cache, and the REST limitation. (FR-023)
- [ ] **T062** `docs/ar/03-operations/coming-soon.md` — translated, not a placeholder. (FR-023)
- [ ] **T063** `docs/en/03-operations/operations-and-security.md` — the mode in the table of modes,
      linking the new page.
- [ ] **T064** `docs-app`: `guides/coming-soon.md` and its sidebar entry; `guides/client-site.md`
      names the generated template.
- [ ] **T065** `CHANGELOG.md` — the entries, and under **Client impact**: a new mode that changes
      nothing until selected; `make:site` generates one more file; `OperationsMode::all()` returns
      five values, for any client code that enumerates them.
- [ ] **T066** Build the docs site and run the link check as a pass, not a skip. Regenerate the
      token inventory.
- [ ] **T067** `PROGRESS.md`, `DECISIONS.md`, `PROJECT-STATUS.md` and its generated copy.
- [ ] **T068** Guard Gate — `docs-guard` on every page; the full Pest, Jest, lint and browser runs.
