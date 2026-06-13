<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * The read-only description of what applying a kit *would* do, shown in the activation prompt before any change
 * (spec 042): the per-page dispositions (the same {@see PagePlanner} classification a real apply uses, computed
 * with no writes), the slug that would become the front page, and the modules/flags that would be enabled.
 * Immutable.
 */
final class ApplyPreview
{
    /**
     * @param list<PageDisposition> $pages
     * @param list<string>          $modules
     * @param list<string>          $flags
     */
    public function __construct(
        public readonly string $kit,
        public readonly array $pages,
        public readonly ?string $frontTargetSlug,
        public readonly array $modules,
        public readonly array $flags,
    ) {
    }
}
