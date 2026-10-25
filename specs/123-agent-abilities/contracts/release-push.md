# Contract: `scripts/release-push.mjs`

Sends one release package from the owner's machine to a CoreX site through spec 107's resumable upload (R11,
DECISIONS #503). It needs no WordPress install around it.

## Invocation

```text
node scripts/release-push.mjs <package.zip> [--settings <file>]
```

**The package.** The file name must match spec 107's rule (`corex-release-<name>.zip`; the regex is
`ReleaseUpload::A_PACKAGE_NAME`). Any other name is refused before anything is sent.

**The settings file.** It is `--settings <file>`, or else the file the `COREX_PUSH_SETTINGS` environment variable
names. With neither, the script stops and says how to give one. The file is never looked for inside the
repository.

## Settings file

```json
{
  "site": "https://mu-va.com",
  "user": "corex-agent",
  "application_password": "xxxx xxxx xxxx xxxx xxxx xxxx"
}
```

- `site` must be `https://`. Plain `http://` is accepted only for `localhost`, `*.localhost`, `*.test` and
  `*.local`.
- The password goes only into the `Authorization: Basic` header. It is never printed or logged, and never taken
  from the command line.
- For Muva, the file lives at `C:\Users\pc\.muva\agent-push.json`.

## Sequence

The steps below describe the HTTP requests. Every route is under `<site>/wp-json/corex/v1`, and every request
carries the Basic header.

1. Hash the file as a stream (SHA-256, lowercase hex).
2. `GET /releases/uploads/<sha256>`, which answers `{ok:true, data:{received, part_bytes}}`.
3. If `received` equals the file size, skip to step 5. Print "already on the site".
4. Repeat until every byte is sent: `POST /releases/uploads/<sha256>?offset=<received>`, with body
   `application/octet-stream` (`part_bytes` bytes from `received`). Each answer gives the new `received`.
   - A 409 `out_of_step` answer carries the site's `received`. Continue from it.
   - A network error or a 5xx is retried three times, waiting 2, 4 and 8 seconds. Each retry first asks step 2
     again.
5. `POST /releases/uploads/<sha256>/complete`, with body JSON `{size, name}`, which answers
   `{ok:true, data:{package}}`.
6. Print the package name and its SHA-256, and tell the agent the next step (`corex/release-inspect` with that
   name).

## Exit codes and output

| Code | When | Output |
|---|---|---|
| 0 | the package is on the site | package name, SHA-256, bytes sent |
| 1 | usage or settings error (missing file, bad name, `http://` to a public host) | the problem, in one line |
| 2 | the site refused | its `reason` and `message`, for example `not_signed`, `agent_changes_off`, `too_large` or `damaged` |
| 3 | the site could not be reached after the retries | the last error |

## Tested by

`tests/release-push.test.js` (Jest), with a fake `fetch`, covers:
- a fresh upload;
- resuming after a cut;
- an out-of-step answer;
- the "already whole" case;
- a refused name;
- an `http://` public host;
- that the password never appears in any output.
