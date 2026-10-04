<?php

/**
 * The media provider boots on `plugins_loaded` and must build neither of its command definitions
 * there: they translate their help text, and a translation before `init` loads the `corex` text
 * domain earlier than WordPress 6.7+ accepts. The commands are handed to WP-CLI on `cli_init`
 * instead (DECISIONS #233).
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Media\MediaServiceProvider;
use Corex\Support\Config\ConfigInterface;

/**
 * Brain Monkey probes every hooked closure with `Closure::bind()`, which warns for a static one,
 * and boot() hooks several. The probe silences its own warning; Pest reports it regardless.
 */
function bootWithoutStaticClosureProbeWarnings(MediaServiceProvider $provider): void
{
    $previous = set_error_handler(
        static function (int $level, string $message, string $file = '', int $line = 0) use (&$previous): bool {
            if (str_contains($message, 'Cannot bind an instance to a static closure')) {
                return true;
            }

            return $previous !== null && $previous($level, $message, $file, $line);
        },
    );

    try {
        $provider->boot();
    } finally {
        restore_error_handler();
    }
}

it('translates nothing while it boots, and registers its commands on cli_init', function () {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
    Functions\when('__')->alias(static function (string $text): never {
        throw new LogicException("\"{$text}\" was translated while the media provider booted.");
    });

    $container = new Container();
    // Conversion off, so boot() stops before the job registration this test is not about.
    $container->instance(ConfigInterface::class, new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'media.webp.enabled' ? false : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'media.webp.enabled';
        }
    });

    $provider = new MediaServiceProvider($container);
    $provider->register();
    bootWithoutStaticClosureProbeWarnings($provider);

    expect(has_action('cli_init'))->toBeTrue();
});
