---
title: Cookbook — WooCommerce detect-and-defer
description: Use WooCommerce when it is present without ever making it a hard dependency.
audience: contributor
stability: stable
last_verified: null
---

# Cookbook — WooCommerce detect-and-defer

**The problem.** You want a feature that uses WooCommerce when the site has it, but the framework must keep
working on a site that does **not** (Principle IX: no optional plugin is a hard dependency). The pattern is
*detect-and-defer*: check for the dependency at the boundary, and degrade cleanly when it is absent.

This is a real Corex pattern — `corex-kit-woo` and the forms `SendEmailListener` both use it. Exact class
signatures are in the generated reference (docs-app); the shapes below are what you write.

## Example 1 — a feature gated on Woo presence *and* an opt-in flag

The WooCommerce kit runs only when WooCommerce is active **and** the operator turned its feature flag on. The
gate is a pure, testable predicate; the provider self-disables otherwise.

```php
// A pure gate — unit-testable with no WordPress (this is how Corex\Woo\WooKitGate works).
final class WooKitGate
{
    public function isEnabled(bool $flagOn): bool
    {
        return $flagOn && class_exists('WooCommerce');
    }
}
```

```php
// The service provider defers entirely when the gate is closed — a Woo-less site is unaffected.
public function boot(): void
{
    $flagOn = Config::enabled('woocommerce_kit');           // the feature flag
    if (! $this->gate->isEnabled($flagOn)) {
        return;                                             // no-op: self-disable
    }
    // ... register the storefront kit (reuses Woo's own blocks/templates) ...
}
```

Because the gate is a plain method, you test **both** shapes with no WooCommerce installed:

```php
expect($gate->isEnabled(true))->toBeFalse();   // flag on, Woo absent → still off
expect($gate->isEnabled(false))->toBeFalse();  // Woo present, flag off → off
```

```text
✓ both pass — the feature can never fire by accident
```

## Example 2 — a listener that prefers a seam, then falls back

The forms engine sends mail through the neutral `Mailer` **seam** when the Mail add-on is active, and falls
back to `wp_mail` when it is not — the same detect-and-defer shape, for a different dependency.

```php
public function handle(FormSubmittedEvent $event): void
{
    if ($this->container->has(Mailer::class)) {
        $this->container->make(Mailer::class)->send($message);   // prefer the seam
        return;
    }
    wp_mail($to, $subject, $body);                               // defer to core
}
```

The two examples are different *shapes* of the same idea: Example 1 gates a whole feature off; Example 2 picks
the better of two paths at runtime. Both keep the optional dependency optional.

## Pitfalls

- Do **not** `require` or `use` a Woo class at the top of a file that loads unconditionally — that turns the
  optional dependency into a fatal on a Woo-less site. Guard with `class_exists()` / `function_exists()` at the
  boundary.
- For order/product data, when you *are* inside the gated path, use the WooCommerce CRUD APIs (HPOS-safe), not
  direct meta — see the woo-guard rules.

## See also

- [`addons/corex-kit-woo/`](../../../addons/corex-kit-woo/) (the real gate) ·
  [Feature flags](../../README.md#what-lives-where) · the generated `WooKitGate` reference in docs-app.
