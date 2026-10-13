<?php

/**
 * Integration test: CoreX's boot does not ask the database, one option at a time, for a setting's
 * override that is not stored (DECISIONS #361).
 *
 * On a fresh site with no persistent object cache, CoreX's boot ran eleven queries on every
 * request and every one read an option that was not in the database. Ten of them were overrides
 * of settings: nine through the config engine, one for an add-on's feature flag.
 *
 * The test names the options it forbids and looks for nothing else, so a query somebody adds for
 * another reason does not fail it. Each request runs in a process of its own, because this one
 * booted long ago and its queries were not kept.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

/**
 * The options CoreX's boot asked for one at a time, by who asked.
 */
const COREX_OVERRIDES_BOOT_ASKED_FOR = [
    // Corex\Boot, for the WooCommerce kit's feature flag.
    'corex_features_woocommerce_kit',
    // The Insights registry, made with its screen.
    'corex_insights_psi_key',
    'corex_insights_cloudflare_token',
    'corex_insights_cloudflare_account_id',
    // Corex\Theme\ThemeServiceProvider.
    'corex_theme_brand_path',
    // Corex\Media\MediaSettings.
    'corex_media_webp_enabled',
    'corex_media_webp_quality',
    'corex_media_webp_convert_jpeg',
    'corex_media_webp_convert_png',
    'corex_media_webp_min_saving',
];

/**
 * Load WordPress in a process of its own and report the options its `plugins_loaded` asked the
 * database for one at a time.
 *
 * @return array{is_admin: bool, queries_heard: int, asked_one_at_a_time: list<string>, stored: list<string>, webp_quality: mixed}
 */
function optionQueriesOfOneBoot(bool $admin): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-request-') . '.php';
    file_put_contents($script, sprintf(
        <<<'PHP'
        <?php
        // Before WordPress loads, so that every query of the request is kept.
        define('SAVEQUERIES', true);

        if (%s) {
            define('WP_ADMIN', true);
        }

        // WordPress adopts hooks that were set before it loaded, which is the only way to stand
        // on either side of everything `plugins_loaded` runs.
        $window = ['from' => 0, 'to' => 0];
        $GLOBALS['wp_filter']['plugins_loaded'][PHP_INT_MIN]['corex_query_probe_opens'] = [
            'function' => static function () use (&$window): void {
                $window['from'] = count((array) $GLOBALS['wpdb']->queries);
            },
            'accepted_args' => 0,
        ];
        $GLOBALS['wp_filter']['plugins_loaded'][PHP_INT_MAX]['corex_query_probe_closes'] = [
            'function' => static function () use (&$window): void {
                $window['to'] = count((array) $GLOBALS['wpdb']->queries);
            },
            'accepted_args' => 0,
        ];

        require %s;

        $forbidden = %s;
        $asked = [];

        foreach (array_slice($wpdb->queries, $window['from'], $window['to'] - $window['from']) as $query) {
            if (preg_match('/WHERE option_name = \'([^\']+)\' LIMIT 1$/', trim($query[0]), $found) === 1) {
                $asked[] = $found[1];
            }
        }

        $placeholders = implode(', ', array_fill(0, count($forbidden), '%%s'));

        echo 'COREX_REQUEST_JSON:' . wp_json_encode([
            'is_admin' => is_admin(),
            'queries_heard' => count($wpdb->queries),
            'asked_one_at_a_time' => $asked,
            'stored' => $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
                $forbidden,
            )),
            'webp_quality' => \Corex\Boot::app()->container()
                ->make(\Corex\Support\Config\ConfigInterface::class)
                ->get('media.webp.quality', 82),
        ]);
        PHP,
        $admin ? 'true' : 'false',
        var_export(ABSPATH . 'wp-load.php', true),
        var_export(COREX_OVERRIDES_BOOT_ASKED_FOR, true),
    ));

    try {
        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } finally {
        unlink($script);
    }

    expect($exitCode)->toBe(0, "The request did not finish:\n" . $stdout . $stderr);
    expect(preg_match('/COREX_REQUEST_JSON:(\{.+\})/', $stdout, $matches))->toBe(1, $stdout . $stderr);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

it('asks the database for no setting override that is not stored while it boots', function (bool $admin) {
    $request = optionQueriesOfOneBoot(admin: $admin);

    if ($request['stored'] !== []) {
        // A stored override is loaded with the site's options and is no query either way, so
        // this install cannot tell the two behaviours apart for these.
        $this->markTestSkipped('This install stores ' . implode(', ', $request['stored']) . '.');
    }

    expect($request['is_admin'])->toBe($admin)
        // The probe is not deaf: the request ran queries, and it kept them.
        ->and($request['queries_heard'])->toBeGreaterThan(0)
        // The settings were read all the same, and answered by their defaults.
        ->and($request['webp_quality'])->toBe(82)
        ->and(array_values(array_intersect($request['asked_one_at_a_time'], COREX_OVERRIDES_BOOT_ASKED_FOR)))
        ->toBe([]);
})->with([
    'on a front-end request' => [false],
    'on an admin request' => [true],
]);
