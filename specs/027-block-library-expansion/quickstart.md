# Quickstart: Block library expansion (027)

## Prerequisites

- The repo + the corex-ui add-on. The renderers are unit-testable headlessly; the editor preview needs a
  browser (Apache) for the visual smoke.

## 1. Renderer unit tests (no WP)

```bash
vendor/bin/pest tests/Unit/Ui/ComponentBlocksTest.php
```

Expected: each renderer (stat, testimonial, pricing, accordion) returns the expected accessible, escaped markup
from attributes; empty attributes degrade gracefully; a token-only scan finds no hardcoded color/size. Green.

## 2. Manifest enumerates the new blocks

```bash
vendor/bin/pest tests/Unit/Ui/UiManifestTest.php
```

Expected: `UiManifest` lists `corex/stat`, `corex/testimonial`, `corex/pricing`, `corex/accordion` alongside the
existing blocks (kits can compose them) — no engine change.

## 3. Build the block assets

```bash
npm run build
```

Expected: each new block compiles `build/blocks/<slug>/{index.js,index.asset.php,style-index.css,style-index-rtl.css}`.

## 4. Live registration (real WP)

```bash
wp eval 'do_action("init"); $r = WP_Block_Type_Registry::get_instance(); foreach (["corex/stat","corex/testimonial","corex/pricing","corex/accordion"] as $n) echo $n . ": " . ($r->is_registered($n) ? "ok" : "MISSING") . "\n";' --path=wp
```

Expected: each new block prints "ok" (registered, dynamic, in the Corex category). The editor visual is the
Apache-gated browser smoke.
