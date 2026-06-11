# Quickstart: validating the developer & operations handbook (028)

The handbook's "tests" are doc-quality checks + real runs of its recipes.

## 1. docs-guard pass (per page, before shipping)

For every changed page, verify:
- every referenced class / command / hook / flag / path exists in the source — or the page is
  `stability: planned` with a link to its Spec Kit module;
- every command block is language-tagged and followed by an expected-output block;
- every topology/lifecycle/deployment page has a ` ```mermaid ` block;
- no architecture or class-reference content is duplicated from `docs-app/` (links only).

## 2. Link / structure check

- `docs/ar/` mirrors `docs/en/` file-for-file (placeholders) — counts match.
- Internal links resolve; links into `docs-app/` point at real pages.
- `_glossary.md` and `_translation-memory.md` exist and cover the terms used.

## 3. Mermaid renders on GitHub

- Open a changed topology page on GitHub (or a Mermaid preview) and confirm each diagram renders (no syntax
  errors). No external image build is involved.

## 4. Recipe execution (phase D12 — the real proof)

- Follow each `00-getting-started/<os>.md` on a clean machine/VM for that OS → the site boots
  (`wp theme list` shows `corex`).
- `docker compose up` from a clean checkout → stack up, site reachable, tests run inside the container.
- Follow each `05-deployment/<target>.md` to deploy a release tag end-to-end over HTTPS.
- Stamp each verified page's `last_verified: YYYY-MM-DD`.

## What "done" looks like per phase

Each phase (D1–D12) ends green on checks 1–3 for the pages it added; D12 adds check 4 across the setup +
deployment guides.
