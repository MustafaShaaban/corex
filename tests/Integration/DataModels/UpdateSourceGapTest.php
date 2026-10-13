<?php

/**
 * Integration test: a site that has named no update source is not told it has something to finish.
 *
 * No update source is what CoreX ships with, and the admin listed it as a gap on every site, with
 * a button to a Settings screen that has no field for it (read 2026-10-13, DECISIONS #363, and
 * then run). The list is for what was chosen and left half done; a gap nobody can close kept it
 * from ever being empty.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\DataModels\CapabilityFacts;

it('lists nothing to finish on a site that chose no captcha provider and named no update source', function () {
    // Both settings are answered by a filter, so the install's own are neither read nor changed.
    // Answering with the caller's default is how an option reads when no row holds it.
    $noProvider = static fn (): string => 'none';
    $notStored  = static fn (mixed $pre, string $option, mixed $default): mixed => $default;
    add_filter('pre_option_corex_captcha_driver', $noProvider);
    add_filter('pre_option_corex_updates_endpoint', $notStored, 10, 3);

    try {
        $facts = (new CapabilityFacts(Boot::app()->container()))->gather([]);
    } finally {
        remove_filter('pre_option_corex_captcha_driver', $noProvider);
        remove_filter('pre_option_corex_updates_endpoint', $notStored, 10);
    }

    expect($facts['gaps'])->toBe([]);
});
