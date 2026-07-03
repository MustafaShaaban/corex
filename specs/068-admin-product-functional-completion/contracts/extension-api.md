# Extension API Contract

These contracts keep CoreX extensible without hard dependencies. Exact PHP signatures are finalized in task-level tests before implementation.

## Forms and Flows

- `register_field_type(key, definition)` registers renderer, sanitizer, schema, and editor metadata.
- `register_validation(key, rule)` registers server validation and optional editor configuration.
- `register_flow_action(key, action)` registers an ordered post-validation pipeline action returning a typed result.
- `register_routing_rule(key, rule)` registers condition metadata and a resolver.
- `register_email_variable(key, resolver)` registers safe variable schema and resolution.
- `register_success_state(key, handler)` registers validation, preview, and visitor response behavior.

Registration rejects duplicate keys and invalid definitions. Extensions cannot bypass authorization, sanitation, versioning, timeline, or activity contracts.

## Data Sources

A source registers:

- identity and label
- field schema and personal-data classes
- capability set
- read/query/detail adapter
- optional create/update/delete/bulk adapter
- optional import/export adapter
- optional migration provider
- permission map

Consumers render only declared capabilities. Adapters return typed results and never echo or terminate requests.

## Insights

An insight provider registers:

- provider identity/label
- connection-state resolver
- setup URL/action
- run operation returning a normalized result
- result schema and recommendation mapper
- environment constraints

Providers never expose secrets in state or result payloads.

## Activity

Domains publish a typed event only after the authoritative outcome is known. Required fields are event kind, area, actor, target, outcome, safe context, and retention/sensitivity classification. Event kind registrations define label rendering and redaction.

## Abilities

Plugins/add-ons register grouped `corex_*` ability definitions. Definitions may be code/config locked and may imply lower-risk abilities. Registration cannot grant an ability; grants are owned by the access service.

## Email

Email providers implement environment-aware delivery and return a typed attempt outcome. Template variable resolvers declare input schema and safe rendered type. Delivery providers never receive unresolved template expressions.

## Compatibility

- Missing optional extensions leave the product operational with truthful dependency/setup states.
- Public extension keys remain stable once released.
- Versioned contracts add fields compatibly and reject unsupported major versions clearly.
