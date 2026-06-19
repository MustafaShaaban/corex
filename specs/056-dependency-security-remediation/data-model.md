# Data Model: Dependency Security Remediation

## Dependency Finding

- `ecosystem`: `composer`, `npm-root`, or `npm-docs`
- `advisoryId`: stable advisory identifier
- `package`: affected package name
- `severity`: `low`, `moderate`, `high`, or `critical`
- `paths`: one or more dependency paths reported by the audit source
- `exposure`: `shipped-runtime`, `ci`, `local-dev-server`, `build-test-transitive`, or `unreachable`
- `status`: `remediated`, `excepted`, `unbounded`, or `unavailable`

Validation rules:

- Advisory identity is unique within an ecosystem.
- High or critical findings cannot be excepted for `shipped-runtime` or `ci` exposure.
- A finding not present in policy is `unbounded` and fails verification.

## Risk Exception

- `ecosystem`: audit source the exception applies to
- `advisoryId`: exact advisory identifier
- `package`: expected affected package
- `severityCeiling`: maximum accepted severity
- `exposure`: allowed non-runtime/non-CI exposure class
- `reason`: Corex-specific reachability explanation
- `control`: compensating control
- `owner`: responsible maintainer or upstream project
- `reviewAfter`: ISO date after which validation fails
- `upstreamTrigger`: concrete version, dependency release, or removal condition that ends the exception

Validation rules:

- All fields are required and non-empty.
- `reviewAfter` must be a valid future date when verified.
- `severityCeiling` must not be lower than the current advisory severity.
- Stale exceptions for advisories no longer reported fail validation so the policy is cleaned up.

## Audit Snapshot

- `ecosystem`: one of the three required audit surfaces
- `generatedAt`: ISO timestamp
- `status`: `ready` or `unavailable`
- `findings`: normalized dependency findings
- `sourceExitCode`: audit command exit code
- `sourceError`: present only for unavailable results

## Policy Evaluation

- `status`: `pass`, `fail`, or `unavailable`
- `unbounded`: findings without valid exceptions
- `expired`: matching exceptions past review date
- `stale`: exceptions whose advisory is absent
- `forbidden`: high/critical runtime or CI exceptions
- `accepted`: valid bounded development-tool exceptions

State transition:

`reported -> remediated` when the advisory disappears after a compatible update.

`reported -> excepted` only after all exception fields validate.

`excepted -> unbounded` when severity/exposure changes, review date passes, or policy metadata no longer matches.
