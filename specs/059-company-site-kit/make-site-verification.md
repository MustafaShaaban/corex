# `wp corex make:site` verification (company-site readiness item #6)

**Date:** 2026-06-20 · **Environment:** local WAMP WordPress at `wp/` (WP-CLI 2.12.0); the `corex` WP-CLI namespace
is registered and `make:site` is available.

## What was run

```bash
# from a temp dir, against the local install:
wp --path=<repo>/wp corex make:site "Acme Industries"
# → Success: Client site scaffolded: …/acme-industries
```

The run was a throwaway verification in an OS temp directory; the scaffold was inspected and deleted (nothing
committed to the framework repo).

## Result — the first real company website CAN be started

`make:site` works and produces a correctly **isolated** client project:

- `plugins/acme-industries-site/` — client app code (PSR-4 `src/` with Models/Services/Controllers/Api/Blocks/
  Options), a `ServiceProvider` that registers with **Corex's container** on boot. Business logic lives here.
- `themes/acme-industries/` — a presentation-only "Corex client skin": `style.css`, `theme.json` (v3,
  `appearanceTools`, client-namespaced `--wp--custom--acme-industries--*` tokens), `parts/header.html`,
  `templates/index.html`.
- Governance: `AGENTS.md`, `CLAUDE.md`, `README.md`, `PROGRESS.md`, `DECISIONS.md`, `specs/`, `docs/`, `.gitignore`,
  all repeating the rule **"Edit only the client plugin/theme — never the Corex framework."**

This confirms the goal's finish line: a real company website can be **started** with `wp corex make:site`, and the
generated structure lets the client use Corex's framework/container **without editing framework internals**.

## Gaps (record as future specs)

The scaffold is intentionally minimal; it does **not yet inherit the visual foundations**:

1. **Visual-foundation inheritance gap.** The client theme is standalone (no `Template: corex` parent) and its
   `theme.json` defines only client-namespaced tokens. It does **not** auto-inherit the M2 brand tokens (Spec 057)
   or the M3 `corex/header-*` / `corex/footer-*` parts and patterns (Spec 058). Its `parts/header.html` is a bare
   `wp:site-title`. → Future spec: either scaffold the client theme as a **child of the Corex theme**, or have the
   **M4 company-kit apply** populate the client theme with M3 parts + M2-token-aligned `theme.json`, or add a
   documented token/parts inheritance step to `make:site`.
2. **Company-kit content gap.** The scaffold has no company pages; that is exactly **Spec 059 / M4** (this spec),
   which is specify-complete but not yet implemented (its plan/tasks/implementation follow the M3 merge).
3. **Dependency ordering.** M3 (Spec 058, PR #56) is implementation-complete but **not yet merged** (review/merge
   permission boundary). M4 implementation and the richer make:site inheritance should land after M3 merges.

## Conclusion

Item #6 is verified: `make:site` starts a real, framework-isolated company site today. Full brand/navigation/page
richness depends on merging M3 (PR #56) and implementing M4 (Spec 059) plus the visual-foundation inheritance gap
above — the recommended next steps, not blockers to *starting* a site.
