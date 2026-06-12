---
title: Cookbook — AI-agent-driven flows
description: Expose safe, read-only capabilities to AI agents via the WP 7.0 Abilities API.
audience: contributor
stability: stable
last_verified: null
---

# Cookbook — AI-agent-driven flows

**The problem.** You want an AI agent (or MCP client) to discover and act on a Corex site **safely** — read-only
where it should be, capability-gated always, and a no-op on a WordPress that lacks the Abilities API. Corex
ships an `AbilitiesProvider` that does exactly this; you extend it the same way.

## Example 1 — what Corex registers (read-only, gated, REST-exposed)

`AbilitiesProvider` registers abilities on the WP 7.0 Abilities init hooks, guarded by `function_exists` so it
is a no-op on older cores. Each ability is read-only, capability-gated, and shown in REST.

```php
public function boot(): void
{
    if (! function_exists('wp_register_ability')) {
        return;                                  // Abilities API absent → no-op
    }
    add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
}
```

```json
// GET /wp-json/wp/v2/abilities/corex/list-blocks  →
[ { "name": "corex/stat" }, { "name": "corex/testimonial" }, ... ]
```

## Example 2 — add your own ability

Follow the same shape: a one-line read-only data method + a gated registration. Keep the `execute_callback`
free of side effects for a read ability, and gate writes behind a real capability.

```php
private function registerReadOnlyAbility(string $id, string $label, callable $execute): void
{
    wp_register_ability($id, [
        'label'               => $label,
        'category'            => 'corex',
        'execute_callback'    => $execute,
        'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
        'meta'                => ['annotations' => ['readonly' => true], 'show_in_rest' => true],
    ]);
}

// register a custom one:
$this->registerReadOnlyAbility(
    'corex/recent-forms',
    __('Recent form submissions (count)', 'corex'),
    static fn (): array => ['count' => $repository->countRecent()],
);
```

```text
GET /wp-json/wp/v2/abilities/corex/recent-forms → { "count": 12 }
```

The two examples differ in shape: Example 1 is the **built-in** read surface; Example 2 **extends** it with a
new ability while keeping the read-only + cap-gated guarantees.

## Pitfalls

- **Never** register a write ability without a real `permission_callback` — `readonly: true` is an annotation,
  not enforcement.
- Keep `execute_callback` cheap and side-effect-free for read abilities (an agent may call it often).
- The provider must stay `function_exists`-guarded so the site never fatals on a core without the Abilities API.

## See also

- [Headless Corex](./headless.md) · the generated `AbilitiesProvider` / `CorexAbilities` references in docs-app.
