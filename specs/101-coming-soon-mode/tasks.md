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

- [x] **T031** Pest: `ComingSoonNoticeTest` — what the bar says and links to for an administrator,
      for a user who can edit posts and cannot change the mode, and that it is absent for anybody
      who is not served the real site. (FR-016)

      Who gets a bar is not the notice's decision. `ComingSoonDecision` gained a `bar`, set on the
      one row that is "the real site, served to somebody who can edit posts", so "who is told"
      cannot drift from "who passes". Tested there first, and seen failing.
- [x] **T032** `ComingSoonNotice` — the front-end bar at `wp_body_open`, and the toolbar node in
      the admin. The link to Operations & Security only for a user who can change the mode. Not
      dismissible.

      It also listens on `wp_footer`, and prints once: a theme that never fires `wp_body_open`
      still gets the bar, at the foot of the page. It asks the guard for the decision once per
      request, so the stylesheet and the bar cannot disagree.

      The unit tests for what the bar says passed on their first run, because the class was
      written straight after them and nothing was left to correct. The integration tests for where
      it appears were written before it was registered or styled, and two of them failed for that.
- [x] **T033** `plugins/corex-config/assets/css/coming-soon-bar.css` — logical properties, CoreX
      tokens, enqueued only on a response that shows the bar. (Principle VI, FR-021)

      The tokens are the admin ones. They are defined on `.corex-admin` as well as on the admin
      body, so the bar's element carries that class and the stylesheet names `corex-admin-tokens`
      as its dependency: both arrive with the bar and on no other front-end response. The bar
      brings CoreX's palette rather than the theme's so that its contrast is the same on every
      client's site. The token adapter's header said it had two consumers; it now says three.
- [x] **T034** The visitor view: a user who passes and asks for it is served exactly what an
      anonymous visitor at the home URL is served, marked non-cacheable. (US3.3)

      Asked for with `?corex_visitor_view=1`. No nonce: it asks for less than the user already
      has. It serves the page at any address, and is ignored from somebody who is a visitor
      already.

      "Exactly" took three steps, each found by comparing the two responses over HTTP. WordPress
      sets its toolbar up on the same hook at the same priority and was registered first, so by
      the time the guard runs the toolbar's stylesheet is queued — and that stylesheet is what
      pushes the page down by the toolbar's height. So the toolbar is switched off *and* its
      assets are taken back out of the queue; and `logged-in` is taken off the body, since a
      theme may style by it. After that the visitor view and a signed-out visitor's page are the
      same bytes.
- [x] **T035** Guard Gate, and a WCAG 2.2 AA pass on the bar.

      | | Light | Dark | Needs |
      |---|---|---|---|
      | Message on the bar | 17.01 | 16.28 | 4.5 |
      | Link | 6.54 | 10.33 | 4.5 |
      | Link, hovered | 9.71 | 12.40 | 4.5 |
      | Focus ring against the bar | 4.96 | 11.29 | 3 |

      Computed from the token values. Links are underlined, so they are not told apart by colour
      alone; each is 29px tall, past the 24px target size; each name says where it goes. The bar
      is an `aside` with a label. Checked in a browser in light, dark, right-to-left and at 375px:
      it wraps without overflowing, and in right-to-left it mirrors.

      Focus order, for the record: the bar's links come before the theme's skip link, because
      WordPress inserts the skip link in front of the site's blocks and the bar is printed before
      them. Two links ahead of the skip link, and the toolbar's are ahead of both.

      From the guard gate: the notice asked for the decision twice per request, firing the
      client's filters each time; it now asks once.

## Phase 5 — The preview link and its banner (US4)

- [x] **T036** `PreviewAccessStore` — the interface, and an in-memory implementation for tests
      (`tests/Fixtures/Operations/InMemoryPreviewAccessStore.php`).
- [x] **T037** Pest: `PreviewAccessTest` — 27 cases: no link until one is created; a created link's
      token is accepted; a different token is not; regenerating ends the old token and starts a
      new one; revoking leaves none; clearing leaves none; and nothing in the store equals the
      token or can be turned back into it. (FR-011 to FR-014)

      Each security rule was then weakened in the code and the suite run, to see the tests fail:
      a grant that never runs out, an expiry outside the signature, a signature over nothing of
      the link, a hash without the key, a create that replaces, a regenerate that creates. Each
      was caught.
- [x] **T038** `PreviewAccess` — create, regenerate, revoke, clear, and what a browser must hold.
      The store keeps a keyed hash, when it was issued and by whom.

      **The cookie does not hold the token**, which the plan said it would. It holds a grant: an
      expiry, and a signature over the stored hash and that expiry. A cookie that leaks gives
      away one browser's access and not the link; the fourteen days are enforced by the server
      and not by a cookie lifetime its holder can edit; and regenerating or revoking still ends
      every grant at the next request, because the signature is over the hash they replace. The
      plan's Decision 4 is amended to say so.

      Creating over a link that exists does nothing, and regenerating where there is none does
      nothing. Two tabs open on the screen must not let a stale "Create" silently end the link
      the other tab just handed to the client.
- [x] **T039** `OptionPreviewAccessStore` — one option, not autoloaded. Anything under its name
      that is not a record this class wrote is read as no link.
- [x] **T040** `OperationsModeStore` — a log row may carry an `event`; `record()` writes one with
      no `from` or `to`; `timeline()` answers both kinds; `history()` keeps answering mode changes
      for the callers that want only those. (FR-012)

      `record()` accepts three named events and nothing else. The log is a closed vocabulary the
      screen renders, not a place a caller can write free text — which is also what keeps a link
      out of it.
- [x] **T041** `ModeChangeService` — leaving Coming soon for any other mode clears the link.
      (FR-012a)

      It asks the store whether the site has left the mode, before and after the change, rather
      than inferring it from the result. A launch reaches the store through
      `ProductionLaunchService` and the other modes reach it directly; the first draft of the
      test for the launch route is what showed the two had to be covered by one check. A change
      that was refused, or that changed nothing, keeps the link. No "revoked" row is written:
      nobody revoked it, and the mode change beside it is the record.
- [x] **T042** `ComingSoonGuard` — claiming: a valid value sets the cookie (`HttpOnly`,
      `SameSite=Lax`, `Secure` over HTTPS, 14 days) and redirects to the same address without it;
      an invalid one changes nothing and is not acknowledged. (FR-011, FR-014)

      An invalid value never becomes a fact of the request at all, so nothing downstream can
      answer it differently from no value. Over HTTP, a wrong link and no link return the same
      status and the same headers, the date aside.
- [x] **T043** `PreviewLinkController` — one `admin_post` action behind `AdminGuard`, carrying
      which of the three operations is wanted. (FR-011, FR-012, Principle VII)

      The rules are not in it. `PreviewLinkService` holds them — only while the site is in Coming
      soon, each operation recorded with the operator — and returns a `PreviewLinkResult`
      (Principle III). Two classes the plan's file list did not have.

      A new link is shown by answering the POST itself, with a page, and not by redirecting. It
      has to be: the link is stored nowhere it could be read back from, so a redirect would mean
      putting the secret in an address or parking it in the database for the next request.
      Everything that makes no link redirects back to the screen with a status, as its other
      forms do.
- [x] **T044** `OperationsSecurityScreen` — the preview-link card, present only in Coming soon: no
      link and a Create button; or when it was created and by whom, with Regenerate and Revoke.
      History rows for link events render with the operator's name, and the history's heading
      now says it holds both. The link is never on the screen.
- [x] **T045** The preview banner — the same bar, with the private-preview message and no link,
      for a browser that holds preview access. Never shown to an anonymous visitor. (FR-016a)

      Somebody who can edit posts and also holds preview access gets the notice, not the banner:
      an operator who opened the client's link to check it is still an operator. The mode's
      description on the screen gained a line about the link and what removes it.
- [x] **T046** Integration: `PreviewAccessIntegrationTest` (18) and `PreviewLinkScreenTest` (14) —
      the option store against a real database, and that the row holds no link; leaving the mode
      through the real service empties it; the guard claims a valid link and treats a wrong, old
      or revoked one as none; a grant has no effect in another mode; a preview holder under the
      admin has what an anonymous visitor has. (US4.4, US4.7)

      Not covered by an automated test, for the same reason as before — each ends the request:
      the cookie actually set, the redirect that follows, and the controller's two responses.
      Each was driven over real HTTP against this worktree's install: opening a link, browsing
      with the grant, a wrong link, regenerating, revoking, leaving the mode and coming back, and
      the admin side with and without a nonce and a session. T059 makes that repeatable.
- [x] **T047** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, applied by hand.

      Worth saying to whoever writes the guide in Phase 8: a preview link's token is in the
      address when it is opened, so it is in the web server's access log for that one request.
      That is true of every link of this kind. It is why the link can be regenerated, and why
      the cookie does not carry it.

## Phase 6 — The command (US5)

- [x] **T048** Pest: `ModeCommandTest` — 17 cases. `get` reports the mode and whether it was
      declared or inherited; `set` with the confirmation its mode needs has the same result and
      history row as the screen; without it, nothing changes and the exit is non-zero; an unknown
      mode fails. (FR-017)

      "The same history row as the screen" is tested by making the change both ways and comparing
      the rows, the time aside.
- [x] **T049** `ModeCommand` and `ModeCommandResult` — a pure `execute()` over
      `ModeChangeService`, and a `run()` that prints.

      Three things the task did not name. Setting the mode the site is already in **succeeds**:
      a deploy script that sets it on every run must not fail on its second. Going live as
      nobody is refused with a sentence rather than the launch service's exception — the thing
      Phase 1 found — and says to pass `--user`. And `get --porcelain` prints the mode alone,
      for a script to read.

      One test elsewhere had to change. `SiteScopedStateTest` passed only while
      `wp_get_environment_type` had never been stubbed earlier in the run: the code it exercises
      asks `function_exists()` first, and Brain Monkey defines a function for the whole process
      the first time any test stubs it. `ModeCommandTest` sorts before it and stubs it. The test
      now stubs the function itself, so it no longer depends on what ran before.
- [x] **T050** Registered in `CliServiceProvider`'s lazy map as `corex mode get` and
      `corex mode set`, resolved only when run; `CommandRegistrationTest` holds both to the
      unresolvable-container test beside the ones #202 restored.

      Both carry a synopsis, which their neighbours mostly do not. With one, WP-CLI checks the
      arguments first, so a mistyped `--acknowlege` is an error and not an acknowledgement that
      silently was not given.

      Run in a real WP-CLI against this worktree's install, through every case, reading the exit
      code each time: 0 for a change made and for no change needed; 1 for a missing
      acknowledgement, a missing phrase, a launch with no user, an unknown mode, no mode, and a
      mistyped flag.
- [x] **T051** `packages/cli/README.md` — the command, what each mode needs, the exit codes, and
      what `--user` is for. The mode's description on the screen now names the command as the
      way back for an operator who cannot reach the screen, as T015 said it would once it
      existed.

## Phase 7 — The generated template and the asset action (US6)

- [x] **T052** Pest: `SiteScaffolderTest` — a generated client theme has
      `templates/coming-soon.html`: core blocks only, no header or footer part, every block
      closed, nothing left for the renderer to fill in. `--theme-only` and `--starter` have it;
      `--plugin-only` does not. (FR-020)
- [x] **T053** `SiteScaffolder` — the template, as the client theme's own file, from
      `packages/cli/stubs/site/theme-coming-soon.stub`. It opens with a comment saying what the
      page is, why it has no header or footer, and where its own assets go.

      **The readiness validator had to change.** `SiteScaffoldValidator` called any `{{` or `}}`
      in a generated file an unresolved placeholder, and a block with an object attribute ends
      its comment in `}}` — so no template with a real layout could pass, and every template the
      generator wrote until now had been kept to flat attributes. It now uses the renderer's own
      definition: two braces, a name, two braces. Two existing tests failed on the new template,
      which is how it was found; two new ones pin the rule both ways.
- [x] **T054** `corex_coming_soon_enqueue_assets`, fired on `wp_enqueue_scripts` only when the page
      is the response, and the `corex-coming-soon` body class. Integration tests for both, on the
      visitor view as well, and that neither is present on the real site or in another mode.

      The body-class callback is the one that already took `logged-in` off the served page; it
      now does both, under a name that says so.
- [x] **T055** `client-site-layout` in CI — after generating and building the site, the generated
      theme is switched on, the mode is set from the command line (and first refused without
      `--acknowledge`), and the home URL has to answer 200 with the client theme's page; another
      address has to answer 302 with no body. (SC-005)

      The page is edited in the client's repository before the request, so the assertion is that
      *that file* is what a visitor is sent. CoreX's default says the same words until somebody
      changes them, and a check that could not tell the two apart would prove nothing.

      Rehearsed by hand first, on this worktree's install, with a site generated into a scratch
      directory by the real command: the edited page was served with the body class and the
      theme's own stylesheet, a post redirected, and under the Corex theme the same address
      served CoreX's default instead. The one part not rehearsed is `wp server`, which the CI
      step uses to answer the request; the run on this commit is its first test.

      Found on the way, and not this spec's: every WP-CLI command logs a "translation loading
      triggered too early" notice, from the reset command's description being translated while
      commands are registered. Phase 6's mode command was written the same way. Raised as its own
      task.

## Phase 8 — Documentation, release notes and the browser project

- [x] **T056** `tests/e2e/playwright.config.js` — a second project, `coming-soon`, that depends on
      the first and matches only the coming-soon spec; the first ignores it.
- [x] **T057** `tests/e2e/coming-soon.spec.js` — signed out: home is 200 with the page, and still is
      with a campaign tag; a published post, a published page, an address that does not exist, a
      feed and a search each answer 302 to home with an empty body and a no-cache header;
      `robots.txt` is served; the sitemap lists one URL. Restores the previous mode whatever
      happens. (SC-002)
- [x] **T058** Same file — an administrator reaches every address and sees the bar with the
      Operations link; an editor sees it without; a subscriber ends at the coming-soon page. The
      visitor view has no toolbar, no bar and no signed-in mark. (SC-003)
- [x] **T059** Same file — the preview link: shown once and never on the screen again; opened, the
      real site, the banner, the secret gone from the address bar; the cookie `HttpOnly`,
      `SameSite=Lax`, fourteen days, and not the link; no way into the admin; then regenerated,
      revoked, and the mode left and returned to, each ending access at the next request; and each
      action in the history without the link. (SC-004)
- [x] **T060** Same file — the page, the notice and the banner in LTR and RTL, light and dark, at
      375px and 1280px: nothing scrolls sideways, the bar stays inside the viewport, its text and
      links are measured at 4.5 to 1 or better against its background, its links are 24px or
      taller, and its accent is on the start edge in either direction. (SC-007, FR-021)

      **The spec found a defect on its first run that no other suite could.** After the mode form
      came back for a missing acknowledgement it showed Coming soon and its checkbox, while the
      `<select>` underneath still held the mode the site was in; a script moved it on load.
      Ticked and submitted before that script ran — or with no script, the path the screen
      documents as working — it applied the wrong mode. In the test it tried to launch the site.
      The proposed mode is now selected in the markup itself, with an integration test, and the
      defect predates this spec: it is the no-script path of the mode form as spec 077 left it.

      Run here against this worktree's install on PHP's built-in server: 8 of 8. The existing
      `operations-security.spec.js` was re-run for the form change: 15 of 15, once the config
      plugin's scripts were built, which this worktree had never done. The rest of the browser
      suite needs the block editor and the seeded fixtures and is CI's to run.

      Two fixture users are seeded in CI for it — an editor and a subscriber of its own, since
      login protection counts attempts per address and a shared user is how other specs came to
      fail for each other.
- [x] **T061** `docs/en/03-operations/coming-soon.md` — what each request gets and in what order;
      turning it on and off, from the screen and the command line; the bar and the visitor view;
      the preview link, and what is and is not stored; the template and how a theme replaces it;
      the asset action and body class; the two filters; search engines; and the two limits — it
      is not a confidentiality control, and a page cache has to be purged. (FR-023)
- [x] **T062** `docs/ar/03-operations/coming-soon.md` — translated in full. Commands, file names,
      hooks and paths stay in English, as the translation memory requires. (FR-023)

      The Arabic `operations-and-security.md` beside it is still the translation placeholder it
      was before this spec. This page links to it; translating it is not this spec's.
- [x] **T063** `docs/en/03-operations/operations-and-security.md` — the mode in the table of modes,
      a short section on what it does, and a link to the new page.
- [x] **T064** `docs-app`: `guides/coming-soon.md` and its sidebar entry; `guides/client-site.md`
      names the generated template. `docs/en/04-team-workflow/client-site-workflow.md` names it
      too, where it lists a client theme's override points.
- [x] **T065** `CHANGELOG.md` — the feature, two fixes, one change to the screen's wording, and
      under **Client impact**: the mode changes nothing until selected; `OperationsMode::all()`
      returns five values; rows in the mode log can be events; `make:site` writes one more file
      and leaves existing themes alone; the names CoreX now uses on the front end; and two
      constructors that changed.
- [x] **T066** The docs site built with the class reference generated, as the Docs workflow does:
      996 pages. `tests/docs-links.test.js` then ran against that build and passed — as a pass,
      not the skip it reports when nothing is built. Token inventory regenerated.
- [x] **T067** `PROGRESS.md` (the in-flight paragraph replaced, not appended to), `DECISIONS.md`
      #236 (renumbered three times, as `main` took #233, #234 and #235 on the same day), `PROJECT-STATUS.md` and its
      generated copy.
- [x] **T068** Guard Gate — `docs-guard` on each page: every command, flag, hook, filter, option,
      class name and path in the guides was checked against the code or the command's own help,
      and the ordered list of rules was corrected to the decision's actual ten after its first
      draft left two out. Pest unit 2030, integration 463; Jest 530 and 476, none skipped;
      `lint:js` and `lint:css` clean; the coming-soon browser project 8 of 8 locally.

      Not run here: the main browser project in full, and the multisite integration suite. Both
      run in CI.
