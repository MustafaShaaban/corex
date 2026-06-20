# Spec 057 Font Evidence

Captured: 2026-06-20 EEST

| Check | Result | Evidence |
|---|---|---|
| Self-hosted WOFF2 count | PASS | Four files: two bounded Latin variable subsets and Arabic 400/600 |
| External font CDN | PASS | None referenced by the theme or style variations |
| Readable fallback stacks | PASS | System body, sans-serif heading/Arabic, and monospace technical fallbacks |
| `font-display: swap` | PASS | All four WordPress font faces and manifest records use `swap` |
| Provenance/checksums/licenses/subsets | PASS | Pinned Google Fonts commit, local OFL files, subset ranges, and SHA-256 records verified |
| Preloads | PASS | None added; no measured-benefit record exists |
| Browser font network requests | ENVIRONMENT-GATED | Chromium cannot resolve the local `corex-spec057.local` hostname (`ERR_NAME_NOT_RESOLVED`); no rendered-network PASS is claimed |
| Built WordPress font integration | PASS | WordPress 7.0 `WP_Font_Face_Resolver` resolves all base and style-variation faces to the four local CoreX theme URLs with the approved weights and `swap` |

The owner approved integration on 2026-06-20. Sources are pinned to Google Fonts commit
`cf28404eac0c6f9753bef3510bbe271952e4154d`; subsetting used fonttools 4.63.0 and Brotli 1.2.0.

## Guard review

- `clean-code-guard`: PASS; the batch adds only the four required assets, one provenance authority, and direct
  WordPress-native mappings without speculative loaders or abstractions.
- `wp-guard`: PASS; `WP_Font_Face_Resolver` confirms the supported `theme.json` contract, and no enqueue, hook,
  request, output, query, or client-brand behavior changed.
- `test-guard`: PASS; the contract asserts observable file/provenance/checksum and WordPress mapping behavior with
  no mocks or duplicated framework-only cases.
- `docs-guard`: PASS; task state, source commit, tool versions, checksums, WordPress evidence, and browser gate were
  checked against the changed files and fresh command output.
