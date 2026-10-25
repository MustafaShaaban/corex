# Quickstart: prove an agent can install a release

This is a validation run for the finished work, phase by phase. It runs against the local CoreX install
(`C:\wamp64\www\corex\wp`, environment type `local`, so Application Passwords work over `http`), never against a
client's production site. The contracts are in [contracts/](contracts/), and the shapes are in
[data-model.md](data-model.md).

## Prerequisites

- The local install boots with CoreX active, as the Environment Gate requires.
- The MCP Adapter plugin (v0.7.1 or later) is installed and active on the local install.
- A release package named `corex-release-*.zip`, built for the local install's client.
- Claude Code (or another MCP client) connected to the local install's default server, with a test user's
  Application Password.

## Phase 1: visibility (US1)

1. Ask the client to discover abilities. Expected: `corex/site-info` and `corex/list-blocks` are listed beside
   the `core/*` ones.
2. Run `corex/site-info` as an author or above. Expected: `{name, framework: "Corex", version, corex_blocks}`.
   As a subscriber: "Permission denied" (the version stays hidden, spec 111).
3. Deactivate the MCP Adapter and load the admin. Expected: no warning or notice. Reactivate it.

Repeat steps 1 and 2 against mu-va.com after the patch release. This is the live check SC-002 asks for.

## Phase 2: the agent's account and the switch (US2)

1. In Users, create `corex-agent` with the role "CoreX agent". In Access & Abilities, allow the role
   `corex_manage_releases` and `corex_manage_operations`.
2. Make an Application Password for `corex-agent`, and connect the MCP client with it.
3. With the switch off, run `corex/release-inspect`. Expected: `{ok:false, reason:"agent_changes_off"}`, and one
   `agent.call` event with outcome `warning`.
4. Remove `corex_manage_releases` from the role and run `corex/release-overview`. Expected: a permission refusal,
   one entry in the access-denied log (kind `action`, section `agent:corex/release-overview`), and one `denied`
   event, however many times the adapter checked. Allow it again.
5. Turn the switch on in Operations & Security → Agent access. Expected: the panel says on, and an
   `agent.switch.changed` event is written.
6. Revoke the Application Password and call again. Expected: the client is refused by WordPress. Make a new one.

## Phase 3: read (US3)

- `corex/release-overview` returns what the Releases screen shows, and running it removes nothing (compare
  `wp-content/corex-releases/` before and after).
- `corex/activity-recent` with `count: 5` returns at most five events, and leaves out personal-sensitivity ones.
- `corex/submissions-recent` is refused until the role is allowed `corex_manage_submissions`. After that it
  writes an event with sensitivity `personal`.

## Phase 4: push (US4)

1. Write the settings file outside the repository, for example `%USERPROFILE%\.corex\local-push.json`, with
   `site`, `user` and `application_password`.
2. Run:

   ```bash
   node scripts/release-push.mjs "C:/path/to/corex-release-acme-1.0.0.zip" --settings "C:/Users/<you>/.corex/local-push.json"
   ```

   Expected: exit 0, the package name and its SHA-256 printed, and the package listed by `corex/release-overview`.
3. Start the same run, stop it with Ctrl+C half-way, and run it again. Expected: the second run sends only the
   rest.
4. Run it a third time. Expected: "already on the site", and nothing sent.
5. Turn the switch off and run it. Expected: exit 2 with `agent_changes_off`. Turn it back on.
6. `curl` the overview route with the Basic header and no nonce. Expected: `403 not_signed`, because only the
   three upload routes accept an Application Password.

## Phase 5: release (US5)

Through the MCP client, as `corex-agent`:
1. `corex/release-inspect` with the package name. Check that the `sha256` matches what the script printed.
2. `corex/release-prepare`. Expected: nothing on the site changes, and `confirm_needs` lists the phrase.
3. `corex/release-confirm` with a wrong phrase. Expected: `not_confirmed`. Then with the right one: progress.
4. `corex/release-step` with the key, repeated until progress says `finish`. Then `corex/release-finish` with the
   key, repeated until `done:true`.
   - Expected: no call waits for maintenance to time out.
   - Expected: the install event's `key_from` is `body`.
   - Expected: the visitor-facing site was held off only between the swap and the finish.
5. `corex/release-return-prepare`, then `corex/release-return` with the phrase it gave. Expected: the release
   before is back, and the screen's history shows both, by `corex-agent`.
6. Start a prepare from the screen as another administrator, then `corex/release-prepare` from the agent.
   Expected: `installation_in_progress`.
7. `corex/release-leftovers-prepare`, change nothing, then `corex/release-leftovers-remove` with the fingerprint.
   Expected: removed. With a stale fingerprint: `leftovers_changed`.

## Phase 6: settings (US6)

- With LiteSpeed Cache inactive, `corex/page-cache-clear` answers `nothing_to_clear`. With it active, it answers
  `cleared` and LiteSpeed's purge runs.
- `corex/operations-mode-set` with `mode: "coming-soon"` and no `confirmed` returns status `confirm`. With
  `confirmed: true` the mode changes, and `operations.mode.changed` is recorded.
- `corex/feature-flags` lists every flag, and says where `agent_changes` came from.

## Automated suites to run

```bash
composer test
```

```bash
composer test:integration
```

```bash
npm run test:js -- tests/release-push.test.js
```

```bash
npm run test:e2e -- tests/e2e/operations-security.spec.js
```

The integration suite runs only on the local CoreX install or in CI, never inside a client checkout whose `wp/`
is a live site. On Windows with PHP 8.3 ZTS, `composer test` can segfault at shutdown even on an unmodified tree
(PROGRESS, "Baseline"). CI is the authority for the integration and browser suites.
