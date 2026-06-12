<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a custom admin option page definition — an `OptionPage` (title, menu, capability,
 * fields) the developer registers with the `OptionPageRegistry` (spec 039). The page renders +
 * saves through the shared settings form, so the scaffold is a definition, not form/save code.
 */
final class OptionPageGenerator extends Generator
{
    public function stub(): string
    {
        return 'option-page';
    }

    public function suffix(): string
    {
        return '';
    }

    public function subPath(): string
    {
        return 'Options';
    }

    /**
     * @return array<string,string>
     */
    public function placeholders(string $className, GeneratorContext $context): array
    {
        // CamelCase → snake_case slug (Billing → billing, TaxSettings → tax_settings).
        $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        return [
            'class'     => $className,
            'namespace' => $context->namespace . '\\Options',
            'slug'      => $slug,
            'title'     => $className,
        ];
    }
}
