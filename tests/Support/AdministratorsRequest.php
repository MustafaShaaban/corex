<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use RuntimeException;

/**
 * One admin request of an administrator whose profile language is not the site's, in a PHP
 * process of its own.
 *
 * A request's language is settled once, while WordPress loads, so a test that needs a request
 * in another language needs another request. No language pack is used: the request is told that
 * `ar` is installed, that it reads right to left, and that every string of CoreX's is `[ar] `
 * and then the string in it. What a test then reads is whether a word was made while the
 * request was in Arabic.
 *
 *     $request = AdministratorsRequest::on('en_US', 'ar')
 *         ->without('corex-email/corex-email.php')
 *         ->run('return ["subject" => __("New submission", "corex")];');
 *
 * Nothing is sent. WordPress is answered for before it reaches its mailer (`pre_wp_mail`) and
 * what it was handed is reported. A post the request inserts is deleted when it ends; anything
 * else it writes is the test's own to take back, in the request that wrote it. There is no
 * transaction to lean on: the tables of a developer's install may not have them.
 */
final class AdministratorsRequest
{
    private const REPORT = 'COREX_REQUEST_JSON:';

    private const SCRIPT = <<<'PHP'
        <?php
        // WordPress adopts hooks that were set before it loaded, which is the only way to decide
        // what a request's language is before the request has one.
        $hook = static function (string $name, callable $answer, int $arguments = 1, int $priority = 10): void {
            $GLOBALS['wp_filter'][$name][$priority]['corex_administrators_request'] = [
                'function' => $answer,
                'accepted_args' => $arguments,
            ];
        };

        $siteLanguage = '{{site}}';
        $profileLanguage = '{{profile}}';
        $switchedOff = {{switched_off}};

        $hook('pre_option_WPLANG', static fn () => $siteLanguage === 'en_US' ? '' : $siteLanguage);
        // After WordPress's own answer, which is "nobody" for a request with no cookie.
        $hook('determine_current_user', static fn () => 1, 1, 99);
        $hook(
            'get_user_metadata',
            static fn ($value, $userId, $key) => $key === 'locale' ? $profileLanguage : $value,
            3,
        );
        // `ar` as an installed language that reads right to left, with every string of CoreX's.
        $hook('get_available_languages', static fn (array $languages) => [...$languages, 'ar']);
        $hook(
            'gettext_with_context_default',
            static fn ($translation, $text, $context) => $text === 'ltr'
                && $context === 'text direction'
                && determine_locale() === 'ar' ? 'rtl' : $translation,
            3,
        );
        $inArabic = static fn ($translation) => determine_locale() === 'ar' ? '[ar] ' . $translation : $translation;
        $hook('gettext_corex', $inArabic);
        $hook('ngettext_corex', $inArabic);
        // Mail is decided as a production site decides it (spec 113 keeps a development site's
        // mail on the site), so each email reaches wp_mail(), where it is answered below.
        $hook('pre_option_corex_app_env', static fn () => 'production');
        // Nothing here sends, and nothing could. What WordPress was handed is kept to be read.
        $GLOBALS['corex_request_mail'] = [];
        $hook(
            'pre_wp_mail',
            static function ($sent, array $mail): bool {
                $GLOBALS['corex_request_mail'][] = [
                    'subject' => (string) $mail['subject'],
                    'message' => (string) $mail['message'],
                ];

                return true;
            },
            2,
        );
        // A plugin left out of the request, as on a site that has not switched it on.
        if ($switchedOff !== []) {
            $hook(
                'option_active_plugins',
                static fn ($plugins) => array_values(array_diff((array) $plugins, $switchedOff)),
            );
        }

        {{before}}

        define('WP_ADMIN', true);

        require '{{wp_load}}';

        $container = \Corex\Boot::app()->container();
        $language = static fn (): array => ['locale' => determine_locale(), 'is_rtl' => is_rtl()];
        $request = ['is_admin' => is_admin()] + $language();

        // Whatever the request stores is deleted when it ends: an email CoreX Mail hands over is
        // logged as a post, and the suite runs on an install somebody uses.
        $stored = [];
        add_action(
            'wp_insert_post',
            static function (int $postId, \WP_Post $post, bool $update) use (&$stored): void {
                if (! $update) {
                    $stored[] = $postId;
                }
            },
            10,
            3,
        );
        // WordPress says an attachment was inserted under a name of its own.
        add_action('add_attachment', static function (int $postId) use (&$stored): void {
            $stored[] = $postId;
        });

        $failure = null;
        try {
            $drawn = (static function () use ($container): array {
                {{body}}
            })();
        } catch (\Throwable $failure) {
        } finally {
            foreach ($stored as $postId) {
                wp_delete_post($postId, true);
            }
        }

        if ($failure !== null) {
            // Said here: WordPress would answer with a page that says only that something failed.
            fwrite(STDERR, (string) $failure);
            exit(1);
        }

        echo '{{report}}' . wp_json_encode([
            'request' => $request,
            'drawn' => $drawn,
            'mail' => $GLOBALS['corex_request_mail'],
            'afterwards' => $language(),
        ]);
        PHP;

    /** @var list<string> */
    private array $switchedOff = [];

    private string $beforeWordPressLoads = '';

    private function __construct(
        private readonly string $siteLanguage,
        private readonly string $profileLanguage,
    ) {
    }

    /**
     * A request on a site in one language, by an administrator who reads the admin in another.
     */
    public static function on(string $siteLanguage, string $profileLanguage): self
    {
        return new self($siteLanguage, $profileLanguage);
    }

    /**
     * Leave plugins out of the request, as on a site that has not switched them on.
     *
     * @param string ...$plugins Plugin files, as `corex-email/corex-email.php`.
     */
    public function without(string ...$plugins): self
    {
        $this->switchedOff = array_values($plugins);

        return $this;
    }

    /**
     * PHP statements to run before WordPress loads: for what has to be in place when the request
     * reaches a hook, and not after. `$hook($name, $answer, $arguments = 1, $priority = 10)`
     * sets one.
     */
    public function before(string $statements): self
    {
        $this->beforeWordPressLoads = $statements;

        return $this;
    }

    /**
     * Run `$body` once WordPress has loaded and report what it returned.
     *
     * @param string $body PHP statements that end by returning an array. `$container` is
     *                     CoreX's container.
     *
     * @return array{
     *     request: array{is_admin: bool, locale: string, is_rtl: bool},
     *     drawn: array<string,mixed>,
     *     mail: list<array{subject: string, message: string}>,
     *     afterwards: array{locale: string, is_rtl: bool},
     * } The request before `$body` ran, what `$body` returned, each email WordPress was handed,
     *   and the request's language afterwards.
     */
    public function run(string $body): array
    {
        $script = (string) tempnam(sys_get_temp_dir(), 'corex-request-');
        file_put_contents($script, strtr(self::SCRIPT, [
            '{{site}}' => $this->siteLanguage,
            '{{profile}}' => $this->profileLanguage,
            '{{switched_off}}' => var_export($this->switchedOff, true),
            '{{before}}' => $this->beforeWordPressLoads,
            '{{wp_load}}' => str_replace('\\', '/', ABSPATH) . 'wp-load.php',
            '{{report}}' => self::REPORT,
            '{{body}}' => $body,
        ]));

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

        $reported = strrpos($stdout, self::REPORT);
        if ($exitCode !== 0 || $reported === false) {
            // The end of what it printed: a page of WordPress's begins with its stylesheet.
            throw new RuntimeException("The request did not finish:\n" . $stderr . "\n" . substr($stdout, -2000));
        }

        return json_decode(substr($stdout, $reported + strlen(self::REPORT)), true, flags: JSON_THROW_ON_ERROR);
    }
}
