---
title: Model · Controller · Service
description: The layered request path — thin controllers, fat services, repositories, value-object models.
---

Corex enforces a layered architecture (constitution Principle III). Each layer is
generated with the [CLI](/guides/cli/) and resolved through the container.

## The layers

- **Model** — a read-only value object describing an entity's shape (`fields()`,
  `casts()`, `relations()`). Not a god object.
- **Repository** — the **only** layer that talks to the data source. Returns Models /
  Collections.
- **Service** — holds business logic; orchestrates repositories. Never queries the DB
  directly, never echoes output.
- **Controller** — thin: route, validate input shape, call **one** service method, return
  a response. No DB calls, no business rules.

## Scaffold the set

```bash
wp corex make:model Invoice
wp corex make:repository Invoice
wp corex make:service Billing
wp corex make:controller Checkout
```

## Wire it with the container

Everything is injected — never `new` a dependency inside a method:

```php
final class CheckoutController
{
    public function __construct(private readonly BillingService $billing) {}

    public function store(Request $request): Response
    {
        return $this->billing->charge($request->input('invoice_id'));
    }
}
```

Controllers declare their security middleware (`nonce`, `auth`, `throttle`, `sanitize`);
the pipeline applies it automatically (Principle VII) — controllers don't hand-write
security checks.
