# Phase 1 Contracts: Forms Engine

The stable public API. Signatures are the agreed shape; bodies live in `tasks.md` / implementation.

## C1 — EventDispatcher (corex-core, `Corex\Events`)

```php
namespace Corex\Events;

interface Event {}                                   // marker for an immutable event object

final class ListenerProvider
{
    /** @param callable(object):void $listener */
    public function listen(string $eventClass, callable $listener): void;

    /** @return list<callable> listeners registered for $event's class, in registration order */
    public function listenersFor(object $event): array;
}

final class EventDispatcher
{
    public function __construct(ListenerProvider $provider, \Corex\Support\BootLogger $logger) {}

    /** Invoke each listener once, in registration order; a throwing listener is logged, the rest run. */
    public function dispatch(object $event): object;   // returns $event
}
```

## C2 — Validation (`Corex\Forms\Validation`)

```php
namespace Corex\Forms\Validation;

interface Rule
{
    /** @return string|null an i18n message key on failure, null on pass */
    public function validate(mixed $value, array $params, array $allValues): ?string;
}

final class ValidationResult
{
    /** @param array<string,string> $errors field => single message key (bail per field) */
    public function __construct(public bool $valid, public array $errors, public array $values) {}
    public function isValid(): bool;
}

final class Validator
{
    public function __construct(RuleRegistry $rules) {}

    /**
     * @param array<string,\Corex\Forms\Schema\FieldSchema> $schema
     * @param array<string,mixed> $values
     */
    public function validate(array $schema, array $values): ValidationResult;
}
```

## C3 — SchemaResolver (`Corex\Forms\Schema`)

```php
namespace Corex\Forms\Schema;

final class FieldSchema
{
    /** @param list<array{rule:string,params:array}> $rules */
    public function __construct(
        public string $name, public string $type, public string $label,
        public array $rules, public bool $required,
    ) {}
}

final class SchemaResolver
{
    /**
     * @param array<string,array{type?:string,rules?:list<string>,label?:string}> $fields
     * @return array<string,FieldSchema>
     * @throws \InvalidArgumentException on duplicate field name or unknown rule
     */
    public function resolve(array $fields): array;
}
```

## C4 — Form + FormRegistry (`Corex\Forms`)

```php
namespace Corex\Forms;

abstract class Form
{
    public string $slug = '';
    /** @var array<string,array{type:string,rules:list<string>,label:string}> */
    protected array $fields = [];
    /** @return array<string,array{type:string,rules:list<string>,label:string}> */
    public function fields(): array;
    /** @return list<class-string> listener service ids for this form's submissions */
    public function listeners(): array;
}

final class FormRegistry
{
    public function register(Form $form): void;
    public function find(string $slug): ?Form;      // unknown slug → null (non-fatal)
    /** @return list<Form> */
    public function all(): array;
}
```

## C5 — Submission lifecycle (`Corex\Forms\Submission`)

```php
namespace Corex\Forms\Submission;

final class FormSubmittedEvent implements \Corex\Events\Event
{
    public function __construct(public string $formSlug, public array $values) {}
}

final class FormSubmissionService               // pure of WP; orchestrates honeypot+validate+dispatch
{
    public function __construct(
        FormRegistry $forms, \Corex\Forms\Schema\SchemaResolver $resolver,
        \Corex\Forms\Validation\Validator $validator, \Corex\Events\EventDispatcher $events,
    ) {}

    /**
     * @param array<string,mixed> $input  sanitized values (honeypot key included)
     * @return \Corex\Http\Middleware\Response  ok(values) | reject(reason,status)
     */
    public function handle(string $slug, array $input, string $honeypotKey = 'corex_hp'): \Corex\Http\Middleware\Response;
}

final class SubmitController                    // REST boundary
{
    public function register(): void;           // register_rest_route('corex/v1','/forms/(?P<slug>[a-z0-9-]+)', …)
    public function submit(\WP_REST_Request $request): \WP_REST_Response;  // build Request → Pipeline → service
}
```

## C6 — Listeners (`Corex\Forms\Listeners`) — boundaries

```php
namespace Corex\Forms\Listeners;

final class StoreSubmissionListener   // __invoke(FormSubmittedEvent): void — persists corex_submission
final class SendEmailListener         // __invoke(FormSubmittedEvent): void — wp_mail(recipient)
```

## C7 — Block (`Corex\Forms\Block`)

```php
namespace Corex\Forms\Block;

final class FormBlockRenderer
{
    public function __construct(FormRegistry $forms, \Corex\Forms\Schema\SchemaResolver $resolver) {}
    /** @param array{formSlug?:string} $attributes */
    public function render(array $attributes): string;   // accessible, token-styled, nonce + honeypot
}
// blocks/corex-form/block.json declares the view script/style → conditional (per-block) load.
```

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 dispatch order | listeners run once each, registration order; other types untouched | FR-006, FR-007, SC-003 |
| C1 best-effort | a throwing listener is logged; the rest still run | FR-012a, SC-008 |
| C2 each rule | valid → no error; invalid → exact field error (bail per field) | FR-002/3, SC-002 |
| C3 resolve | dup name / unknown rule → throws; valid → FieldSchema set | FR-005 |
| C4 registry | unknown slug → null (non-fatal) | FR-018 |
| C5 service | honeypot/validation failure → reject + zero side effects; ok → dispatch | FR-008/10/11, SC-006 |
| C5 lifecycle (integration) | nonce/honeypot/validation gates; listeners observed on success | FR-009, SC-004 |
| C7 block | every field + label/required + nonce + honeypot; preset tokens only; script only when present | FR-013/14/15, SC-005 |
