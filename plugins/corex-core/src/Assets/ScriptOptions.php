<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * The normalised options for enqueuing a script (spec 062) — dependencies, footer placement, a
 * loading strategy (defer/async), module type, and an explicit version. Pure: it merges a
 * WordPress `*.asset.php` build record (its `dependencies` + `version`) with caller options, the
 * caller winning on conflict. Unit-tested without WordPress; {@see Script} is the thin WP boundary.
 *
 * @phpstan-type RawOptions array{deps?:list<string>,in_footer?:bool,defer?:bool,async?:bool,module?:bool,strategy?:string,version?:string|null}
 */
final class ScriptOptions
{
    /**
     * @param list<string>  $deps
     * @param 'defer'|'async'|'' $strategy
     */
    public function __construct(
        public readonly array $deps,
        public readonly bool $inFooter,
        public readonly string $strategy,
        public readonly bool $module,
        public readonly ?string $version,
    ) {
    }

    /**
     * @param array<string,mixed>                                    $options caller options
     * @param array{dependencies?:list<string>,version?:string}|null $assetFile decoded *.asset.php (or null)
     */
    public static function from(array $options, ?array $assetFile = null): self
    {
        $assetDeps = isset($assetFile['dependencies']) && is_array($assetFile['dependencies'])
            ? array_map('strval', $assetFile['dependencies'])
            : [];
        $callerDeps = isset($options['deps']) && is_array($options['deps'])
            ? array_map('strval', $options['deps'])
            : [];
        $deps = array_values(array_unique([...$assetDeps, ...$callerDeps]));

        // Loading strategy: an explicit defer/async wins; `strategy` accepted directly too.
        $strategy = '';
        if (! empty($options['defer'])) {
            $strategy = 'defer';
        } elseif (! empty($options['async'])) {
            $strategy = 'async';
        } elseif (in_array($options['strategy'] ?? '', ['defer', 'async'], true)) {
            $strategy = (string) $options['strategy'];
        }

        // Version: caller override > asset-file version > null (the manager's resolved version).
        $version = null;
        if (array_key_exists('version', $options)) {
            $version = $options['version'] === null ? null : (string) $options['version'];
        } elseif (isset($assetFile['version'])) {
            $version = (string) $assetFile['version'];
        }

        return new self(
            $deps,
            (bool) ($options['in_footer'] ?? true),
            $strategy,
            ! empty($options['module']),
            $version,
        );
    }

    /**
     * The args array for `wp_enqueue_script`'s 5th parameter (WP 6.3+: `in_footer` + `strategy`).
     *
     * @return array{in_footer:bool,strategy?:string}
     */
    public function wpArgs(): array
    {
        $args = ['in_footer' => $this->inFooter];

        if ($this->strategy !== '') {
            $args['strategy'] = $this->strategy;
        }

        return $args;
    }
}
