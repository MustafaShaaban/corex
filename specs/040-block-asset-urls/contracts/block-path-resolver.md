# Contract: BlockPathResolver, AssetUrlHealth, BlockAssetsProbe

The internal interfaces this feature exposes within the framework. No external/REST surface is added.

## `Corex\Blocks\BlockPathResolver` (pure)

```php
final class BlockPathResolver
{
    /**
     * Map a discovered block directory to its WP_PLUGIN_DIR-relative mount location.
     *
     * @param string                $blockDir   Absolute dir of the block (may be realpath-resolved
     *                                           outside the plugins dir).
     * @param string                $pluginsDir WP_PLUGIN_DIR, forward-slash normalized, no trailing slash.
     * @param array<string,string>  $mounts     realTargetPath => mountEntryName (see data-model.md).
     * @return string  The dir under $pluginsDir when mappable; the original $blockDir otherwise.
     */
    public function resolve(string $blockDir, string $pluginsDir, array $mounts): string;
}
```

**Guarantees**
- Already-under-`$pluginsDir` input → returned unchanged (FR-005 no-regression).
- Realpath-resolved input whose real target matches a mount → rebuilt under `$pluginsDir` (FR-001/FR-002).
- No match → original returned, never a fabricated path (FR-004).
- Idempotent; trailing-slash neutral; Windows separator/drive-case tolerant.

## `Corex\Blocks\PluginMountMap` (WP/filesystem boundary)

```php
final class PluginMountMap
{
    public function pluginsDir(): string;          // WP_PLUGIN_DIR normalized
    /** @return array<string,string> realTargetPath => entryName, memoized per request */
    public function mounts(): array;
}
```

Built from `WP_PLUGIN_DIR` via one directory scan + `realpath()` per entry. Injected into
`DynamicBlockRegistrar`. Headless tests stub `pluginsDir()`/`mounts()` (no real FS needed for the pure path).

## `DynamicBlockRegistrar::register()` change

Before `register_block_type($block['dir'], $args)`:

```php
$dir = $this->resolver->resolve(
    $block['dir'],
    $this->mountMap->pluginsDir(),
    $this->mountMap->mounts(),
);
$type = register_block_type($dir, $args);
```

Everything else (idempotency guard, render callback, script-translation wiring) is unchanged.

## `Corex\Health\AssetUrlHealth` (pure)

```php
final class AssetUrlHealth
{
    /** True when $url is a well-formed public asset URL under $pluginsUrlBase (no embedded FS path). */
    public function isHealthy(string $url, string $pluginsUrlBase): bool;
}
```

## `Corex\Health\Probes\BlockAssetsProbe` (pure, implements `HealthProbe`)

```php
final class BlockAssetsProbe implements HealthProbe
{
    /**
     * @param list<array{name:string, urls:list<string>}> $blocks  collected corex/* block asset URLs
     * @param string                                       $pluginsUrlBase
     */
    public function __construct(array $blocks, string $pluginsUrlBase);

    public function run(): ProbeResult;   // Critical (names bad blocks) | Good
}
```

**Guarantees**
- `Good` when every URL across every block is healthy (or there are no assets).
- `Critical` listing each block with at least one unhealthy URL (FR-006).
- Pure: judgement only; URL collection happens in `HealthModule` (the boundary).

## Test contract (Pest, headless)

- `BlockPathResolverTest`: realpath-resolved-outside path → output under plugins dir (regression, FR-007);
  already-under path → unchanged; no-match → original; idempotence; Windows separators.
- `AssetUrlHealthTest`: drive-letter URL → unhealthy; clean `…/wp-content/plugins/corex-ui/…` → healthy;
  empty → not counted; `..`/double-scheme → unhealthy.
- `BlockAssetsProbeTest`: all-healthy set → `Good`; one malformed → `Critical` naming that block.
