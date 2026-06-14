# Contract: Foundations tokens (US2)

Add the three genuinely-missing token groups to `theme/theme.json` `settings.custom`; document all groups.

## New tokens (CSS custom properties at runtime)

| Token | CSS variable | Example value |
|---|---|---|
| motion.duration.fast/base/slow | `--wp--custom--motion--duration--fast` … | 150ms / 250ms / 400ms |
| motion.easing.standard/emphasized | `--wp--custom--motion--easing--standard` … | `cubic-bezier(.2,0,0,1)` / `cubic-bezier(.3,0,0,1)` |
| focus.width / color / offset | `--wp--custom--focus--width` … | 2px / `var(--wp--preset--color--accent)` / 2px |
| z.base/dropdown/sticky/overlay/modal/toast | `--wp--custom--z--modal` … | 0 / 1000 / 1100 / 1200 / 1300 / 1400 |

## Invariants

1. Each new token resolves as a CSS custom property on the front end and in the editor.
2. A `brand.json` override of any new token changes the rendered result with **no recompile** (Principle V;
   spec-006 resolver).
3. Existing groups (color/typography/spacing/shadow/radius/layout) are unchanged — documented, not duplicated.

## Test / validation

- theme.json remains valid JSON (existing theme-json validity test covers it).
- A Foundations doc page lists every group with its variable + allowed values + usage rule.
- Manual/env-gated: a component consuming `--wp--custom--z--modal` layers correctly; a brand.json override flows.
