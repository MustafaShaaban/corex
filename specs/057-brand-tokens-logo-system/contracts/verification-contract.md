# Contract: Visual and Compatibility Evidence

## Headless evidence (required)

### Token/source inventory

- JSON/schema validity for theme and variations.
- Unique definitions and generated properties.
- Complete required palette/font lists per mode/variation/fixture.
- Every consumer resolves to a canonical definition or active alias.
- No unrecorded raw design value outside centralized admin or functional-layout allowances.

### Contrast and focus matrix

The evidence dataset lists every supported foreground/background, control, status, border, and focus pairing for
light/default and dark modes.

- Normal text: minimum 4.5:1.
- Large text: minimum 3:1.
- Meaningful non-text UI and focus boundaries: minimum 3:1 against adjacent context.
- Any failed pair blocks the corresponding token values.

### Compatibility

- Existing stable slugs remain.
- Aliases resolve to one canonical name.
- Deprecation window and consumer count are recorded.
- `BrandResolver` associative merging and list replacement remain covered.
- Complete and incomplete palette/font replacement fixtures have explicit outcomes.

### Assets

- Font records enforce WOFF2, family/role/weight/subset, file-count, provenance, swap, and preload rules.
- Logo records remain blocked until owner approval/provenance exists.

## Rendered/manual evidence

### Direction fixture matrix

Run every fixture in light and dark modes and both LTR/RTL document directions:

- Arabic-only prose and headings;
- English-only prose and headings;
- mixed CoreX/product names;
- code and commands inside Arabic text;
- Arabic and Western numerals;
- punctuation and parentheses;
- badges/technical labels;
- long translations; and
- nested direction changes.

Verify shaping/order, bidi isolation, logical alignment, keyboard focus order, clipping, and horizontal overflow at
default and 200% zoom.

### Additional manual/browser matrix

- focus on base, raised, status, overlay, and admin-adapter surfaces;
- forced-colors/high-contrast mode;
- text resizing/content expansion;
- imagery/gradient contexts;
- font fallback, swap, and network request count;
- unused preload warnings; and
- approved logo variants/minimum sizes after the logo gate opens.

## Environment status vocabulary

| Status | Meaning |
|---|---|
| `PASS` | Check executed against the named environment and met its contract. |
| `FAIL` | Check executed and did not meet its contract. |
| `BLOCKED` | Required owner asset/decision is missing. |
| `ENVIRONMENT-GATED` | Docker, WordPress, browser runtime, or deployment target was unavailable; not a pass. |

Headless failures always block. Environment-gated rendered evidence remains explicit in progress/release notes and
is rerun when the required environment becomes available.
