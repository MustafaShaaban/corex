# Contract: `scripts/release-push.mjs` (`npm run release:push`)

*Amended 2026-10-25 (DECISIONS #512, #513): it was an upload; it is now build, upload, install and check.*

Installs one release on a CoreX site from the owner's machine, with no WordPress around it and no browser,
through the Releases routes signed in with an Application Password (FR-013 to FR-015d). User guide:
`docs/en/05-deployment/releases-from-your-machine.md`.

## Invocation

```text
npm run release:push -- --client=<name> [--with-plugin=<folder>]... [--pdf-fonts=all] [options]
npm run release:push -- --package=<corex-release-….zip> [--client=<name>] [options]
npm run release:push -- --resume [--client=<name>] [--settings=<file>]
```

| Option | Meaning |
|---|---|
| `--client` | Build this client's package with `build:dist --zip`, unless `--package` is given; the package must be for it; the name given where the site cannot say whose it is. |
| `--with-plugin`, `--pdf-fonts` | Passed to `build:dist`. |
| `--package` | Send this file instead of building. Its name must match `ReleaseUpload::A_PACKAGE_NAME`. |
| `--settings` | The settings file. |
| `--accept-older`, `--accept-downgrades` | What the screen asks a person to accept (FR-015a). |
| `--resume` | Go on with an installation that stands on the site (FR-015c). |
| `--upload-only` | Stop once the package is on the site (spec 123 User Story 5 installs it through agent abilities). |

Any other argument is refused, including anything that looks like a password.

## Settings

Read in this order (FR-015):
1. `--settings=<file>`;
2. the file `COREX_PUSH_SETTINGS` names;
3. `COREX_PUSH_SITE`, `COREX_PUSH_USER` and `COREX_PUSH_APP_PASSWORD`, all three;
4. `~/.corex/release-push/<client>.json` when `--client` is given.

A file holds `{ "site": …, "user": …, "application_password": … }`. A file inside a git checkout (a folder above
it holds `.git`) is refused. `site` must be `https://`; `http://` only for `localhost`, `127.0.0.1`, `[::1]`,
`*.localhost`, `*.test` and `*.local`. The password goes only into the `Authorization: Basic` header and is
never printed.

## State

`~/.corex/release-push/state/<host>.json` (or `COREX_PUSH_STATE_DIR`), written with mode `0600` at prepare:
`{site, package, version, key, recovery_link}`. Removed when the installation finishes, or when the site
refuses a step before the swap. Kept on any other failure (FR-015b).

## Sequence

Every route is `<site>/?rest_route=/corex/v1/releases…` (which works with or without pretty permalinks),
asked through `createReleaseClient()` from `plugins/corex-config/src/Releases/releaseClient.js` with no nonce
and the Basic header, so the installer's mark, the waits past a host's own pages and the installation's key
are the screen's.

1. `GET releases`. Refusal: stop (exit 2). An installation standing at a step other than `awaiting`: without
   `--resume`, say where it stands and stop (exit 2); with it, go to step 9. `blockers`: stop (exit 2).
2. Build with `node scripts/build-shared-host-dist.mjs --client=… [--with-plugin=…] [--pdf-fonts=…] --zip` and
   take the path from its `package: <path>` line (exit 4 on failure), or take `--package`.
3. Upload with `client.upload()` in parts of the overview's `part_bytes`, hashed as a stream; resumes from
   what the site holds and from a `409 out_of_step`.
4. With `--upload-only`: print the name and SHA-256, exit 0.
5. `POST inspect`. Stop before anything starts (exit 1, or 2 for `blockers`) when the package is for another
   client than `--client`, when the site cannot say whose it is and no `--client` was given, when it is older
   and `--accept-older` was not given, or when it holds downgrades and `--accept-downgrades` was not given.
6. `POST install/prepare`; write the state file.
7. `POST install/confirm` with `phrase` = the statement's version, `client` when given, `older`, `downgrade`.
8. `runInstallation()` from `installRun.js`: the steps until `finish`, then `finish` until `done`.
9. *(resume)* A `stuck` installation: print the way back, exit 2. Otherwise a client with the state file's key
   when it names the same package, or, without one, enough tries (50) to outlast WordPress's ten minutes of
   maintenance; then `runInstallation()` from the standing step.
10. Remove the state file. `GET releases` and `GET <site>/`: exit 0 only when the site runs the version
    installed and its home page answers below 500; otherwise exit 2 with the way back.

## Output on failure (FR-015d)

- Before confirm: the site's message and reason, then `Nothing on the site changed.`
- A step refused before the swap: `No folder of the site was moved: it still runs the release it ran.`
- A failure with the files possibly in place (`filesInPlace` from `runInstallation()`): `--resume`, the
  Releases screen's "Go back", the recovery link, and where the key and link are kept.
- WordPress's own refusal (wrong password, no right to the route): its status, code and message.

## Exit codes

| Code | When |
|---|---|
| 0 | installed and checked, or `--upload-only` done, or `--resume` with nothing standing |
| 1 | usage or settings, or something not said to be meant |
| 2 | the site refused, an installation stands, or the check after failed |
| 3 | the site could not be reached |
| 4 | the build failed |
| 5 | the command itself failed (any error it does not explain is thrown on, never reported as the site's answer) |

## Tested by

`tests/release-push.test.js` (Jest), with the real client and loop against a fake site:
- a whole installation, in the screen's order, with the version as the phrase;
- the password only in the `Authorization` header, never printed, no nonce, never in an address;
- an upload resumed from what the site holds;
- an older package, a downgrade, another client: stopped before prepare;
- a standing installation: stopped without `--resume`, finished with it, the kept key carried;
- a failure after the swap: the recovery link and `--resume`, the state kept;
- a step refused before the swap: nothing kept;
- `push_off` passed on in the site's words;
- a wrong Application Password named as WordPress's refusal;
- settings from the variables and from the client's file, a file in a git checkout refused, the `http://`
  rule, and no unknown option.

Run by hand against a WordPress built from a package on 2026-10-25: a whole installation over HTTP,
`signed_in_with` in the release record, and `push_off` with the constant removed.
