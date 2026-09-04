<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Multisite\ActivationScope;

final class AddonExclusion
{
    public function __construct(
        public readonly string $slug,
        public readonly AddonBlockReason $reason,
        public readonly ActivationScope $scope,
        public readonly int $siteId,
        public readonly string $detail = '',
    ) {
    }

    public function message(): string
    {
        $detail = $this->detail === ''
            ? ''
            : sprintf(
                /* translators: %s: dependency, feature flag, or external gate identifier. */
                __(' (%s)', 'corex'),
                $this->detail,
            );

        return sprintf(
            /* translators: 1: add-on slug, 2: site ID, 3: activation scope, 4: block reason, 5: optional detail. */
            __('Add-on %1$s is excluded on site %2$d in the %3$s scope: %4$s%5$s.', 'corex'),
            $this->slug,
            $this->siteId,
            $this->scope->label(),
            $this->reason->label(),
            $detail,
        );
    }
}
