# Security Policy

## Supported versions

Corex is pre-1.0 and ships from a single active line. Security fixes land on the latest released
version (and `develop`). Always run the most recent release.

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report privately through one of:

- GitHub's **"Report a vulnerability"** (Security → Advisories) on the repository, or
- email **mustafashaaban22@gmail.com** with the subject line `COREX SECURITY`.

Include:

- the affected component (plugin/add-on/theme) and version,
- a description of the issue and its impact,
- reproduction steps or a proof of concept,
- any suggested remediation.

## What to expect

- **Acknowledgement** within 3 business days.
- An initial **assessment** (severity + whether it is in scope) within 7 business days.
- A fix and coordinated disclosure timeline communicated as the work proceeds; we credit reporters
  who wish to be named once a fix is released.

## Scope

In scope: the Corex framework plugins (`corex-core`, `corex-blocks`, `corex-forms`, `corex-config`),
the bundled add-ons, the starter theme, and the CLI. Out of scope: vulnerabilities in WordPress core,
third-party plugins, or the hosting environment — report those to their respective maintainers.

## Hardening notes

Corex is built security-first (Constitution Principle VII): a declarative middleware pipeline
(nonce/capability/throttle/sanitize), escaping at output, prepared queries, and no optional plugin as a
hard dependency. The self-update flow only fetches from a source **you** configure and installs through
WordPress's own signed updater — see [`docs/en/05-deployment/updates-and-distribution.md`](docs/en/05-deployment/updates-and-distribution.md).

## Dependency advisories

Corex audits the Composer lockfile, root npm lockfile, and docs-app npm lockfile together:

```bash
npm run verify:dependencies
```

The command preserves raw package-manager findings, then validates them against
`.github/dependency-security-policy.json`. Exit code `0` means every audit ran and every finding is either fixed or
covered by a current bounded exception. Exit code `1` means the policy rejected a new, changed, expired, stale, or
forbidden finding. Exit code `2` means an audit service, command, payload, or the policy itself was unavailable; an
unavailable audit is never reported as clean.

An exception identifies the exact advisory, package, dependency path, severity ceiling, exposure class, reason,
compensating control, owner, review date, and upstream removal trigger. High or critical findings reachable through
shipped runtime or CI cannot be excepted. Do not use `npm audit fix --force`: current npm suggestions include
breaking downgrades unrelated to a supported Corex migration.

Local development servers are not production services. Bind WordPress, webpack, Astro/Vite, and proxy development
servers to loopback; never expose them to untrusted networks; avoid untrusted sites while affected servers run; and
stop them after use. Static docs and compiled WordPress assets do not include these development servers.
