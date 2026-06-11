# Corex Kit — WooCommerce

The WooCommerce store starter kit — a Blueprint and shop composition for a Corex site
running WooCommerce. **WooCommerce is never a hard dependency:** the kit self-disables
when Woo is inactive or its feature flag is off (Principle IX).

> Requires the `corex-core` plugin. WooCommerce is required **at runtime** to do anything,
> but the kit degrades safely without it.

## Enabling the kit

The kit runs only when **both** are true:

1. WooCommerce is active (`class_exists('WooCommerce')`), and
2. the `woocommerce_kit` [feature flag](../../plugins/corex-core/README.md#feature-flags) is on.

Turn it on per-site:

```bash
wp option update corex_features_woocommerce_kit 1 --path=wp
# or in .env:  FEATURES_WOOCOMMERCE_KIT=1
```

With the flag off (the default) the `WooServiceProvider` is a no-op — verified on a real
install: the `woocommerce` blueprint is not registered until the gate allows it.

## What it provides

- **`WooBlueprint`** — declares the store kit's modules/templates/parts/patterns for
  tooling and the setup wizard.
- **HPOS compatibility** — the plugin declares `custom_order_tables` compatibility on
  `before_woocommerce_init`. The kit is presentation + a blueprint; it never reads orders
  by direct post meta, so it is HPOS-safe.
- The shop/product display uses **WooCommerce's own blocks and templates**; the kit
  composes them with the Corex hero/features/CTA patterns and the theme's templates.

## Architecture

`WooKitGate` holds the pure enable decision (`isEnabled(bool $wooActive)`), so the
"never a hard dependency" guarantee is unit-tested without WooCommerce loaded. The
provider passes `class_exists('WooCommerce')` into the gate at boot.

## Tests

```bash
composer test   # the gate (Woo-absent / flag-off self-disable) + the blueprint manifest
```

> The gate, self-disable, and manifest are covered headlessly. The **storefront visuals**
> (Woo blocks + the composed patterns) should be confirmed in a browser with sample products.
