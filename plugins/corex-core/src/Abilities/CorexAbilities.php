<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Abilities;

defined('ABSPATH') || exit;

/**
 * The data behind the Corex WP 7.0 "abilities" — read-only views of what a Corex site
 * exposes, for agent/MCP discovery. Pure: each method takes plain inputs and returns
 * plain arrays (no WordPress), so the ability logic is unit-testable; the provider wraps
 * these as `execute_callback`s with the real registries.
 */
final class CorexAbilities
{
    /**
     * The registered `corex/*` blocks as {name, title} rows.
     *
     * @param array<string,object> $registeredBlocks  name => WP_Block_Type-like (has ->title)
     *
     * @return list<array{name:string,title:string}>
     */
    public function blocks(array $registeredBlocks): array
    {
        $blocks = [];

        foreach ($registeredBlocks as $name => $type) {
            if (! is_string($name) || ! str_starts_with($name, 'corex/')) {
                continue;
            }

            $blocks[] = [
                'name'  => $name,
                'title' => isset($type->title) ? (string) $type->title : $name,
            ];
        }

        return $blocks;
    }

    /**
     * A small read-only site/framework summary.
     *
     * @return array{name:string,framework:string,version:string,corex_blocks:int}
     */
    public function siteInfo(string $siteName, string $version, int $corexBlockCount): array
    {
        return [
            'name'         => $siteName,
            'framework'    => 'Corex',
            'version'      => $version,
            'corex_blocks' => $corexBlockCount,
        ];
    }
}
