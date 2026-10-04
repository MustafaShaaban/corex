<?php

/**
 * The CLI provider boots on `plugins_loaded` and must build no command definition there: a
 * definition translates its help text, and a translation before `init` loads the `corex` text
 * domain earlier than WordPress 6.7+ accepts. The commands are handed to WP-CLI on `cli_init`
 * instead (DECISIONS #233).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\CliServiceProvider;
use Corex\Container\Container;

it('translates nothing while it boots, and registers its commands on cli_init', function () {
    Functions\when('__')->alias(static function (string $text): never {
        throw new LogicException("\"{$text}\" was translated while the CLI provider booted.");
    });

    $provider = new CliServiceProvider(new Container());
    $provider->boot();

    expect(has_action('cli_init', [$provider, 'registerCommands']))->toBe(10);
});
