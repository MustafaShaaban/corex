---
title: Troubleshooting
description: The real errors and their fixes.
---

## "Your site doesn't include support for this block"

**Cause.** The block is registered server-side but the editor has no JavaScript
registration for it — so WordPress doesn't recognise the type in the editor.

**Fix.** Build the assets. Every Corex block ships an `index.js` (which calls
`registerBlockType` and previews via `<ServerSideRender>`) that must be compiled:

```bash
npm install
npm run build        # produces build/blocks/<name>/index.js + index.asset.php
```

Then confirm registration:

```bash
wp eval 'foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $n => $t) {
  if (strpos($n, "corex/") === 0) echo $n . (empty($t->editor_script_handles) ? " - no editor script\n" : " - ok\n");
}' --path=wp
```

## I can't see the add-on blocks at all

The add-on **plugins aren't active** (or aren't linked into `wp-content`). Re-check
[Wiring the monorepo](/getting-started/monorepo-wiring/) then activate them
([First run](/getting-started/first-run/)):

```bash
wp plugin list --path=wp        # are corex-ui, corex-forms, … present + active?
```

## Missing WordPress install

Corex is a framework — it needs WordPress around it. If `wp` commands fail with "This does
not seem to be a WordPress installation", you haven't installed core into `./wp` yet. See
[WAMP / Apache + WP-CLI](/getting-started/wamp-apache/).

## Junctions broke after renaming the repo folder

A Windows junction stores the target's **absolute path**, so moving/renaming the repo
breaks all of them. Repoint each:

```powershell
cmd /c rmdir "C:\wamp64\www\corex\wp\wp-content\plugins\corex-core"
cmd /c mklink /J "C:\wamp64\www\corex\wp\wp-content\plugins\corex-core" "C:\wamp64\www\corex\plugins\corex-core"
# …repeat for every plugin/add-on and the theme
```

## MySQL/Apache won't start (no admin rights on WAMP)

Launch MySQL directly (no elevation needed):

```powershell
Start-Process "C:\wamp64\bin\mysql\mysql8.3.0\bin\mysqld.exe" `
  -ArgumentList '--defaults-file="C:\wamp64\bin\mysql\mysql8.3.0\my.ini"' -WindowStyle Hidden
```

WP-CLI and the test suites only need MySQL; a browser smoke test needs Apache (start full
WAMP from the tray).

## `wp corex` commands aren't found

They register only when WP-CLI is present **and** `corex-core` is active. Confirm with
`wp plugin list --path=wp`, and target the install with `--path=wp`.
