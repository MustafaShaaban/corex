<?php

/**
 * Integration test: an email is drawn in the site's language, whoever's request draws it
 * (DECISIONS #356).
 *
 * WordPress answers `is_rtl()`, `get_bloginfo('language')` and every translated string for the
 * language of the request. In the admin that is the signed-in person's own profile language,
 * not the site's, so an email written for an English site's customer was laid out right to
 * left, marked as Arabic and signed off in Arabic when the administrator who triggered it reads
 * the admin in Arabic.
 *
 * Each case is a request of its own, because a request's language is settled once, while
 * WordPress loads. No language pack is needed: the request is told that `ar` is installed, that
 * it reads right to left, and what one string of an email is in it.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

/**
 * Draw the shared layout, a template email and a reply in an admin request of a person whose profile language is
 * not the site's, and report what was drawn and what the request was before and after.
 *
 * @return array{
 *     request: array{is_admin: bool, locale: string, is_rtl: bool},
 *     layout: array{html: string, in_other_language: bool},
 *     template: array{html: string, in_other_language: bool},
 *     reply: array{html: string, in_other_language: bool},
 *     afterwards: array{locale: string, is_rtl: bool},
 * }
 */
function emailsDrawnInAnAdminRequest(string $siteLanguage, string $profileLanguage): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-request-') . '.php';
    file_put_contents($script, sprintf(
        <<<'PHP'
        <?php
        // WordPress adopts hooks that were set before it loaded, which is the only way to decide
        // what a request's language is before the request has one.
        $hook = static function (string $name, callable $answer, int $arguments = 1, int $priority = 10): void {
            $GLOBALS['wp_filter'][$name][$priority]['corex_language_probe'] = [
                'function' => $answer,
                'accepted_args' => $arguments,
            ];
        };

        $siteLanguage = %1$s;
        $profileLanguage = %2$s;

        $hook('pre_option_WPLANG', static fn () => $siteLanguage === 'en_US' ? '' : $siteLanguage);
        // After WordPress's own answer, which is "nobody" for a request with no cookie.
        $hook('determine_current_user', static fn () => 1, 1, 99);
        $hook(
            'get_user_metadata',
            static fn ($value, $userId, $key) => $key === 'locale' ? $profileLanguage : $value,
            3,
        );
        // `ar` as an installed language that reads right to left, with one string of an email.
        $hook('get_available_languages', static fn (array $languages) => [...$languages, 'ar']);
        $hook(
            'gettext_with_context_default',
            static fn ($translation, $text, $context) => $text === 'ltr'
                && $context === 'text direction'
                && determine_locale() === 'ar' ? 'rtl' : $translation,
            3,
        );
        $hook(
            'gettext_corex',
            static fn ($translation) => determine_locale() === 'ar' ? '[ar] ' . $translation : $translation,
        );
        // Nothing here sends, and nothing could.
        $hook('pre_wp_mail', static fn () => true);

        define('WP_ADMIN', true);

        require %3$s;

        $container = \Corex\Boot::app()->container();
        $language = static fn (): array => ['locale' => determine_locale(), 'is_rtl' => is_rtl()];
        $drawn = static fn (string $html): array => [
            'html' => preg_match('/<html[^>]*>/', $html, $found) === 1 ? $found[0] : '',
            'in_other_language' => str_contains($html, '[ar] '),
        ];

        $request = ['is_admin' => is_admin()] + $language();

        $template = $container->make(\Corex\Email\Template\TemplateRenderer::class)->render(
            $container->make(\Corex\Email\Template\TemplateRegistry::class)->find('contact-notification'),
            new \Corex\Email\Template\MailContext(['submission' => ['name' => 'Salma']]),
        );
        $reply = $container->make(\Corex\Email\Design\ReplyComposer::class)
            ->compose('salma@example.com', 'Subject', '<p>Hello</p>');
        // What an Email Studio template is wrapped by, with nothing else around it.
        $layout = $container->make(\Corex\Email\Template\Layout::class)->wrap('Subject', '<p>Body</p>');

        echo 'COREX_REQUEST_JSON:' . wp_json_encode([
            'request' => $request,
            'layout' => $drawn($layout),
            'template' => $drawn($template->body),
            'reply' => $drawn($reply->html),
            'afterwards' => $language(),
        ]);
        PHP,
        var_export($siteLanguage, true),
        var_export($profileLanguage, true),
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

it('draws an email in the language of the site, not of the administrator whose request draws it', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    array $theEmail,
) {
    $drawn = emailsDrawnInAnAdminRequest($siteLanguage, $profileLanguage);

    // The request is the administrator's: without that, this test would pass on any code.
    expect($drawn['request'])->toBe(['is_admin' => true] + $theRequest)
        // The bare layout has no words of CoreX's own: only which way it runs.
        ->and($drawn['layout']['html'])->toBe(sprintf('<html dir="%s">', $theEmail['dir']))
        ->and($drawn['template'])->toBe(['html' => sprintf('<html dir="%s">', $theEmail['dir'])] + $theEmail['words'])
        ->and($drawn['reply'])->toBe(
            ['html' => sprintf('<html lang="%s" dir="%s">', $theEmail['lang'], $theEmail['dir'])] + $theEmail['words'],
        )
        // And the administrator's screen is still theirs once the emails are drawn.
        ->and($drawn['afterwards'])->toBe($theRequest);
})->with([
    'an English site, an administrator who reads the admin in Arabic' => [
        'en_US',
        'ar',
        ['locale' => 'ar', 'is_rtl' => true],
        ['dir' => 'ltr', 'lang' => 'en-US', 'words' => ['in_other_language' => false]],
    ],
    'an Arabic site, an administrator who reads the admin in English' => [
        'ar',
        'en_US',
        ['locale' => 'en_US', 'is_rtl' => false],
        ['dir' => 'rtl', 'lang' => 'ar', 'words' => ['in_other_language' => true]],
    ],
]);
