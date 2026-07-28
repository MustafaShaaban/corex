<?php

/**
 * Corex Guides defaults.
 *
 * `support.email` is where "I could not find what I needed" goes. Change this one line to change
 * the address in code; a site owner can change the same value without opening PHP, in
 * CoreX Settings → Guides, which writes the `corex_guides_support_email` option that layers over
 * this default (spec 087, FR-002 / FR-003).
 *
 * `support.enabled` switches the panel off entirely. Off is not the same as unconfigured: an empty
 * address with the panel on renders an honest "support is not set up here" state rather than a form
 * that would discard what somebody typed.
 *
 * @package Corex\Guides
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'support' => [
        'email'   => 'Mustafashaaban22@gmail.com',
        'enabled' => true,
    ],
];
