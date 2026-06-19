# Contract: Metadata Consistency Check

## Purpose

Verify that release and package metadata agree or report exact mismatches.

## Surfaces

- `package.json`
- `composer.json`
- Plugin and add-on headers
- `COREX_*_VERSION` constants
- `Update URI` headers
- `README.md`
- `CHANGELOG.md`
- `PROGRESS.md`
- docs references under `docs/` and `docs-app/`
- Git tag/release value when available

## Result Shape

```json
{
  "status": "fail",
  "expected": "0.26.1",
  "mismatches": [
    {
      "path": "package.json",
      "field": "version",
      "actual": "0.1.0",
      "policy": "report-or-ignore-explicitly"
    }
  ]
}
```

## Rules

- A mismatch must include path, field, expected value, and actual value.
- Policy exceptions are explicit; no silent ignore.
- The check does not write files unless a separate task/command explicitly applies a version plan.
- README/CHANGELOG/PROGRESS checks distinguish current-release claims from historical entries.

## Required Tests

- All surfaces matching returns pass
- Header mismatch returns exact path/value
- Constant mismatch returns exact path/value
- README/CHANGELOG mismatch reports the correct narrative surface
- Policy exception is visible in output
