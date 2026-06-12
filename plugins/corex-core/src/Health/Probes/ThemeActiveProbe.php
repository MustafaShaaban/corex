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
 * Checks that a block (FSE) theme is active — Corex's tokens, patterns, and blocks are built for
 * full-site editing. A classic theme is not a hard failure (Principle IX) but is recommended
 * against, since the token + pattern layer will not fully apply.
 */
final class ThemeActiveProbe implements HealthProbe
{
    public function __construct(private readonly bool $isBlockTheme)
    {
    }

    public function run(): ProbeResult
    {
        return new ProbeResult(
            $this->isBlockTheme ? HealthStatus::Good : HealthStatus::Recommended,
            'theme_active',
            __('Block theme active', 'corex'),
            $this->isBlockTheme
                ? __('A block (FSE) theme is active — Corex tokens and patterns apply.', 'corex')
                : __('A classic theme is active; Corex is designed for block (FSE) themes.', 'corex'),
            $this->isBlockTheme ? [] : [__('Activate a block theme (the Corex starter theme, or any FSE theme).', 'corex')],
        );
    }
}
