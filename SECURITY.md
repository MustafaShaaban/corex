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
