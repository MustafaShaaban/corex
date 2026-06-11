# Quickstart — build & verify Corex blocks (018)

Proves the feature end-to-end on a real install. Prerequisites: WordPress 7.0+ with the monorepo mapped into
`wp-content` and the Corex plugins active (see PROGRESS "Environment quick reference"); Node 20+ + npm.

## 1. Build the block assets

```bash
npm install
npm run build            # compiles every block package's SCSS+JS → build/blocks/
```

Expected per block: `build/blocks/<name>/{index.js, index.asset.php, style-index.css, style-index-rtl.css}`
(forms also `view.js`).

## 2. Verify editor registration (no "block not supported")

```bash
wp eval 'foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $n=>$t){
  if (strpos($n,"corex/")===0) echo $n.(empty($t->editor_script_handles)?" — NO editor script\n":" — ok\n"); }' --path=wp
```

Expected: every `corex/*` block prints `— ok`.

## 3. Verify the server render

```bash
wp eval 'echo do_blocks("<!-- wp:corex/copyright /-->");' --path=wp
```

Expected: escaped `<p class="corex-copyright">© <year> <site>…</p>`.

## 4. Verify conditional + RTL assets

- A page without a block does not enqueue that block's CSS/JS.
- `build/blocks/<name>/style-index-rtl.css` exists for every styled block.

## 5. Headless test (no build needed)

```bash
composer test            # registers blocks from source; full Pest suite green
```

## 6. Browser smoke (manual — needs Apache)

Open the Site Editor, insert a Corex block from the **Corex** inserter category, confirm it previews and is
not flagged unsupported.

## Acceptance mapping

SC-001 ↔ step 2 · SC-002 ↔ step 1 · SC-003 ↔ step 4 · SC-004 ↔ step 5 · SC-005 ↔ `wp plugin list` active +
no fatals · SC-006 ↔ token-only unit scan + step 4.
