<?php

/**
 * The standard email design, the values it is drawn from, and the plain-text version beside it
 * (spec 106, slice 2: FR-008 to FR-013).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailPalette;
use Corex\Email\Design\PlainText;
use Corex\Email\Design\EmailBrandSource;
use Corex\Email\Design\ReplyComposer;
use Corex\Email\Design\StandardEmailDesign;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/** The contrast of two `#rrggbb` colours, as WCAG measures it. */
function emailContrast(string $first, string $second): float
{
    $luminance = static function (string $colour): float {
        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($colour, '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };
    $lighter = max($luminance($first), $luminance($second));
    $darker = min($luminance($first), $luminance($second));

    return ($lighter + 0.05) / ($darker + 0.05);
}

function acmeBrand(string $logo = '', string $direction = 'ltr', string $footer = ''): EmailBrand
{
    return EmailBrand::standard('Acme & Sons', 'https://acme.test/', $logo, $direction, 'en-GB', $footer);
}

function acmeEmail(string $message = '<p>Thanks, Sam.</p>', ?EmailBrand $brand = null): string
{
    return (new StandardEmailDesign())->render(
        new EmailContent('Re: your <message>', $message, 'sam@example.com'),
        $brand ?? acmeBrand(),
    );
}

// SC-005: every text an email shows, on every ground it is shown on, in both palettes.
it('keeps a contrast of 4.5 to 1 for every text on every ground', function (EmailPalette $palette) {
    foreach ([$palette->text, $palette->muted, $palette->link] as $ink) {
        foreach ([$palette->surface, $palette->canvas] as $ground) {
            expect(emailContrast($ink, $ground))->toBeGreaterThanOrEqual(4.5, "$ink on $ground");
        }
    }
})->with([
    'light' => [fn () => EmailPalette::light()],
    'dark' => [fn () => EmailPalette::dark()],
]);

// An email cannot read the admin's custom properties, so their values are written again in
// EmailPalette. This is what notices when the two stop agreeing.
it('holds the colours the CoreX admin is drawn in, light and dark', function (EmailPalette $palette) {
    $tokens = strtolower((string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-core/assets/css/corex-admin-tokens.css'));
    $short = static fn (string $colour): string => preg_match('/^#(.)\1(.)\2(.)\3$/', $colour, $pair) === 1
        ? '#' . $pair[1] . $pair[2] . $pair[3]
        : $colour;
    $token = [
        'canvas' => 'canvas', 'surface' => 'surface-raised', 'border' => 'border', 'text' => 'text',
        'muted' => 'text-muted', 'accent' => 'action', 'link' => 'link',
    ];

    foreach ($token as $colour => $name) {
        $written = preg_quote($palette->{$colour}, '/') . '|' . preg_quote($short($palette->{$colour}), '/');
        expect($tokens)->toMatch(sprintf('/--corex-admin-%s:\s*(%s);/', $name, $written));
    }
})->with([
    'light' => [fn () => EmailPalette::light()],
    'dark' => [fn () => EmailPalette::dark()],
]);

it('refuses a colour an email client could not read', function (mixed $colour) {
    expect(fn () => EmailPalette::light()->with(['accent' => $colour]))->toThrow(InvalidArgumentException::class);
})->with([
    'a name' => ['red'],
    'three digits' => ['#abc'],
    'a function' => ['rgb(1,2,3)'],
    'a letter that is not hex' => ['#12345g'],
    'a custom property' => ['var(--corex-admin-action)'],
    'a second declaration' => ['#ad8643;background:url(x)'],
    'a line ending after it' => ["#ad8643\n#000000"],
    'not text at all' => [['#ad8643']],
]);

it('reads ltr or rtl and nothing else, because the direction is written into the document', function () {
    expect(fn () => new EmailBrand('Acme', 'https://acme.test/', '', 'ltr" onload="steal()', 'en', '', EmailPalette::light(), EmailPalette::dark()))
        ->toThrow(InvalidArgumentException::class)
        ->and(EmailBrand::standard('Acme', 'https://acme.test/', '', 'sideways')->direction)->toBe('ltr');
});

it('links and loads the web only, whatever it is handed', function () {
    $html = acmeEmail(brand: EmailBrand::standard('Acme', 'javascript:steal()', 'data:image/svg+xml,<svg onload=steal()>'));

    expect($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('data:')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('This message was sent by Acme.');
});

it('takes a site its own colours and keeps the rest', function () {
    $own = EmailPalette::light()->with(['accent' => '#0B1F3B']);

    expect($own->accent)->toBe('#0b1f3b')
        ->and($own->text)->toBe(EmailPalette::light()->text);
});

it('shows the site name where there is no logo', function () {
    $html = acmeEmail();

    expect($html)->not->toContain('<img')
        ->and($html)->toContain('Acme &amp; Sons');
});

it('shows the logo with the site name standing in for it when images are off', function () {
    $html = acmeEmail(brand: acmeBrand('https://acme.test/logo.png'));

    expect($html)->toContain('<img src="https://acme.test/logo.png"')
        ->and($html)->toContain('alt="Acme &amp; Sons"');
});

/**
 * The email's header as a reader of markup sees it: its logo's attributes, and its words.
 *
 * @return array{logo:?array<string,string>,words:string,cells:list<string>}
 */
function emailHeader(string $html): array
{
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $find = new DOMXPath($document);
    $band = $find->query('//td[contains(concat(" ", @class, " "), " cx-band ") or contains(concat(" ", @class, " "), " own-band ")]')->item(0);
    $image = $find->query('.//img', $band)->item(0);
    $logo = null;
    if ($image instanceof DOMElement) {
        foreach ($image->attributes as $attribute) {
            $logo[$attribute->name] = $attribute->value;
        }
    }
    $cells = [];
    foreach ($find->query('//td[contains(concat(" ", @class, " "), " cx-surface ") or contains(concat(" ", @class, " "), " own-surface ")]') as $cell) {
        $cells[] = $cell->getAttribute('style');
    }

    return ['logo' => $logo, 'words' => trim($band->textContent), 'cells' => $cells];
}

// Spec 106, FR-032: on the first live site the logo stood above the card, on the canvas, and
// read as something that had come loose from the email.
it('draws the header as a band of the card that holds the message', function () {
    $html = acmeEmail();
    $accent = EmailPalette::light()->accent;
    $header = emailHeader($html);

    expect($header['cells'])->toHaveCount(2)
        // The band carries the card's edge and its top corners; the message its bottom ones.
        ->and($header['cells'][0])->toContain('border-top:4px solid ' . $accent)->toContain('border-radius:10px 10px 0 0')
        ->and($header['cells'][1])->toContain('border-radius:0 0 10px 10px')
        ->and($header['cells'][1])->not->toContain('border-top')->not->toContain($accent)
        // One ground for both, which is what makes them one card.
        ->and($header['cells'][0])->toContain('background:' . EmailPalette::light()->surface)
        ->and($header['cells'][1])->toContain('background:' . EmailPalette::light()->surface)
        ->and($header['words'])->toBe('Acme & Sons')
        ->and(strpos($html, 'Thanks, Sam.'))->toBeGreaterThan(strpos($html, 'Acme &amp; Sons'));
});

// FR-033: a width limit alone let a square logo be drawn 180 pixels tall. The fitted size is
// written as the image's own attributes, which is all Outlook on Windows reads, and as a
// width in its style with the height left to follow.
it('writes a logo of any shape at a size inside 180 by 48, with its proportions kept', function (int $width, int $height, string $drawnWidth, string $drawnHeight) {
    $logo = emailHeader(acmeEmail(brand: acmeBrand('https://acme.test/logo.png')->withLogoSize($width, $height)))['logo'];

    expect($logo['src'])->toBe('https://acme.test/logo.png')
        ->and([$logo['width'], $logo['height']])->toBe([$drawnWidth, $drawnHeight])
        ->and($logo['style'])->toContain('width:' . $drawnWidth . 'px')->toContain('height:auto')
        // No height in the style: with images not shown it would cut the name off.
        ->and($logo['style'])->not->toContain('max-height');
})->with([
    'square' => [600, 600, '48', '48'],
    'wide' => [1000, 200, '180', '36'],
    'very wide' => [1800, 100, '180', '10'],
    'tall' => [100, 400, '12', '48'],
    'already small: never enlarged' => [120, 40, '120', '40'],
    'one pixel wide' => [1, 5000, '1', '48'],
    'one pixel tall' => [5000, 1, '180', '1'],
]);

// FR-009: the header carries the site's name where images are not shown. A mark's box is 48
// pixels wide and holds no name, and Outlook shows no image from a sender it does not know.
it('writes the name beside a mark as text, so that it is there when images are not shown', function () {
    $header = emailHeader(acmeEmail(brand: acmeBrand('https://acme.test/mark.png')->withLogoSize(600, 600)));

    expect($header['words'])->toBe('Acme & Sons')
        // Said once: the image beside the name is not read out as the name again.
        ->and($header['logo']['alt'])->toBe('');
});

it('lets a logo that is the name written out stand alone, with the name standing in for it', function () {
    $header = emailHeader(acmeEmail(brand: acmeBrand('https://acme.test/wordmark.png')->withLogoSize(1000, 200)));

    expect($header['words'])->toBe('')
        ->and($header['logo']['alt'])->toBe('Acme & Sons');
});

it('reads a logo at least twice as wide as it is tall as the name, and a squarer one as a mark', function (int $width, int $height, bool $isItsName) {
    expect(acmeBrand('https://acme.test/logo.png')->withLogoSize($width, $height)->logoIsItsName())->toBe($isItsName);
})->with([
    'square' => [600, 600, false],
    'tall' => [100, 400, false],
    'a little wider than tall' => [199, 100, false],
    'twice as wide' => [200, 100, true],
    'a line of lettering' => [1000, 120, true],
    'of no known size' => [0, 0, false],
]);

it('places the mark and the name from the side a right-to-left site reads from', function () {
    $html = acmeEmail(brand: acmeBrand('https://acme.test/mark.png', 'rtl')->withLogoSize(600, 600));

    expect($html)->toContain('<table role="presentation" dir="rtl" align="right"')
        // The space between them is on the side the name is on.
        ->and($html)->toContain('padding-left:12px');
});

// A size nothing is known about: no attributes, since a height given to an image smaller
// than it would enlarge it where attributes are read.
it('limits a logo whose size is not known by its style alone, and gives it no size of its own', function () {
    $logo = emailHeader(acmeEmail(brand: acmeBrand('https://acme.test/logo.png')))['logo'];

    expect($logo)->not->toHaveKey('width')->not->toHaveKey('height')
        ->and($logo['style'])->toContain('max-width:180px')->toContain('max-height:48px')
        ->and($logo['alt'])->toBe('Acme & Sons');
});

it('takes a size as a width and a height or as none, however the brand was made', function () {
    $brand = acmeBrand('https://acme.test/logo.png')->withLogoSize(600, 300);
    $half = new EmailBrand('Acme', 'https://acme.test/', '', 'ltr', 'en', '', EmailPalette::light(), EmailPalette::dark(), 600, 0);

    expect([$brand->withFooter('Acme')->logoWidth, $brand->withFooter('Acme')->logoHeight])->toBe([600, 300])
        ->and($brand->withPalettes(EmailPalette::light(), EmailPalette::dark())->logoBox())->toBe([96, 48])
        ->and([$half->logoWidth, $half->logoHeight])->toBe([0, 0])
        ->and(acmeBrand()->withLogoSize(-5, 40)->logoBox())->toBeNull();
});

it('does not trust a logo address into an attribute', function () {
    $html = acmeEmail(brand: acmeBrand('https://acme.test/a.png" onerror="steal()'));

    expect($html)->not->toContain('onerror="steal()"');
});

it('carries the message once, and a footer that names the site', function () {
    $html = acmeEmail('<p>Thanks, Sam.</p><ul><li>One</li></ul>');

    expect(substr_count($html, 'Thanks, Sam.'))->toBe(1)
        ->and($html)->toContain('<li')->toContain('One')
        ->and($html)->toMatch('#This message was sent by <a [^>]*href="https://acme.test/">Acme &amp; Sons</a>\.#');
});

it('says a site its own footer line', function () {
    $html = acmeEmail(brand: acmeBrand(footer: 'Acme & Sons · 12 High Street <b>'));

    expect($html)->toContain('Acme &amp; Sons · 12 High Street &lt;b&gt;')
        ->and($html)->not->toContain('This message was sent by');
});

it('escapes the subject into the document title', function () {
    expect(acmeEmail())->toContain('<title>Re: your &lt;message&gt;</title>');
});

it('reads right to left on a right-to-left site, and lets each paragraph take its own direction', function () {
    $rtl = acmeEmail(brand: acmeBrand(direction: 'rtl'));

    expect($rtl)->toContain('<html lang="en-GB" dir="rtl"')
        ->and($rtl)->toContain('text-align:right')
        ->and($rtl)->not->toContain('text-align:left')
        ->and($rtl)->toContain('<p dir="auto"')
        ->and(acmeEmail())->toContain('<html lang="en-GB" dir="ltr"')->toContain('text-align:left');
});

it('holds a phone and a wide screen: fluid, and never wider than 600', function () {
    $html = acmeEmail();

    expect($html)->toContain('name="viewport"')
        ->and($html)->toContain('width="100%"')
        ->and($html)->toContain('max-width:600px')
        // No width in pixels but the one measure: a fixed width is what scrolls sideways on a phone.
        ->and(preg_match_all('/width[:=]"?\s*\d+(px|")/', $html, $fixed))->toBe(1)
        ->and($fixed[0])->toBe(['width:600px']);
});

it('declares both colour schemes, and draws the light one for a client that honours neither', function () {
    $html = acmeEmail();
    $light = EmailPalette::light();
    $dark = EmailPalette::dark();

    expect($html)->toContain('<meta name="color-scheme" content="light dark"')
        ->and($html)->toContain('@media (prefers-color-scheme: dark)')
        ->and($html)->toContain('background:' . $light->surface)
        ->and($html)->toContain('color:' . $light->text)
        ->and($html)->toContain($dark->surface)
        ->and($html)->toContain($dark->text);
});

it('styles what a message holds, because a mail client reads no style sheet for it', function () {
    $html = acmeEmail('<p>Hello</p><ol><li>First</li></ol><p><a href="https://example.com/offer">The offer</a></p>');

    expect($html)->toMatch('/<p dir="auto" class="cx-text" style="[^"]*margin:0 0 16px/')
        ->and($html)->toMatch('/<ol style="[^"]+"/')
        ->and($html)->toMatch('/<li dir="auto" class="cx-text" style="[^"]+"/')
        ->and($html)->toMatch('/<a class="cx-link" style="[^"]*color:' . EmailPalette::light()->link . '[^"]*" href="https:\/\/example.com\/offer">The offer<\/a>/');
});

it('writes a message out as plain text: paragraphs, lists and links', function () {
    $text = PlainText::of(
        '<p>Hello <strong>Salma</strong>,</p>' . "\n"
        . '<p>We offer:<br />' . "\n" . 'two things.</p>'
        . '<ul><li>One</li><li>Two &amp; a half</li></ul>'
        . '<ol><li>First</li><li>Second</li></ol>'
        . '<p>See <a href="https://example.com/offer">the offer</a> or <a href="mailto:us@example.com">us@example.com</a>.</p>',
    );

    expect($text)->toBe(
        "Hello Salma,\n\n"
        . "We offer:\ntwo things.\n\n"
        . "- One\n- Two & a half\n\n"
        . "1. First\n2. Second\n\n"
        . 'See the offer (https://example.com/offer) or us@example.com.',
    );
});

// As WordPress lays a reply out before it gets here: a line ending between every two tags.
// The first version turned each into a space, and every list item after the first was set in.
it('writes a list out the same however its markup was laid out', function () {
    $text = PlainText::of("<p>Intro</p>\n<ul>\n<li>One</li>\n<li>Two</li>\n</ul>\n<ol>\n<li>First</li>\n<li>Second</li>\n</ol>\n<p>After<br />\nthe lists</p>\n");

    expect($text)->toBe("Intro\n\n- One\n- Two\n\n1. First\n2. Second\n\nAfter\nthe lists");
});

it('sets a list inside a list in under its item', function () {
    $text = PlainText::of('<ul><li>One<ul><li>Inner A</li><li>Inner B</li></ul></li><li>Two<ol><li>Step</li></ol></li></ul><p>End</p>');

    expect($text)->toBe("- One\n  - Inner A\n  - Inner B\n- Two\n  1. Step\n\nEnd");
});

it('does not write a link out twice where its text is its address', function () {
    $text = PlainText::of('<p><a href="https://example.com/a?b=1&#038;c=2">https://example.com/a?b=1&amp;c=2</a> and <a href="https://example.com/x">example.com/x</a></p>');

    expect($text)->toBe('https://example.com/a?b=1&c=2 and example.com/x');
});

// A pattern that reads UTF-8 answers nothing for bytes that are not: the plain-text part of the
// email would have left empty while the HTML held the message.
it('keeps the words of a message that holds a byte that is not text', function () {
    $text = PlainText::of("<p>Caf\xE9 au lait</p><p>Second line</p>");

    expect(mb_check_encoding($text, 'UTF-8'))->toBeTrue()
        ->and($text)->toContain('au lait')
        ->and($text)->toContain('Second line');
});

it('leaves no markup and no entity in the plain text', function () {
    $text = PlainText::of('<p>5 &lt; 6 &nbsp;and&nbsp; <em>so</em> on</p><p></p><p>&copy; Acme</p>');

    expect($text)->toBe("5 < 6  and  so on\n\n© Acme");
});

// Found in the browser, not here: the first version read the text as bytes, took the second byte
// of "م" for a line ending, and a development site then failed to keep the email at all.
it('writes Arabic out as the text it was', function () {
    $text = PlainText::of("<p>مرحبا سلمى، سنتصل بك غدا.</p>\n<ul><li>أولا</li><li>ثانيا</li></ul><p>م</p>");

    expect(mb_check_encoding($text, 'UTF-8'))->toBeTrue()
        ->and($text)->toBe("مرحبا سلمى، سنتصل بك غدا.\n\n- أولا\n- ثانيا\n\nم");
});

it('composes a reply as the designed document and the same words in plain text', function () {
    // The site's own values are read from WordPress in SiteEmailBrandTest, against WordPress.
    $site = new class() implements EmailBrandSource {
        public function brand(): EmailBrand
        {
            return EmailBrand::standard('Acme & Sons', 'https://acme.test/', 'https://acme.test/icon.png', 'rtl', 'ar');
        }
    };

    $email = (new ReplyComposer(new StandardEmailDesign(), $site))->compose(
        'sam@example.com',
        'Re: your message',
        '<p>Thanks, Sam.</p><p>See <a href="https://example.com/offer">the offer</a>.</p>',
    );

    expect($email->html)->toContain('<html lang="ar" dir="rtl"')
        ->and($email->html)->toContain('<img src="https://acme.test/icon.png" alt="Acme &amp; Sons"')
        ->and($email->html)->toContain('Thanks, Sam.')
        ->and($email->text)->toBe(
            "Thanks, Sam.\n\nSee the offer (https://example.com/offer).\n\n-- \n"
            . 'This message was sent by Acme & Sons (https://acme.test/).',
        );
});
