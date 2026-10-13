<?php

/**
 * An email is drawn inside WordPress's switch to the site's language, and the request gets its
 * own language back (DECISIONS #356).
 *
 * What the switch changes is WordPress's and is held against WordPress in `EmailLanguageTest`.
 * Here: that the switch is made around the drawing, and undone exactly when it was made.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Mail\SiteLanguage;

it('switches to the site language for the drawing and back after it', function () {
    $steps = [];
    Functions\when('get_locale')->justReturn('en_US');
    Functions\when('switch_to_locale')->alias(static function (string $locale) use (&$steps): bool {
        $steps[] = 'switched to ' . $locale;

        return true;
    });
    Functions\when('restore_previous_locale')->alias(static function () use (&$steps): string {
        $steps[] = 'restored';

        return 'ar';
    });

    $drawn = (new SiteLanguage())->during(static function () use (&$steps): string {
        $steps[] = 'drawn';

        return '<html dir="ltr">';
    });

    expect($drawn)->toBe('<html dir="ltr">')
        ->and($steps)->toBe(['switched to en_US', 'drawn', 'restored']);
});

it('restores nothing when WordPress had nothing to switch', function () {
    // The request is in the site's language already. Restoring here would undo a switch
    // somebody else made around this email.
    Functions\when('get_locale')->justReturn('en_US');
    Functions\when('switch_to_locale')->justReturn(false);
    Functions\expect('restore_previous_locale')->never();

    expect((new SiteLanguage())->during(static fn (): string => 'drawn'))->toBe('drawn');
});

it('gives the request its language back when the drawing fails', function () {
    Functions\when('get_locale')->justReturn('en_US');
    Functions\when('switch_to_locale')->justReturn(true);
    Functions\expect('restore_previous_locale')->once();

    expect(static fn () => (new SiteLanguage())->during(static function (): never {
        throw new DomainException('The template could not be drawn.');
    }))->toThrow(DomainException::class);
});
