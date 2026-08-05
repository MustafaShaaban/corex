<?php

/**
 * Plugin Name: CoreX E2E client guide
 * Description: Stands in for a client plugin registering a guide, so the browser suite proves the public seam rather than a CoreX guide.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * A guide contributed the way a site plugin contributes one (spec 097, FR-008).
 *
 * Through `corex_guides` rather than the container, because that is the harder half of the contract:
 * the filter is the seam for a plugin that never sees Corex's container, it runs after every
 * deferred factory has resolved, and it is the one a site developer copying the documentation will
 * reach for first.
 *
 * An mu-plugin so it is active from the moment WordPress loads, with no activation step for a
 * provisioning script to forget. Copied into `wp/wp-content/mu-plugins/` by
 * `.github/workflows/ci.yml` and by `scripts/setup-wordpress.ps1`.
 */
add_filter('corex_guides', static function (array $guides): array {
    if (! class_exists(\Corex\Guides\Guide::class)) {
        return $guides;
    }

    $guides[] = \Corex\Guides\Guide::for('e2e-client-guide', 'Client plugin guide')
        ->withSummary('Registered by a plugin CoreX has never heard of.')
        ->inSection('content')
        // A native screen, on purpose: it proves the declared address is rendered as a link to
        // wherever the guide says, not merely to CoreX's own pages.
        ->onScreen('upload.php')
        ->withTopic(\Corex\Guides\GuideTopic::for(
            'client-topic',
            'Something only this site can do',
            '',
            [
                new \Corex\Guides\GuideStep(
                    'Open the Media library.',
                    'The library lists this site’s uploads.',
                ),
            ],
        ));

    return $guides;
});
