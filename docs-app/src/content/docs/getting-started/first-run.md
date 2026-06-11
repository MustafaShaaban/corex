---
title: First run & brand
description: Activate the theme and plugins, build assets, and apply a brand.
---

## 1. Activate the theme and plugins

```bash
wp theme activate corex --path=wp

wp plugin activate \
  corex-core corex-blocks corex-config corex-forms \
  corex-ui corex-email corex-captcha corex-newsletter \
  corex-careers corex-bookings corex-kit-company --path=wp
```

`corex-core` self-initializes on `plugins_loaded`; the others mount on it.

## 2. Build the front-end assets

Blocks ship SCSS + JS that compile with `@wordpress/scripts`. Without a build, the editor
reports *"block not supported"* (see [Troubleshooting](/troubleshooting/)).

```bash
npm install
npm run build
```

## 3. Verify the blocks register

```bash
wp eval 'foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $n => $t) {
  if (strpos($n, "corex/") === 0) echo $n . (empty($t->editor_script_handles) ? " (no editor script)\n" : " ✓\n");
}' --path=wp
```

Every `corex/*` block should print `✓`.

## 4. Apply a brand

Design tokens live in `theme/theme.json`; a per-site `brand.json` overrides them at
runtime (deep-merged via `BrandResolver`). See **[Apply a brand](/guides/branding/)**.

## 5. Log in

Visit `http://corex.local/wp-admin/` (or your vhost). The Corex admin menu, login
branding, and settings screen come from `corex-config`.
