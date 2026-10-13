<?php

/**
 * Integration tests: the version of the Email Studio's installed defaults is stored where
 * WordPress loads it with the site's options (DECISIONS #366).
 *
 * The email add-on compares that version with its own on `init` of every request, to install the
 * layouts and partials a release added, and it was saved with autoload off: one query a request
 * on every site that has the add-on.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\MailServiceProvider;
use Corex\Tests\Support\LoadedOptionsRun;
use Corex\Tests\Support\OptionRow;

beforeEach(function () {
    if (! class_exists(MailServiceProvider::class)) {
        $this->markTestSkipped('The email add-on is not active on this install.');
    }

    $this->versionRow = OptionRow::capture(MailServiceProvider::STUDIO_DEFAULTS_OPTION);
});

afterEach(function () {
    if (property_exists($this, 'versionRow')) {
        OptionRow::restore(MailServiceProvider::STUDIO_DEFAULTS_OPTION, $this->versionRow);
    }
});

it('stores the version loaded when it installs the defaults', function () {
    delete_option(MailServiceProvider::STUDIO_DEFAULTS_OPTION);

    // Installing is safe to repeat: a layout or a partial that is there is left as it is.
    (new MailServiceProvider(\Corex\Boot::app()->container()))->registerEmailStudio();

    expect(OptionRow::autoload(MailServiceProvider::STUDIO_DEFAULTS_OPTION))->toBe('on')
        ->and(get_option(MailServiceProvider::STUDIO_DEFAULTS_OPTION))->not->toBe('');
});

it('turns over the version an older release stored unloaded, and keeps it', function () {
    OptionRow::storeUnloaded(MailServiceProvider::STUDIO_DEFAULTS_OPTION, '2');

    LoadedOptionsRun::onACronRequest();

    expect(OptionRow::autoload(MailServiceProvider::STUDIO_DEFAULTS_OPTION))->toBe('on')
        ->and(get_option(MailServiceProvider::STUDIO_DEFAULTS_OPTION))->toBe('2');
});
