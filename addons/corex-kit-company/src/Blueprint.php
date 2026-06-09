<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * A starter-kit manifest: what the kit provides and what it needs. Read-only and
 * pure — a kit composes existing modules (presentation), it carries no behavior.
 */
abstract class Blueprint
{
    abstract public function name(): string;

    /**
     * @return list<string> module slugs the kit needs (e.g. corex-ui)
     */
    abstract public function requiredModules(): array;

    /**
     * @return list<string> module slugs that enhance the kit (e.g. corex-forms)
     */
    public function recommendedModules(): array
    {
        return [];
    }

    /**
     * @return list<string> FSE template slugs the kit relies on
     */
    abstract public function templates(): array;

    /**
     * @return list<string> FSE template-part slugs the kit relies on
     */
    abstract public function parts(): array;

    /**
     * @return list<string> block-pattern names the kit composes
     */
    public function patterns(): array
    {
        return [];
    }
}
