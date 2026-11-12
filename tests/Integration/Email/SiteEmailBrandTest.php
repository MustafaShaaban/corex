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
use Corex\Tests\Support\BrandAccent;
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
    // Whatever the test went on to say: an image it put in the library does not outlive it.
    foreach (libraryImages() as $image) {
        wp_delete_attachment($image, true);
    }
    libraryImages(forget: true);
});

it('names the site as it is written, not as WordPress stores it', function () {
    ($this->answer)('pre_option_blogname', static fn (): string => 'Acme &amp; Sons');

    $brand = siteEmailBrand();

    expect($brand->name)->toBe('Acme & Sons')
        ->and($brand->homeUrl)->toBe(home_url('/'))
        ->and($brand->direction)->toBe(is_rtl() ? 'rtl' : 'ltr')
        ->and($brand->language)->toBe(get_bloginfo('language'))
        // With no accent set, the brand's own primary colour (spec 120).
        ->and($brand->light)->toEqual(EmailPalette::light()->with(['accent' => BrandAccent::primary()]))
        ->and($brand->dark)->toEqual(EmailPalette::dark()->with(['accent' => BrandAccent::primary()]));
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
    ($this->answer)('pre_option_corex_mail_brand_accent', static fn (): string => '#12AB34');
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => 'https://acme.test/mail-logo.png');
    ($this->answer)('pre_option_corex_mail_brand_footer', static fn (): string => 'Acme Design · Cairo');

    $brand = siteEmailBrand();

    expect($brand->light->accent)->toBe('#12ab34')
        ->and($brand->dark->accent)->toBe('#12ab34')
        ->and($brand->light->text)->toBe(EmailPalette::light()->text)
        ->and($brand->logo)->toBe('https://acme.test/mail-logo.png')
        ->and($brand->footer)->toBe('Acme Design · Cairo');
});

it('passes over a logo setting that is not a whole web address, and keeps the site\'s own', function (string $stored) {
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => $stored);
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('get_site_icon_url', static fn (): string => 'https://acme.test/icon.png');

    expect(siteEmailBrand()->logo)->toBe('https://acme.test/icon.png');
})->with(['/wp-content/uploads/logo.png', '//cdn.acme.test/logo.png', 'ftp://acme.test/logo.png']);

it('passes over a stored accent that is not a colour an email can carry', function () {
    ($this->answer)('pre_option_corex_mail_brand_accent', static fn (): string => 'red;background:url(x)');

    expect(siteEmailBrand()->light->accent)->toBe(BrandAccent::primary());
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

it('tells the email managers when a site brand filter throws, and what it said', function () {
    $announced = [];
    Boot::app()->container()->make(\Corex\Events\ListenerProvider::class)->listen(
        \Corex\Email\Design\EmailDesignFailedEvent::class,
        static function (object $event) use (&$announced): void {
            $announced[] = [$event->design, $event->reason, $event->detail];
        },
    );
    ($this->answer)(SiteEmailBrand::FILTER, static fn (EmailBrand $brand): EmailBrand => $brand->withPalettes($brand->light->with(['text' => 'navy']), $brand->dark));

    siteEmailBrand();
    Boot::app()->container()->make(\Corex\Notifications\NotificationService::class)
        ->resolve('email.design_failed:corex_email_brand', 'The test that raised it is over.');

    expect($announced)->toBe([['corex_email_brand', 'brand_threw', 'The email colour "text" must be written as #rrggbb.']]);
});

it('keeps the brand it had when a site filter fails', function (callable $filter) {
    ($this->answer)('pre_option_corex_mail_brand_footer', static fn (): string => 'Set in the settings');
    ($this->answer)(SiteEmailBrand::FILTER, $filter);

    $brand = siteEmailBrand();

    expect($brand->footer)->toBe('Set in the settings')
        ->and($brand->light)->toEqual(EmailPalette::light()->with(['accent' => BrandAccent::primary()]));
})->with([
    'a colour that is not hex' => [static fn (EmailBrand $brand): EmailBrand => $brand->withPalettes($brand->light->with(['text' => 'navy']), $brand->dark)],
    'something that is not a brand' => [static fn (): string => 'navy'],
    'an error of its own' => [static fn () => throw new RuntimeException('Undefined index')],
]);

/**
 * The images this file put in the site's library, so that each test's are removed after it.
 *
 * @return list<int>
 */
function libraryImages(?int $added = null, bool $forget = false): array
{
    static $made = [];
    if ($added !== null) {
        $made[] = $added;
    }
    $all = $made;
    if ($forget) {
        $made = [];
    }

    return $all;
}

/**
 * An image in the site's library. No file is written: what is read here is what WordPress
 * recorded about one.
 *
 * @param array{int,int}|null $size The size WordPress recorded, or null for none.
 */
function libraryImage(?array $size, string $file = 'logo.png'): int
{
    $id = (int) wp_insert_attachment([
        'post_title' => $file,
        'post_mime_type' => str_ends_with($file, '.svg') ? 'image/svg+xml' : 'image/png',
        'post_status' => 'inherit',
    ]);
    update_post_meta($id, '_wp_attached_file', '2026/10/' . $file);
    if ($size !== null) {
        update_post_meta($id, '_wp_attachment_metadata', [
            'width' => $size[0],
            'height' => $size[1],
            'file' => '2026/10/' . $file,
            'sizes' => [],
        ]);
    }
    libraryImages($id);

    return $id;
}

function uploadsAddress(string $path): string
{
    return wp_get_upload_dir()['baseurl'] . '/' . $path;
}

// Spec 106, FR-033: a design fits the logo to its header from the logo's own size, so the
// size is read where WordPress knows it.
it('says the size of the theme\'s logo, which is what a design fits it from', function () {
    $logo = libraryImage([600, 600]);
    ($this->answer)('theme_mod_custom_logo', static fn (): int => $logo);

    $brand = siteEmailBrand();

    expect($brand->logo)->toBe(uploadsAddress('2026/10/logo.png'))
        ->and([$brand->logoWidth, $brand->logoHeight])->toBe([600, 600])
        ->and($brand->logoBox())->toBe([48, 48]);
});

// WordPress answers "1 by 1" for an image it holds no size for. Taken as a size, the logo was
// written one pixel square for a client that reads an image's own size.
it('says no size for a theme logo WordPress recorded none for', function () {
    $logo = libraryImage(null);
    ($this->answer)('theme_mod_custom_logo', static fn (): int => $logo);

    $brand = siteEmailBrand();

    expect($brand->logo)->toBe(uploadsAddress('2026/10/logo.png'))
        ->and($brand->logoBox())->toBeNull();
});

it('says the size of the site icon where that is the mark', function () {
    $icon = libraryImage([512, 512], 'icon.png');
    ($this->answer)('theme_mod_custom_logo', static fn (): int => 0);
    ($this->answer)('pre_option_site_icon', static fn (): int => $icon);

    $brand = siteEmailBrand();

    expect($brand->logo)->toBe(uploadsAddress('2026/10/icon.png'))
        ->and($brand->logoBox())->toBe([48, 48])
        // And the address alone, which the older layout asks for, is the same one.
        ->and(Boot::app()->container()->make(SiteEmailBrand::class)->logo())->toBe($brand->logo);
});

// Gmail and Outlook on Windows draw nothing for a vector image: the name is a better header.
it('passes over a vector logo, for the next mark the site has or for its name', function () {
    $vector = libraryImage([300, 100], 'logo.svg');
    ($this->answer)('theme_mod_custom_logo', static fn (): int => $vector);
    ($this->answer)('pre_option_site_icon', static fn (): int => 0);
    $named = uploadsAddress('2026/10/mail.SVG?v=2');
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => $named);

    expect(siteEmailBrand()->logo)->toBe('');
});

it('says the size of the logo the Mail settings name, when it is in the site\'s library', function (string $suffix) {
    libraryImage([1000, 200], 'mail-logo.png');
    $address = uploadsAddress('2026/10/mail-logo.png') . $suffix;
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => $address);

    $brand = siteEmailBrand();

    expect($brand->logo)->toBe($address)
        ->and($brand->logoBox())->toBe([180, 36]);
})->with([
    'as it is' => [''],
    'with a version after it' => ['?ver=3'],
]);

it('reads the size a cut of this site\'s own image says in its name', function () {
    $cut = uploadsAddress('2026/10/acme-300x150.png');
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => $cut);

    expect(siteEmailBrand()->logoBox())->toBe([96, 48]);
});

// A name is a size only where WordPress wrote it. Elsewhere it is a name: designers call a
// file by its proportions, and a report by its years.
it('says no size for a logo elsewhere, whatever its name or its address says', function (string $address) {
    ($this->answer)('pre_option_corex_mail_brand_logo', static fn (): string => $address);

    $brand = siteEmailBrand();

    expect($brand->logo)->toBe($address)
        ->and([$brand->logoWidth, $brand->logoHeight])->toBe([0, 0])
        ->and($brand->logoBox())->toBeNull();
})->with([
    'a plain name' => ['https://cdn.acme.test/brand/acme.png'],
    'a name that is a ratio' => ['https://cdn.acme.test/brand/logo-16x9.png'],
    'a name that is two years' => ['https://cdn.acme.test/brand/report-2024x2025.png'],
    'a size in what follows the name' => ['https://cdn.acme.test/brand/logo.png?src=a-300x150.png'],
]);
