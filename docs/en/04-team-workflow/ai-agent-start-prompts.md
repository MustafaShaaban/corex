---
title: AI agent start prompts
audience: team
stability: stable
---

# AI agent start prompts

Copy/paste these at the start of an AI session (any model). They force the agent to check the repo, classify its
[mode](./agent-roles.md), read the right source-of-truth files, stay in scope, follow Spec Kit, run the relevant
guards, apply UI/UX ProMax to UI work, update `PROGRESS.md`/`DECISIONS.md`, and end with a SUMMARY + NEXT STEP.

Keep all examples neutral — use **Acme** placeholders (`sites/acme/`, `acme-site`, `acme-theme`, `http://acme.local`).
Never put a real client name into the framework repo.

## Universal CoreX agent prompt

```text
You are working in the CoreX monorepo. Before doing anything:
1. Run: git fetch --all --prune; git status --short --branch; git branch -vv; gh pr list --state open; git tag --sort=-creatordate | head
2. Classify this session's Role Gate mode: CoreX Framework / Client Site / Deployment / Docs-Planning
   (see docs/en/04-team-workflow/agent-roles.md). State the mode and the files you may edit.
3. Read the source-of-truth files for that mode. Never work from main except inspection/allowed merge/release.
   Use a focused branch. Do not create .worktrees without owner approval.
4. Follow Spec Kit before implementation. Run the relevant Guard Gate before shipping any diff. Apply UI/UX ProMax
   to any UI-facing work.
5. Never edit wp/wp-content/ or dist/ as source. Never commit dist/. Use Acme placeholders only.
6. Update PROGRESS.md and DECISIONS.md. End with the SUMMARY/…/NEXT STEP handoff format (AGENTS.md / WORKING-GUIDE §G.5).
```

## CoreX Framework Mode prompt

```text
Mode: CoreX Framework. You may edit plugins/, addons/, packages/, root theme/, root specs/, root docs/, docs-app/,
ROADMAP.md, root PROGRESS.md, framework admin/login/docs UI, release/versioning.
Read: root AGENTS.md/CLAUDE.md, specs/constitution.md, COREX-WORKING-GUIDE.md, COREX-FRAMEWORK.md, root PROGRESS.md,
ROADMAP.md, the active root spec. Do NOT edit sites/<client>/ unless explicitly authorized.
Follow Spec Kit (root specs/), run clean-code-guard + wp-guard (+ test-guard/docs-guard as relevant), apply UI/UX
ProMax to framework UI. End with the required handoff format.
```

## Client Site Mode prompt

```text
Mode: Client Site. You may edit sites/<client>/ ONLY (e.g. sites/acme/): the client plugin (acme-site), client theme
(acme-theme), pages, blocks, templates, content, branding.
Read: root AGENTS.md/CLAUDE.md (global safety only), sites/<client>/AGENTS.md, CLAUDE.md, PROGRESS.md, DECISIONS.md,
sites/<client>/specs/, and the client brand/design docs.
Do NOT continue the CoreX framework roadmap. Do NOT edit plugins/, addons/, packages/, root theme/, root specs/,
ROADMAP.md, or root PROGRESS.md. For a framework bug, STOP and open a CoreX Framework Mode task.
Follow Spec Kit (sites/<client>/specs/), run the relevant guards, apply UI/UX ProMax to all client UI (homepage,
inner pages, header/footer, blocks, templates, mobile, RTL, accessibility, SEO, performance, keyboard, 200% zoom,
brand consistency). End with the required handoff format.
```

## Deployment Mode prompt

```text
Mode: Deployment. You may edit the dist builder (scripts/build-shared-host-dist.sh), azure-pipelines.yml, deploy
scripts, runtime-file protection, and rollback. Do NOT make client-design or framework-product changes beyond
packaging. Build with npm run build:dist and verify with npm run verify:dist. Never commit dist/. Protect production
runtime files (wp-config.php, .htaccess, uploads/, cache/, upgrade/, debug.log). Use placeholders/secrets — never
real credentials. End with the required handoff format.
```

## Docs/Planning Mode prompt

```text
Mode: Docs/Planning. You may edit docs, specs, roadmap, decisions, prompts, and handoffs. Do NOT ship runtime code
unless explicitly authorized. Keep CHANGELOG entries under Unreleased until a release. Run docs-guard (or the
documented fallback: docs-app npm run build). Keep examples neutral with Acme placeholders. End with the required
handoff format.
```

> **Arabic translation:** these team-workflow prompts are currently English-only. A bilingual (`docs/ar/`) mirror is
> tracked as a backlog item; the English version is canonical for this area until then.
