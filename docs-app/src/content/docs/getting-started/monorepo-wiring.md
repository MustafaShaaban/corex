---
title: Wiring the monorepo into wp-content
description: Map the theme + plugins + add-ons into the WordPress install with junctions (Windows) or symlinks.
---

The repo is the single source of truth; WordPress loads it through links in
`wp-content/`. Core is never copied or committed.

## Windows (junctions)

Junctions point a `wp-content` entry at the repo source. Create one per package:

```powershell
$root = "C:\wamp64\www\corex"
$dest = "$root\wp\wp-content\plugins"

# Plugins
cmd /c mklink /J "$dest\corex-core"        "$root\plugins\corex-core"
cmd /c mklink /J "$dest\corex-blocks"      "$root\plugins\corex-blocks"
cmd /c mklink /J "$dest\corex-config"      "$root\plugins\corex-config"
cmd /c mklink /J "$dest\corex-forms"       "$root\plugins\corex-forms"

# Add-ons
cmd /c mklink /J "$dest\corex-ui"          "$root\addons\corex-ui"
cmd /c mklink /J "$dest\corex-email"       "$root\addons\corex-email"
cmd /c mklink /J "$dest\corex-careers"     "$root\addons\corex-careers"
cmd /c mklink /J "$dest\corex-captcha"     "$root\addons\corex-captcha"
cmd /c mklink /J "$dest\corex-newsletter"  "$root\addons\corex-newsletter"
cmd /c mklink /J "$dest\corex-bookings"    "$root\addons\corex-bookings"
cmd /c mklink /J "$dest\corex-kit-company" "$root\addons\corex-kit-company"

# Theme
cmd /c mklink /J "$root\wp\wp-content\themes\corex" "$root\theme"
```

:::caution[Folder-rename gotcha]
A junction stores the target's **absolute path**. Renaming or moving the repo folder
breaks every link — repoint them with `cmd /c rmdir <link>` then `mklink /J` again.
:::

## macOS / Linux (symlinks)

```bash
ln -s "$PWD/plugins/corex-core" wp/wp-content/plugins/corex-core
# …one per plugin/add-on, and:
ln -s "$PWD/theme" wp/wp-content/themes/corex
```

## Verify

```bash
wp theme list --path=wp     # shows: corex
wp plugin list --path=wp    # shows all corex-* plugins
```

Next: **[First run & brand](/getting-started/first-run/)**.
