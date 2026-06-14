# Contract: `make:site --starter` / `--minimal` (US4)

Extends the existing pure scaffolder. Render-all-before-write is preserved; no WordPress runtime touched.

## CLI surface

```
wp corex make:site <Name> [--starter] [--minimal] [--plugin-only] [--theme-only] [--force] [--path=<dir>]
```

| Flag | Effect |
|---|---|
| (none) | plugin + theme + governance scaffold, **no** example slice |
| `--starter` | the above **plus** the example vertical slice + starter-theme asset architecture |
| `--minimal` | explicit "no slice" (same as default; documents intent) |
| `--plugin-only` / `--theme-only` | restrict to one artifact (existing) |
| `--force` | overwrite existing files (existing) |
| `--path` | output dir (existing; default `cwd()/sanitize_title(Name)`) |

## `SiteScaffolder::scaffold()` contract

- Signature unchanged: `scaffold(string $rawName, string $outputDir, array $options): SiteScaffoldResult`.
- New option key `starter` (bool). When true, add to the render map:
  - the StarterSlice files (model/repository/service/controller-on-envelope/block+renderer/option-page/test/
    `REMOVE-EXAMPLE.md`) under the client plugin, client-namespaced via `SiteIdentity`;
  - the StarterTheme asset architecture (`assets/src/*`, build scripts, `inc/Assets.php`, extra templates/parts)
    under the client theme.
- Default / `starter=false` / `--minimal` → today's lean output (empty `.gitkeep` folders), unchanged.
- All generation stays render-all-before-write: an unresolved placeholder fails loudly with nothing written.
- Idempotent without `--force` (marker file check unchanged).

## `MakeCommand::runSite()` contract

- Parse `--starter` and `--minimal` into `$options`:
  `starter => (bool)($assoc['starter'] ?? false) && ! ($assoc['minimal'] ?? false)`.
- Report each created file; on success print the "edit only the client plugin/theme" + "see REMOVE-EXAMPLE.md"
  guidance.

## Generated-output invariants

- Every generated PHP passes `php -l`.
- Identifiers are client-namespaced and distinct from `Corex\` (`SiteIdentity` guard; a name normalizing to
  `corex` is refused).
- The controller uses the spec-043 response envelope; the block is dynamic, token-only, RTL; the theme is a skin
  (no business logic), tokens consumed at runtime, assets conditional, dev-only source maps, minified prod,
  hashed `*.asset.php` cache-busting, and an `Assets` url/path/version helper.

## Test contract (Pest)

`tests/Unit/Cli/SiteScaffolderStarterTest.php`:
- `--starter` emits the slice + theme assets; every generated `.php` passes `php -l`.
- default and `--minimal` **omit** the slice (only the lean scaffold).
- idempotent without `--force`; a reserved name (`corex`) is refused.
- `REMOVE-EXAMPLE.md` is present and names the slice files.
