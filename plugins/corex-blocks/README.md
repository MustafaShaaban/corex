# corex-blocks

The Corex block engine: convention-based discovery of `corex/*` blocks and their registration via
`register_block_type`, with conditional assets (declared in each `block.json`), a container-resolved render
callback, and Block-Bindings connectors. Server-rendered; no JS build required for registration.

## Mount-safe asset URLs (spec 040)

WordPress derives a block's editor/view/style URLs from the directory passed to `register_block_type`, using
`plugins_url()` — which only works when that path is under `WP_PLUGIN_DIR`. In a monorepo dev setup the plugins
are linked into `wp-content/plugins` (junctions on Windows, symlinks on POSIX). If the block directory is ever
**realpath-resolved** to its real location outside `wp-content/plugins` (symlink mounts, a `realpath()` call, or
the PHP realpath cache), `plugins_url()` cannot strip the prefix and emits a malformed URL embedding a filesystem
path (e.g. `…/wp-content/plugins/C:/…/addons/corex-ui/…/index.js`), which 404s and silently breaks the block in
the editor.

To prevent this on any mount, `DynamicBlockRegistrar` normalizes every discovered block directory back to its
`WP_PLUGIN_DIR`-relative location before registration:

- `BlockPathResolver` (pure) maps a possibly-realpath-resolved dir back under the plugins dir using a mount map;
  it returns an already-under-plugins path **unchanged** (no regression for the junction case).
- `PluginMountMap` is the boundary that builds the map (`realpath()` of each plugin-dir entry → its mount name).

The fix lives at the single registration chokepoint, so it covers `corex-blocks` and every add-on provider with
no per-provider change.

## Visibility: the block-assets health check

`BlockAssetsProbe` (in `corex-core`, folded into the spec-036 health seam) inspects every registered `corex/*`
block's asset URLs and reports a **failing** Site Health / `wp corex doctor` status — naming the offending
blocks — if any URL embeds a filesystem path, so a misconfigured mount is diagnosed in seconds instead of
showing up as a blank editor panel.
