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
 * Checks that the uploads directory is writable — media uploads, generated assets, and many add-on
 * features depend on it. A non-writable uploads dir is critical: it breaks core editing workflows.
 */
final class UploadsWritableProbe implements HealthProbe
{
    public function __construct(private readonly bool $writable)
    {
    }

    public function run(): ProbeResult
    {
        return new ProbeResult(
            $this->writable ? HealthStatus::Good : HealthStatus::Critical,
            'uploads_writable',
            __('Uploads writable', 'corex'),
            $this->writable
                ? __('The uploads directory is writable.', 'corex')
                : __('The uploads directory is not writable — media uploads will fail.', 'corex'),
            $this->writable ? [] : [__('Fix the uploads directory permissions (typically 755) and its owner.', 'corex')],
        );
    }
}
