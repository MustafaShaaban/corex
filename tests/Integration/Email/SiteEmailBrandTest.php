<?php

/**
 * An email is drawn as this site's: its name, its address, its mark, its direction and its
 * language, read from WordPress when the email is drawn (spec 106, FR-009).
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Design\EmailPalette;
use Corex\Email\Design\SiteEmailBrand;

beforeEach(function () {
    // What this file answers for WordPress, removed afterwards one by one: nothing else that
    // listens on these hooks is touched.
    $this->answered = [];
    $this->answer = function (string $hook, callable $with): void {
        add_filter($hook, $with);
        $this->answered[] = [$hook, $with];
    };
});

afterEach(function () {
    foreach ($this->answered as [$hook, $with]) {
        remove_filter($hook, $with);
    }
});

it('names the site as it is written, not as WordPress stores it', function () {
    ($this->answer)('pre_option_blogname', static fn (): string => 'Acme &amp; Sons');

    $brand = (new SiteEmailBrand())->brand();

    expect($brand->name)->toBe('Acme & Sons')
        ->and($brand->homeUrl)->toBe(home_url('/'))
        ->and($brand->direction)->toBe(is_rtl() ? 'rtl' : 'ltr')
        ->and($brand->language)->toBe(get_bloginfo('language'))
        ->and($brand->light)->toEqual(EmailPalette::light())
        ->and($brand->dark)->toEqual(EmailPalette::dark());
});

it('names a site that has no title by where it is', function () {
    ($this->answer)('pre_option_blogname', static fn (): string => '');

    expect((new SiteEmailBrand())->brand()->name)->toBe((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
});

it('takes the site icon for its mark where the theme has no logo', function () {
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('get_site_icon_url', static fn (): string => 'https://acme.test/icon.png');

    expect((new SiteEmailBrand())->brand()->logo)->toBe('https://acme.test/icon.png');
});

it('has no mark where the site has neither a logo nor an icon', function () {
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('get_site_icon_url', static fn (): string => '');

    expect((new SiteEmailBrand())->brand()->logo)->toBe('');
});
