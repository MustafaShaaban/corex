# Phase 1 Contracts: Middleware + Security

The stable public API. Signatures are the agreed shape; implementation lives in `tasks.md`.

## C1 — Middleware + Request + Response

```php
namespace Corex\Http\Middleware;

interface Middleware
{
    /** @param callable(Request): Response $next */
    public function process(Request $request, callable $next): Response;
}

final class Request
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly string $method,
        public readonly array $input = [],
        public readonly string $nonce = '',
        public readonly string $nonceAction = '',
        public readonly string $throttleKey = '',
    ) {}

    /** @param array<string, mixed> $input */
    public function withInput(array $input): self;
}

final class Response
{
    public static function ok(mixed $value = null): self;
    public static function reject(string $reason, int $status = 403): self;
    public function isOk(): bool;
    public readonly string $status_reason; // reason
    public readonly int $status;
    public readonly mixed $value;
}
```

## C2 — Pipeline

```php
namespace Corex\Http\Middleware;

final class Pipeline
{
    public function __construct(private \Corex\Support\BootLogger $logger) {}

    /**
     * @param callable(Request): Response $handler
     */
    public function run(Request $request, callable $handler, Middleware ...$middleware): Response;
}
```

Guarantees: onion order; a short-circuit stops inner middleware + handler; empty list → handler; any
throw → `Response::reject` (logged).

## C3 — MiddlewareResolver

```php
namespace Corex\Http\Middleware;

final class MiddlewareResolver
{
    public function __construct(
        private \Corex\Container\ContainerInterface $container,
        private \Corex\Support\BootLogger $logger,
    ) {}

    public function resolve(string $name): Middleware;          // 'auth:manage_options'

    /** @param list<string> $names @return list<Middleware> */
    public function resolveAll(array $names): array;
}
```

Unknown alias → a `RejectingMiddleware` (fail closed, logged).

## C4 — The four middleware

```php
namespace Corex\Http\Middleware;

final class NonceMiddleware implements Middleware {}        // reject non-GET without a valid nonce
final class CapabilityMiddleware implements Middleware {    // reject !current_user_can($capability)
    public function __construct(private string $capability) {}
}
final class ThrottleMiddleware implements Middleware {}     // transient count vs config limit/window
final class SanitizeMiddleware implements Middleware {      // reduce input to the expected shape
    /** @param array<string, string> $shape  key => sanitizer */
    public function __construct(private array $shape) {}
}
```

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C2 order | middleware run outer→inner; handler reached when none reject | FR-002, SC-001 |
| C2 short-circuit | a rejecting middleware stops inner + handler | FR-003, FR-011 |
| C2 empty | handler runs directly | FR-004 |
| C2 throw | throw → reject, logged, handler side effect absent | FR-006, SC-004 |
| C3 alias+param | resolves the container alias with the parameter | FR-014 |
| C3 unknown | RejectingMiddleware (fail closed) | FR-015, SC-004 |
| C4 nonce | non-GET no/invalid nonce rejects; valid passes | FR-007 |
| C4 capability | !can → reject; can → pass | FR-008 |
| C4 throttle | over limit rejects; resets after window | FR-009 |
| C4 sanitize | handler sees only cleaned expected shape | FR-010 |
| SecurityModule | aliases resolvable by name | FR-016 |
