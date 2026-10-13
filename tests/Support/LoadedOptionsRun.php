<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Boot;
use Corex\Support\Options\LoadedOptions;

/**
 * Runs the turn-over of CoreX's loaded options as a cron request does. The integration suite's
 * own process is neither an admin nor a cron request, and the turn-over writes on no other.
 */
final class LoadedOptionsRun
{
    /**
     * @param LoadedOptions|null $options The site's own registry when none is given: what CoreX's
     *                                    providers registered while they booted.
     */
    public static function onACronRequest(?LoadedOptions $options = null): void
    {
        $options ??= Boot::app()->container()->make(LoadedOptions::class);

        add_filter('wp_doing_cron', '__return_true');

        try {
            $options->ensureLoaded();
        } finally {
            remove_filter('wp_doing_cron', '__return_true');
        }
    }
}
