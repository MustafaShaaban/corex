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

    /**
     * @return list<string> feature-flag slugs the kit needs enabled (e.g. woocommerce_kit)
     */
    public function featureFlags(): array
    {
        return [];
    }

    /**
     * The pages the kit creates when applied — each composing the kit's existing corex/*
     * patterns/blocks. One page may be marked `front` to become the static front page.
     * A subclass MAY accept a demo `$level` (minimal|standard|full, FR-137) to narrow the set
     * (see CompanyBlueprint); kits without levels keep this no-arg signature and ignore it.
     * Default: none (a kit that ships templates but seeds no pages).
     *
     * @return list<array{title:string,slug:string,content:string,front?:bool}>
     */
    public function pages(): array
    {
        return [];
    }
}
