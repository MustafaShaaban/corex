# Research: Junction/Symlink-Safe Block Asset URLs

Phase 0 decisions. No open `NEEDS CLARIFICATION` remained from the spec; the research below records the
design choices that resolve the "HOW" the spec deliberately left open.

## D1 — Where to normalize: the single chokepoint

**Decision**: Normalize the block directory inside `DynamicBlockRegistrar::register()`, immediately
before `register_block_type($dir, $args)`.

**Rationale**: Every block source — `corex-blocks`, `corex-forms`, `corex-careers`,
`corex-kit-portfolio`, `corex-ui` — discovers blocks with `BlockMap::discover()` and registers each via
this one method. Fixing it here covers all of them with a single edit (FR-003) and leaves the
discovery-by-convention contract and each provider untouched (FR-008). No provider passes its own base.

**Alternatives considered**:
- *Edit each provider to anchor on its plugin main file* (`plugin_dir_url(__FILE__)`): touches 5 files,
  re-introduces the same `__DIR__`/realpath fragility per-provider, and spreads the rule. Rejected.
- *Override the computed `src` after registration* (filter `script_loader_src`/style src): brittle,
  global, and fights WordPress's own URL machinery. Rejected.

## D2 — How to map a realpath-resolved dir back under `WP_PLUGIN_DIR`

**Decision**: Build a **mount map** of `realpath(WP_PLUGIN_DIR/<entry>) => <entry>` for each immediate
child of `WP_PLUGIN_DIR`. To normalize a block dir: realpath-normalize it, find the mount whose real
target is a path-prefix of it, and rebuild `WP_PLUGIN_DIR . '/' . <entry> . <remainder>`. If the block
dir is already under `WP_PLUGIN_DIR` (the common junction case), return it unchanged. If no mount
matches (un-mappable), return the original dir untouched and let the probe (D4) flag it.

**Rationale**: The resolved real path alone cannot tell you the mount name — but `WP_PLUGIN_DIR`'s own
entries are the junctions/symlinks, so `realpath()` on each entry yields exactly the real targets to
match against. This is mount-agnostic: it works whether the input arrived as the junction path
(already-under, no-op) or the resolved real path (prefix-matched and rebuilt). Forward-slash + case
normalization (Windows) is applied before comparison so separators/drive-letter casing don't defeat it.

**Purity split**: `BlockPathResolver` is pure — it takes `(string $blockDir, string $pluginsDir,
array<string,string> $mountMap)` and does only string arithmetic, so the realpath-resolved-outside case
is unit-testable with synthetic paths and **no filesystem**. `PluginMountMap` is the thin WP/filesystem
boundary that produces `$pluginsDir` (`WP_PLUGIN_DIR`) and the realpath mount map; it is built once and
injected, memoized per request.

**Alternatives considered**:
- *`str_replace(realpath(WP_PLUGIN_DIR), WP_PLUGIN_DIR, $dir)`*: only fixes the case where the whole
  plugins dir is linked, not a per-plugin junction to an out-of-tree monorepo. Rejected.
- *Store the intended mount path at discovery time*: requires threading state from each provider; the
  chokepoint has no provider identity. The mount map reconstructs it without that coupling. Rejected.

## D3 — Edge handling

- **Already under plugins dir** → return unchanged (no double-map, normalized trailing slash preserved).
- **No assets / unmappable dir** → return original; never fabricate a URL. The probe makes it visible.
- **Mixed separators / drive-letter case (Windows)** → normalize both sides to forward slashes and
  compare case-insensitively on Windows only (POSIX stays case-sensitive).
- **Realpath cache warm vs cold** → because the resolver matches against `realpath()` of the mount
  entries (not the raw `__DIR__`), the result is identical whether the input was pre-resolved or not.

## D4 — Detecting a "bad" asset URL (the probe rule)

**Decision**: An asset URL is **healthy** iff it is a well-formed public URL whose path begins with the
site's plugins URL base (`plugins_url('', __FILE__)`-style base, i.e. `…/wp-content/plugins/`) and whose
remainder is a normal relative path — containing **no** drive-letter segment (`/[A-Za-z]:/`), no `://`
beyond the scheme, and no `..`. The malformed shape
`http://site/wp-content/plugins/C:/wamp64/www/corex/addons/…` is caught by the drive-letter/`:` rule even
though it is technically under the site host. Empty/absent asset handles count as healthy (nothing to load).

**Rationale**: Checking "under `site_url()`" alone is insufficient because the malformed URL *is* under the
host; the discriminator is the embedded absolute filesystem path. The rule is expressed as a pure predicate
(`AssetUrlHealth::isHealthy(string $url, string $pluginsUrlBase): bool`) so it is unit-testable and the probe
stays a thin collector.

**Alternatives considered**:
- *HTTP HEAD each asset URL to check for 404*: network I/O in a health probe, slow and flaky, and the
  block editor wouldn't wait for it. The structural predicate is deterministic and instant. Rejected.

## D5 — Wiring the probe into the existing health seam

**Decision**: In `HealthModule::report()`, collect every registered `corex/*` block's editor/view/style
handle `src` URLs from `WP_Block_Type_Registry` + `wp_scripts()`/`wp_styles()`, and append
`new BlockAssetsProbe($collectedMap, $pluginsUrlBase)` to the probe list — exactly the
`BrandPresentProbe` pattern (boundary computes the inputs, probe judges). It then appears in Site Health
(`site_status_tests`) and `wp corex doctor` automatically, since both consume `report()->results()`.

**Rationale**: Zero new health framework (spec-036 reuse, per the assumption in the spec); the probe is
`Critical` when any URL is unhealthy (a broken editor asset is a real failure), `Good` otherwise.

**Status**: `Critical` chosen (not `Recommended`) because a 404 editor asset makes the block unusable —
it is not advisory. Matches the `PhpVersionProbe` severity model.
