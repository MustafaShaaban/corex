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

use Corex\Tests\Support\AdministratorsRequest;

/**
 * Draw the shared layout, a template email and a reply in an admin request of a person whose profile language is
 * not the site's, and report what was drawn and what the request was before and after.
 *
 * @return array{
 *     request: array{is_admin: bool, locale: string, is_rtl: bool},
 *     drawn: array{
 *         layout: array{html: string, in_other_language: bool},
 *         template: array{html: string, in_other_language: bool},
 *         reply: array{html: string, in_other_language: bool},
 *     },
 *     afterwards: array{locale: string, is_rtl: bool},
 * }
 */
function emailsDrawnInAnAdminRequest(string $siteLanguage, string $profileLanguage): array
{
    return AdministratorsRequest::on($siteLanguage, $profileLanguage)->run(<<<'PHP'
        $drawn = static fn (string $html): array => [
            'html' => preg_match('/<html[^>]*>/', $html, $found) === 1 ? $found[0] : '',
            'in_other_language' => str_contains($html, '[ar] '),
        ];

        $template = $container->make(\Corex\Email\Template\TemplateRenderer::class)->render(
            $container->make(\Corex\Email\Template\TemplateRegistry::class)->find('contact-notification'),
            new \Corex\Email\Template\MailContext(['submission' => ['name' => 'Salma']]),
        );
        $reply = $container->make(\Corex\Email\Design\ReplyComposer::class)
            ->compose('salma@example.com', 'Subject', '<p>Hello</p>');
        // The frame an Email Studio template is drawn in, with nothing else around it (spec 120).
        $layout = $container->make(\Corex\Email\Design\EmailFrame::class)
            ->draw(new \Corex\Email\Design\EmailContent('Subject', '<p>Body</p>', '', \Corex\Email\Design\EmailKind::MESSAGE));

        return [
            'layout' => $drawn($layout),
            'template' => $drawn($template->body),
            'reply' => $drawn($reply->html),
        ];
        PHP);
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
        // Every one of them in the one frame, which runs the site's way (spec 120).
        ->and($drawn['drawn']['layout']['html'])->toBe(sprintf('<html lang="%s" dir="%s">', $theEmail['lang'], $theEmail['dir']))
        ->and($drawn['drawn']['template'])->toBe(['html' => sprintf('<html lang="%s" dir="%s">', $theEmail['lang'], $theEmail['dir'])] + $theEmail['words'])
        ->and($drawn['drawn']['reply'])->toBe(
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
