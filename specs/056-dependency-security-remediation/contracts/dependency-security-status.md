# Contract: Dependency Security Status

The verifier prints a concise human summary and emits the following JSON shape when `--json` is supplied:

```json
{
  "status": "pass",
  "generatedAt": "2026-06-19T00:00:00.000Z",
  "ecosystems": [
    {
      "name": "npm-root",
      "status": "pass",
      "findingCount": 0,
      "acceptedExceptionCount": 0
    }
  ],
  "violations": []
}
```

Exit codes:

- `0`: all three audits ran and every finding is remediated or covered by a valid bounded exception;
- `1`: policy violation, including a new, expired, stale, metadata-mismatched, or forbidden exception;
- `2`: an audit command or advisory service was unavailable, or its output could not be parsed.

Human output names each ecosystem and reports `PASS`, `FAIL`, or `UNAVAILABLE`. It must never print `PASS` for an audit that did not run successfully.
