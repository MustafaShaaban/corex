<?php

/**
 * The one date/time config boundary (spec 076, T015 / FR-008).
 *
 * The requirement is not merely "the config reaches the browser" — it is that it reaches it from
 * ONE place. The codebase already demonstrates the failure mode this guards against: eight screens
 * each call `wp_localize_script` with their own object (`corexAccess`, `corexBlogPro`,
 * `corexDataModels`, `corexEmailStudio`, `corexFlows`, `corexInsights`, `corexSecurity`,
 * `corexSubmissions`). Timezone config added to eight payloads is eight things to keep in step.
 *
 * @package Corex\Tests\Integration\AdminUi
 */

declare(strict_types=1);

use Corex\Config\AdminUi\CorexAdminAssets;
use Corex\Support\DateTime\AdminDateTimeFormatter;

beforeEach(function () {
    // Constructed with the real presenter, as the container builds it. The constructor argument is
    // nullable so a site missing the binding loses dates rather than its admin shell — which means
    // a test that forgot to pass one would silently assert nothing here.
    $this->assets = new CorexAdminAssets(new AdminDateTimeFormatter());
});

it('recognises every CoreX screen and no others', function (string $hook, bool $expected) {
    expect($this->assets->supports($hook))->toBe($expected);
})->with([
    'the toplevel Overview'   => ['toplevel_page_corex-settings', true],
    'a submenu screen'        => ['corex_page_corex-submissions', true],
    'the other submenu shape' => ['corex-framework_page_corex-operations-security', true],
    'a declarative option page' => ['corex_page_corex-page-brand', true],
    'the wp-admin dashboard'  => ['index.php', false],
    'wp-admin plugins'        => ['plugins.php', false],
    'another plugin'          => ['toplevel_page_woocommerce', false],
]);

it('localizes the date config exactly once, onto the handle every screen bundle depends on', function () {
    wp_register_script('corex-runtime', 'https://example.test/runtime.js', [], '1.0', true);

    $this->assets->enqueue('toplevel_page_corex-settings');

    $scripts = wp_scripts();
    $data    = $scripts->get_data('corex-runtime', 'data');

    expect($data)->toBeString()
        ->and($data)->toContain('corexDateTime')
        // Once. `wp_localize_script` appends, so a second caller would produce a second
        // `var corexDateTime` and the last one silently wins.
        ->and(substr_count($data, 'var corexDateTime'))->toBe(1);
});

it('does not localize the config on a screen that is not ours', function () {
    wp_deregister_script('corex-runtime');
    wp_register_script('corex-runtime', 'https://example.test/runtime.js', [], '1.0', true);

    $this->assets->enqueue('index.php');

    expect(wp_scripts()->get_data('corex-runtime', 'data'))->toBeFalse();
});

it('skips the config rather than the whole shell when the binding is absent', function () {
    wp_deregister_script('corex-runtime');
    wp_register_script('corex-runtime', 'https://example.test/runtime.js', [], '1.0', true);
    // Registered here because the provider that normally registers it has not run in this test,
    // and `wp_enqueue_style` on an unregistered handle is a no-op — the assertion below would
    // then pass or fail for a reason that has nothing to do with the date config.
    wp_register_style('corex-admin-shell', 'https://example.test/shell.css', [], '1.0');

    (new CorexAdminAssets())->enqueue('toplevel_page_corex-settings');

    // A missing date presenter should cost dates, not the admin shell.
    expect(wp_style_is('corex-admin-shell', 'enqueued'))->toBeTrue()
        ->and(wp_scripts()->get_data('corex-runtime', 'data'))->toBeFalse();
});

it('carries everything the browser needs to format without asking its own platform', function () {
    $config = (new AdminDateTimeFormatter())->clientConfig();

    // Each of these is something the browser would otherwise have to source from `Intl`, which
    // reads a different dictionary than WordPress does.
    expect($config)->toHaveKeys([
        'timezone',
        'locale',
        'months',
        'monthsShort',
        'meridiem',
        'patterns',
        'relative',
        'absent',
    ])
        ->and($config['months'])->toHaveCount(12)
        ->and($config['patterns'])->toHaveKeys(['date', 'time', 'exactTime', 'connector']);
});
