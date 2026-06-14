<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

use Corex\Health\HealthProbe;
use Corex\Health\HealthStatus;
use Corex\Health\ProbeResult;

/**
 * An **advisory** health probe (spec 048) reporting the server's image-optimization support —
 * GD/Imagick, WebP, AVIF — into Site Health + `wp corex doctor`. Never critical: missing
 * support is `recommended` (the site still works, uploads are just unoptimized). Pure (the
 * capability is injected), so it is unit-tested headlessly.
 */
final class MediaImageProbe implements HealthProbe
{
    public function __construct(private readonly ImageCapability $capability)
    {
    }

    public function run(): ProbeResult
    {
        $detail = sprintf(
            /* translators: 1: GD yes/no, 2: Imagick yes/no, 3: WebP yes/no, 4: AVIF yes/no */
            __('GD: %1$s · Imagick: %2$s · WebP: %3$s · AVIF: %4$s', 'corex'),
            $this->yesNo($this->capability->gd),
            $this->yesNo($this->capability->imagick),
            $this->yesNo($this->capability->webp),
            $this->yesNo($this->capability->avif),
        );

        if ($this->capability->canWebp()) {
            return new ProbeResult(HealthStatus::Good, 'corex_media_images', __('Image optimization', 'corex'), $detail);
        }

        return new ProbeResult(
            HealthStatus::Recommended,
            'corex_media_images',
            __('Image optimization', 'corex'),
            $detail,
            [__('Enable the GD or Imagick PHP extension with WebP support to serve smaller images.', 'corex')],
        );
    }

    private function yesNo(bool $value): string
    {
        return $value ? __('yes', 'corex') : __('no', 'corex');
    }
}
