<?php

/**
 * Integration test: no string of CoreX's is translated before WordPress's `init`, on any request
 * (DECISIONS #351).
 *
 * WordPress settles which language a request is in, and which way its text runs, after
 * `plugins_loaded`, where CoreX boots. A string of the `corex` domain read before that makes
 * WordPress log "Translation loading for the `corex` domain was triggered too early" on every
 * request of a site with `WP_DEBUG` on, and whatever was worked out beside the string was worked
 * out too soon as well: the layout of an email, made while CoreX booted, was left-to-right on a
 * right-to-left site.
 *
 * The log holds one notice per request, for the first string read. These tests hear every one,
 * each in a request of its own, because this process booted long ago and has run other tests.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

/**
 * Load WordPress in a process of its own and report what it translated while it started.
 *
 * @return array{is_admin: bool, is_rtl: bool, heard: int, translated_before_init: list<string>, email_direction: string}
 */
function translationReportOfOneRequest(bool $admin, bool $rightToLeft = false): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-request-') . '.php';
    file_put_contents($script, sprintf(
        <<<'PHP'
        <?php
        // WordPress adopts hooks that were set before it loaded, which is the only way to hear
        // what the boot itself translates.
        $heard = 0;
        $early = [];
        $listen = static function ($translation, $text) use (&$heard, &$early) {
            $heard++;

            if (did_action('init') > 0) {
                return $translation;
            }

            // Who asked: the nearest caller that is not WordPress's own translating.
            $reader = 'unknown';
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                $file = str_replace('\\', '/', (string) ($frame['file'] ?? ''));
                if ($file !== '' && ! str_contains($file, '/wp-includes/')) {
                    $reader = basename($file) . ':' . ($frame['line'] ?? 0);
                    break;
                }
            }

            // One line per reader: a theme's settings alone are sixty strings.
            $where = $reader . ' during ' . ($GLOBALS['wp_current_filter'][0] ?? 'the load');
            $early[$where] ??= ['strings' => 0, 'first' => (string) $text];
            $early[$where]['strings']++;

            return $translation;
        };

        foreach (['gettext_corex', 'gettext_with_context_corex', 'ngettext_corex', 'ngettext_with_context_corex'] as $hook) {
            $GLOBALS['wp_filter'][$hook][10]['corex_translation_probe'] = ['function' => $listen, 'accepted_args' => 2];
        }

        if (%s) {
            define('WP_ADMIN', true);
        }

        if (%s) {
            // What the file of a right-to-left language sets, without needing one installed.
            $GLOBALS['text_direction'] = 'rtl';
        }

        require %s;

        $email = \Corex\Boot::app()->container()
            ->make(\Corex\Email\Template\Layout::class)
            ->wrap('Subject', '<p>Body</p>');

        echo 'COREX_REQUEST_JSON:' . wp_json_encode([
            'is_admin' => is_admin(),
            'is_rtl' => is_rtl(),
            'heard' => $heard,
            'translated_before_init' => array_map(
                static fn (string $where, array $read): string => sprintf(
                    '%%s: %%d, the first "%%s"',
                    $where,
                    $read['strings'],
                    $read['first'],
                ),
                array_keys($early),
                array_values($early),
            ),
            'email_direction' => preg_match('/<html dir="(rtl|ltr)"/', $email, $found) === 1 ? $found[1] : '',
        ]);
        PHP,
        $admin ? 'true' : 'false',
        $rightToLeft ? 'true' : 'false',
        var_export(ABSPATH . 'wp-load.php', true),
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

it('translates no string of the corex domain before init', function (bool $admin) {
    $request = translationReportOfOneRequest(admin: $admin);

    expect($request['is_admin'])->toBe($admin)
        // The probe is not deaf: the request translated CoreX's strings, and it heard them.
        ->and($request['heard'])->toBeGreaterThan(0)
        ->and($request['translated_before_init'])->toBe([]);
})->with([
    'on a front-end request' => [false],
    'on an admin request' => [true],
]);

it('draws an email right to left on a right-to-left site', function () {
    $request = translationReportOfOneRequest(admin: false, rightToLeft: true);

    expect($request['is_rtl'])->toBeTrue()
        ->and($request['email_direction'])->toBe('rtl');
});
