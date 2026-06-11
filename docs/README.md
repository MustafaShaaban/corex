# Corex Handbook — set up, run, deploy, and contribute

This is the **in-repo handbook** for people who **work on or operate a Corex project**: getting a dev
environment running, dockerizing the stack, deploying to production, and following the team's workflow. It is
written as plain Markdown so it renders directly on GitHub — no build step.

> **New to Corex and just want to learn the framework / look up a class?** That's the **published docs site**,
> not this handbook. See [What lives where](#what-lives-where) below.

**Language:** English · العربية _(translation pending — see [Languages](#languages))_

---

## Who this is for — audience tiers

Every page declares an `audience` in its front-matter. Pick your starting point:

| Tier | You are… | Start here |
|---|---|---|
| **setup** | a developer getting Corex running on your machine (assumes **zero** prior Corex; some pages assume zero prior DevOps) | [00 · Getting started](./en/00-getting-started/) |
| **ops** | an operator deploying / running Corex in production | [05 · Deployment](./en/05-deployment/) |
| **contributor** | someone changing the Corex codebase | [04 · Team workflow](./en/04-team-workflow/) |

No page assumes you already know a tool: the first time one is mentioned, it's introduced with what it is, how
to install it on Windows / Linux / macOS, and a command to verify it.

---

## Sections

| # | Section | What's in it | Status |
|---|---------|--------------|--------|
| 00 | [Getting started](./en/00-getting-started/) | Local setup, one guide per OS (Windows+WAMP, Windows+XAMPP, Linux, macOS, wp-env/Docker) | phase D2 |
| 01 | Architecture | **Lives in docs-app** — this handbook links to it (no duplication) | see [What lives where](#what-lives-where) |
| 02 | Core concepts | **Lives in docs-app** (guides) — linked | see [What lives where](#what-lives-where) |
| 03 | Class reference | **Generated** by `wp corex docs:generate`, published in docs-app — linked, never hand-written | see [What lives where](#what-lives-where) |
| 04 | [Team workflow](./en/04-team-workflow/) | Onboarding, git-flow-lite, commits, PR review, Claude Code + Spec Kit, quality gates | phase D7 |
| 05 | [Deployment](./en/05-deployment/) | Docker dev/prod, Azure, AWS, cPanel, CI/CD, secrets, backups, zero-downtime | phases D3–D6 |
| 06 | [Cookbooks](./en/06-cookbooks/) | Complex scenarios — Woo detect-and-defer, multisite, headless, AI-agent flows, paid add-ons | phase D8 |
| 07 | [Troubleshooting](./en/07-troubleshooting/) | The real errors and how to fix them | phase D9 |
| 08 | [Contributing](./en/08-contributing/) | How to contribute (links to the authoritative working guide) | phase D9 |

> Sections marked with a future phase are **scaffolded but not yet authored** — content lands one phase per
> session (see [`specs/028-developer-handbook/tasks.md`](../specs/028-developer-handbook/tasks.md)).

---

## What lives where

Corex has **two** documentation surfaces, split by audience so nothing is duplicated (and so the class
reference can never drift):

| You want… | Go to | Why |
|---|---|---|
| To **learn the framework**, read guides, look up a **class/method** | **docs-app** (the published site) — source in [`docs-app/`](../docs-app/) | It's the product documentation; the class reference is **generated** from the code so it's always current. |
| To **set up, dockerize, deploy, or contribute** | **this handbook** (`docs/`) | Operational/contributor content that renders on GitHub where you actually work. |

This handbook **links** to docs-app for architecture and the class reference — it never copies them.

---

## Conventions

- Every command is shown with its **expected output** in a separate block; every code block has a language tag.
- Diagrams are [Mermaid](https://mermaid.js.org) (` ```mermaid `) — GitHub renders them with no image build.
- Domain terms link to the [glossary](./_glossary.md); code identifiers are never translated (see the
  [translation memory](./_translation-memory.md)).
- A page that documents something **not yet built** is marked `stability: planned` and links to the spec that
  will produce it.

New pages start from [`en/_template.md`](./en/_template.md) (and
[`en/_class-reference-stub.md`](./en/_class-reference-stub.md) for a class link-stub).

---

## Languages

- `en/` — English, authored first (this is the source of truth for the handbook).
- `ar/` — Arabic, a file-for-file mirror with `> TODO: translation pending` placeholders, scaffolded in
  phase D10 so translation can begin without restructuring. Code identifiers stay English per the
  [translation memory](./_translation-memory.md).

---

## See also (repository root — the authoritative project docs)

- [`COREX-FRAMEWORK.md`](../COREX-FRAMEWORK.md) — the architecture reference.
- [`COREX-WORKING-GUIDE.md`](../COREX-WORKING-GUIDE.md) — how the team works + the continuity protocol.
- [`COREX-SPECKIT-START.md`](../COREX-SPECKIT-START.md) — the Spec Kit build sequence.
- [`specs/constitution.md`](../specs/constitution.md) — the non-negotiable rules.
