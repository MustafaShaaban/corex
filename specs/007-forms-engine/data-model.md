# Phase 1 Data Model: Forms Engine

Code-defined schemas + pure value objects + one persisted entity (`corex_submission`).

## Entity map

```text
Form (abstract, code)  ── slug, fields[], listeners[]
  └─ SchemaResolver → FieldSchema[] (canonical; dup-name/unknown-rule rejected)
        └─ Validator(values) → ValidationResult (bail per field)

POST corex/v1/forms/{slug}
  SubmitController → Request → Pipeline(nonce, sanitize, throttle)
    → FormSubmissionService: honeypot? → validate → on ok dispatch
        EventDispatcher.dispatch(FormSubmittedEvent{slug, values})
          → StoreSubmissionListener → corex_submission (CPT, via data layer)
          → SendEmailListener      → wp_mail(recipient)
```

## 1. Form *(FR-001)*

- Abstract base. A concrete form declares: `string $slug`, `array $fields`
  (`name => ['type' => string, 'rules' => list<string>, 'label' => string]`), and
  `array $listeners` (event class → list of listener service ids, or the form's default set).
- Read-only definition; the single source feeding the resolver, the endpoint, and the block.

## 2. FieldSchema *(FR-005)* — pure value object

- Normalized field: `name`, `type`, `label`, `rules` (list of `{rule, params}`), `required` (derived).
- Produced by `SchemaResolver`. Resolution **rejects** duplicate field names and unknown rule names
  (fails closed, surfaced to the developer).

## 3. Validation Rule + RuleRegistry *(FR-003)* — pure

- `Rule::validate(mixed $value, array $params, array $allValues): ?string` — returns an i18n message
  **key** (e.g. `email`, `max`) on failure, or `null` on pass. Stateless.
- v1 rules: `Required`, `Email`, `Max` (param N — string length / numeric ≤), `Min` (param N),
  `Numeric`. `RuleRegistry` maps name → Rule and parses `name:param`.

## 4. ValidationResult *(FR-002)* — pure value object

- `bool valid`, `array<string,string> errors` (field → single message key, **bail per field**),
  `array<string,mixed> values` (normalized). `valid === (errors === [])`.

## 5. Event + EventDispatcher + ListenerProvider *(FR-006, FR-007, FR-012a)* — pure (corex-core)

- `Event` — marker interface for an immutable event object.
- `ListenerProvider::listen(string $eventClass, callable $listener)` + `listenersFor(object $event): iterable`.
- `EventDispatcher::dispatch(object $event): object` — invokes each listener for the event's class in
  registration order, exactly once; a throwing listener is caught + logged (`BootLogger`), the rest run.

## 6. FormSubmittedEvent *(FR-008)* — immutable

- `string $formSlug`, `array<string,mixed> $values` (validated/normalized). Implements `Event`.

## 7. Submission (`corex_submission`) *(FR-012)* — persisted

- A non-public custom post type. One submission = one post: title = `{slug} — {timestamp}`,
  validated values stored as post meta (prefixed `corex_field_*`). Persisted by
  `StoreSubmissionListener` via the spec-002 data layer; retrievable/queryable by form slug.
  Admin viewer out of scope.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| value fails a rule | one error/field (first failing rule), no side effect | FR-002, FR-011 |
| missing/invalid nonce | nonce middleware rejects (fail-closed), no dispatch | FR-009 |
| honeypot filled | service rejects silently, no side effect | FR-010 |
| over rate limit | throttle middleware rejects | FR-009 |
| unknown form slug | non-fatal rejection (submit) / editor notice (block) | FR-018 |
| unknown rule / dup field | SchemaResolver throws at resolution (developer-visible) | FR-005, FR-018 |
| a listener throws | logged; remaining listeners run; submission still accepted | FR-012a |
| field not in schema | ignored (not validated, not stored) | FR-002 |
