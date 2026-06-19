# Contract: Logo and Font Assets

## Production logo package gate

Logo implementation is blocked until the owner supplies or approves a package that records:

- source location and package version/date;
- author/owner and license or usage rights;
- explicit owner approval date;
- symbol, wordmark, lockup, monochrome, and contrast variants;
- stable filenames and viewBox values;
- clear-space and minimum-size guidance;
- dark/light/background usage;
- accessible-name versus decorative usage; and
- rollback mapping from the current default asset.

All variants are optimized SVG. No embedded raster image, unapproved external URL, executable script, or required
font text element is allowed. The existing navy/cyan `plugins/corex-config/assets/corex-logo.svg` is migration
evidence only and cannot satisfy this gate.

## Font package

| Role | Family | Files | Weight | Subset | Loading |
|---|---|---:|---|---|---|
| Display/headings | Space Grotesk | 1 | Variable 500–700 | Latin | WOFF2, `swap` |
| Code/technical | JetBrains Mono | 1 | Variable 400–600 | Latin | WOFF2, `swap` |
| Arabic regular | IBM Plex Sans Arabic | 1 | 400 | Arabic | WOFF2, `swap` |
| Arabic emphasis | IBM Plex Sans Arabic | 1 | 600 | Arabic | WOFF2, `swap` |

Contract rules:

- Maximum four self-hosted WOFF2 files.
- Latin body/interface content uses the system stack and adds no font file.
- Each file records upstream source, license, subset method/source, checksum, and covered weights/scripts.
- No preload by default. Any preload records before/after evidence and unused-preload inspection.
- Fallbacks preserve readable content while fonts load or fail.
- If upstream does not provide an approved variable/subset form, the replacement package must use no more files and
  no broader weights/scripts, with the deviation recorded before tasks proceed.

## Asset acceptance

- Paths are repository-relative and stable.
- Assets do not add global JavaScript, a CDN dependency, or an icon font.
- Fonts and logos can be removed during rollback without data or boot changes.
- Logo assets are CoreX product assets and are not forced into client-site identity.
