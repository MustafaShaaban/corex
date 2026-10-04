<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * What make:site knows about the repository a site is being generated into (spec 102): the
 * framework baseline to record, and the repository root when — and only when — the site sits at
 * `<root>/sites/<client>`. Without a root the client's CI workflow cannot be placed, because a
 * workflow runs from `.github/workflows/` at the repository root and nowhere else.
 */
final class SiteRepository
{
    public function __construct(
        public readonly FrameworkBaseline $baseline,
        public readonly ?string $root = null,
    ) {
    }

    public static function unknown(): self
    {
        return new self(FrameworkBaseline::unknown());
    }
}
