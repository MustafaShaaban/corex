# Contract: Handbook page structure

## Every page

- Has the front-matter (`title`, `description`, `audience`, `stability`, `last_verified`) from `data-model.md`.
- Is beginner-first: no "simply"/"just"; no skipped steps; defines a term before using it.
- Every command is a **language-tagged** fenced block followed by its **expected output** in a separate block.
- Links (never duplicates) to `docs-app/` for any architecture or class-reference content.
- Any not-yet-built reference is `stability: planned` + a link to its Spec Kit module.

## Getting-started guide (per OS) — `00-getting-started/`

1. Intro + audience note (zero Corex, possibly zero DevOps).
2. Prerequisites — each tool: one-line description + install (Windows / Linux apt / macOS brew) + verify command
   + expected output.
3. Get the code (clone) → install deps (Composer + npm) → install WordPress → **map the monorepo into
   wp-content** (the OS-correct mechanism: junction / symlink / Docker mount) → activate theme + plugins.
4. Verify: `wp theme list` shows `corex`; `wp plugin list` shows the core plugins; the site boots.
5. "Where to next" links (Docker, deployment, team-workflow).

## Docker page — `05-deployment/`

- The dev `docker compose` services + what each is for + the **monorepo bind-mount mapping**.
- Commands: bring up, tear down, reset DB, run Pest, run JS tests — each with expected output.
- The multi-stage production `Dockerfile` (build → lean runtime).
- A **Mermaid** dev topology diagram and a prod topology diagram.

## Deployment recipe — `05-deployment/<target>.md`

Follows the *Deployment recipe* shape in `data-model.md` (provision → config → deploy-from-tag → HTTPS →
secrets → backups → rollback → zero-downtime → CI/CD → topology diagram → verify). cPanel additionally
documents the **no-symlink** strategy.

## Team-workflow page — `04-team-workflow/`

- Onboarding checklist; git-flow-lite (branches off `develop`, releases tagged on `main`); Conventional Commits;
  PR review; the Claude Code + Spec Kit loop; the Guard Gate (which guard runs when).
- **Links** to `COREX-WORKING-GUIDE.md` + the constitution as the authoritative source (this page is the
  newcomer on-ramp, not a second copy of the rules).

## Cookbook page — `06-cookbooks/<scenario>.md`

- States the problem + when it applies; **≥2 worked examples of different shapes**; pitfalls; links to the
  relevant generated reference / spec.

## Verification (docs-guard, every page)

- Every referenced class / command / hook / flag / path exists in the source (or is `stability: planned`).
- Every command block has a language tag + an expected-output block.
- Every topology/lifecycle page has a Mermaid block.
- No architecture/class-reference content is duplicated from docs-app (links only).
