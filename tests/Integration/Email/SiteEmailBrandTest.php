<?php

/**
 * An email is drawn as this site's: its name, its address, its mark, its direction and its
 * language, read from WordPress when the email is drawn (spec 106, FR-009).
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailBrandSource;
use Corex\Email\Design\EmailPalette;
use Corex\Email\Design\SiteEmailBrand;

function siteEmailBrand(): EmailBrand
{
    return Boot::app()->container()->make(EmailBrandSource::class)->brand();
}

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

    $brand = siteEmailBrand();

    expect($brand->name)->toBe('Acme & Sons')
        ->and($brand->homeUrl)->toBe(home_url('/'))
        ->and($brand->direction)->toBe(is_rtl() ? 'rtl' : 'ltr')
        ->and($brand->language)->toBe(get_bloginfo('language'))
        ->and($brand->light)->toEqual(EmailPalette::light())
        ->and($brand->dark)->toEqual(EmailPalette::dark());
});

it('names a site that has no title by where it is', function () {
    ($this->answer)('pre_option_blogname', static fn (): string => '');

    expect(siteEmailBrand()->name)->toBe((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
});

it('takes the site icon for its mark where the theme has no logo', function () {
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('get_site_icon_url', static fn (): string => 'https://acme.test/icon.png');

    expect(siteEmailBrand()->logo)->toBe('https://acme.test/icon.png');
});

it('has no mark where the site has neither a logo nor an icon', function () {
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('get_site_icon_url', static fn (): string => '');

    expect(siteEmailBrand()->logo)->toBe('');
});

// Spec 106, FR-015: a site's own colour, logo and footer line, with no code.
it('draws the email from the accent, the logo and the footer line the site set', function () {
    ($this->answer)('pre_option_corex_mail_brand_accent', static fn (): string => '#0B1F3B');
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => 'https://acme.test/mail-logo.png');
    ($this->answer)('pre_option_corex_mail_brand_footer', static fn (): string => 'Acme Design · Cairo');

    $brand = siteEmailBrand();

    expect($brand->light->accent)->toBe('#0b1f3b')
        ->and($brand->dark->accent)->toBe('#0b1f3b')
        ->and($brand->light->text)->toBe(EmailPalette::light()->text)
        ->and($brand->logo)->toBe('https://acme.test/mail-logo.png')
        ->and($brand->footer)->toBe('Acme Design · Cairo');
});

it('passes over a stored accent that is not a colour an email can carry', function () {
    ($this->answer)('pre_option_corex_mail_brand_accent', static fn (): string => 'red;background:url(x)');

    expect(siteEmailBrand()->light->accent)->toBe(EmailPalette::light()->accent);
});

it('takes the brand a site plugin returns from the filter', function () {
    ($this->answer)(SiteEmailBrand::FILTER, static fn (EmailBrand $brand): EmailBrand => $brand->withPalettes(
        $brand->light->with(['link' => '#0b1f3b']),
        $brand->dark,
    )->withFooter('From the filter'));

    $brand = siteEmailBrand();

    expect($brand->light->link)->toBe('#0b1f3b')
        ->and($brand->footer)->toBe('From the filter');
});

it('keeps the brand it had when a site filter fails', function (callable $filter) {
    ($this->answer)('pre_option_corex_mail_brand_footer', static fn (): string => 'Set in the settings');
    ($this->answer)(SiteEmailBrand::FILTER, $filter);

    $brand = siteEmailBrand();

    expect($brand->footer)->toBe('Set in the settings')
        ->and($brand->light)->toEqual(EmailPalette::light());
})->with([
    'a colour that is not hex' => [static fn (EmailBrand $brand): EmailBrand => $brand->withPalettes($brand->light->with(['text' => 'navy']), $brand->dark)],
    'something that is not a brand' => [static fn (): string => 'navy'],
    'an error of its own' => [static fn () => throw new RuntimeException('Undefined index')],
]);
