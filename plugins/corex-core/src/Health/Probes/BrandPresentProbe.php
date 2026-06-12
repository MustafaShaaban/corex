<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health\Probes;

defined('ABSPATH') || exit;

use Corex\Health\HealthProbe;
use Corex\Health\HealthStatus;
use Corex\Health\ProbeResult;

/**
 * Checks that a `brand.json` is present so the site has its own identity layer over the neutral
 * theme tokens. Absent is advisory (recommended), not critical — the theme tokens are a complete
 * default; a brand simply personalises them.
 */
final class BrandPresentProbe implements HealthProbe
{
    public function __construct(private readonly bool $present)
    {
    }

    public function run(): ProbeResult
    {
        return new ProbeResult(
            $this->present ? HealthStatus::Good : HealthStatus::Recommended,
            'brand_present',
            __('Brand tokens', 'corex'),
            $this->present
                ? __('A brand.json is present — the site has its own identity layer.', 'corex')
                : __('No brand.json found; the site is using the neutral default tokens.', 'corex'),
            $this->present ? [] : [__('Add a brand.json to personalise colours, fonts, and spacing.', 'corex')],
        );
    }
}
