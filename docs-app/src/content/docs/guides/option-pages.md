---
title: Custom option pages
description: Add a secured admin settings page with one declaration — fields, menu, and save handled for you.
---

Need your own admin settings page? Declare an **`OptionPage`** — a title, where it sits in the menu, who may
see it, and a list of fields — register it, and Corex renders a secured, token-styled form and persists the
values. You write no form HTML, no nonce handling, and no save loop (spec 039).

## Declare a page

```php
use Corex\Config\Options\OptionPage;

$page = new OptionPage(
    slug: 'billing',
    title: 'Billing',
    menuLabel: 'Billing',
    capability: 'manage_options',
    parent: 'corex-settings',          // '' for a top-level menu
    fields: [
        ['key' => 'billing.tax_id',       'label' => 'Tax ID',       'type' => 'text'],
        ['key' => 'billing.invoice_logo', 'label' => 'Invoice logo', 'type' => 'media'],
        ['key' => 'billing.mode',         'label' => 'Mode',         'type' => 'select',
            'options' => ['draft' => 'Draft', 'live' => 'Live']],
        ['key' => 'billing.enabled',      'label' => 'Enabled',      'type' => 'checkbox'],
    ],
);
```

## Register it

In a service provider's `boot()`:

```php
$container->make(Corex\Config\Options\OptionPageRegistry::class)->register($page);
```

That's it — the page appears in the admin under its parent (or as a top-level menu). Each field renders with the
same control as **Corex → Settings**: text/email/url/password inputs, a **media picker**, selects, and
checkboxes. Saving verifies the page's **capability** and a **per-page nonce**, sanitises each value by its type,
and stores it.

## Read the values

Field keys are ordinary `Config` dot-keys, so anywhere in your app:

```php
$taxId = $config->get('billing.tax_id');
```

`password` fields are write-only in the UI — they save but are never re-rendered with their value.

## Scaffold one from the CLI

```bash
wp corex make:option-page Billing
```

writes an `OptionPage` definition (`App\Options\Billing`) with an example field, ready to register and extend —
like the other `make:*` generators.

## How it's built

An `OptionPage` is a `FieldSections` — the same seam the built-in settings screen uses — so the one
`SettingsForm` (per-type controls) and the one save loop render and persist either, with **no duplicated form
code**. The page value + registry are pure and unit-tested; the screen + the WP-CLI command are thin boundaries
(cap + nonce + sanitise on save; output escaped per type).

## See also

- [Settings & feature flags](./configuration.md) — the built-in settings the same controls power.
- [The CLI](./cli.md) — the `make:*` generators.
