## Summary

What this PR changes and why. Link the spec/issue (e.g. `specs/0XX-...`, `Closes #123`).

## Type

- [ ] Feature (went through Spec Kit: specify → clarify → plan → tasks → implement)
- [ ] Fix
- [ ] Docs
- [ ] Refactor / chore

## Checklist (Definition of Done)

- [ ] Follows the [constitution](specs/constitution.md) (token-only styling, logical CSS, no optional
      plugin as a hard dependency, dynamic/server-rendered blocks, declarative security).
- [ ] **Guard Gate** run on the diff: `clean-code-guard` + (`wp-guard` for WP code) + `test-guard` for
      tests + `docs-guard` for docs.
- [ ] Tests added/updated and green (`composer test`; Jest for block JS).
- [ ] i18n-ready (literal `corex` text domain), RTL-verified, WCAG 2.2 AA where UI is involved.
- [ ] Docs updated (READMEs + `docs/` + docs-app) and `PROGRESS.md` / `DECISIONS.md` as needed.
- [ ] Branched off `develop`; CI green.

## Notes for reviewers

Anything that needs a closer look, env-gated behavior, or follow-ups.
