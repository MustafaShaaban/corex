<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

use Corex\Ui\Patterns\PatternLibrary;

/**
 * The machine-readable catalog of what the UI library provides — the dynamic block
 * names (read from the actual block.json files, so it cannot drift) and the section
 * patterns with their category. Consumed by kits/tooling (spec 010) for discovery.
 * Pure (filesystem read only; no WordPress).
 */
final class UiManifest
{
    public function __construct(
        private readonly PatternLibrary $patterns,
        private readonly string $blocksDir,
    ) {
    }

    /**
     * @return array{blocks:list<string>,patterns:list<array{name:string,category:string}>}
     */
    public function describe(): array
    {
        $patterns = [];
        foreach ($this->patterns->patterns() as $pattern) {
            $patterns[] = ['name' => $pattern['name'], 'category' => PatternLibrary::CATEGORY];
        }

        return [
            'blocks'   => $this->blockNames(),
            'patterns' => $patterns,
        ];
    }

    /**
     * @return list<string>
     */
    private function blockNames(): array
    {
        $names = [];

        foreach (glob($this->blocksDir . '/*/block.json') ?: [] as $file) {
            $json = json_decode((string) file_get_contents($file), true);

            if (is_array($json) && isset($json['name'])) {
                $names[] = (string) $json['name'];
            }
        }

        sort($names);

        return $names;
    }
}
