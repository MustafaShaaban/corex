<?php

/**
 * Integration test: the mail send lifecycle on real ./wp (spec US4: FR-010, FR-012,
 * SC-005, SC-006). A templated send delivers + logs; a header-injected message is
 * rejected and logged without sending; the corex-core Mailer seam is bound so the
 * Forms listener can delegate to Corex Mail.
 *
 * @package Corex\Tests\Integration\Mail
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Mail;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

function emailLogCount(): int
{
    $query = new WP_Query([
        'post_type'      => 'corex_email_log',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => false,
    ]);

    return (int) $query->found_posts;
}

it('delivers a templated email and records one sent log', function () {
    add_filter('pre_wp_mail', '__return_true');

    $before = emailLogCount();
    $result = Mail::to('user@example.com')
        ->template('contact-notification')
        ->with([
            'submission' => ['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hello'],
            'form'       => ['slug' => 'contact'],
        ])
        ->send();

    expect($result->isSent())->toBeTrue()
        ->and(emailLogCount())->toBe($before + 1);

    remove_all_filters('pre_wp_mail');
});

it('rejects a header-injected message without sending, but logs it', function () {
    $sends = 0;
    add_filter('pre_wp_mail', function ($short) use (&$sends) {
        $sends++;

        return true;
    });

    $before = emailLogCount();
    $result = Mail::to('user@example.com')->subject("Hi\r\nBcc: victim@example.com")->body('<p>x</p>')->send();

    expect($result->status)->toBe('rejected')
        ->and($sends)->toBe(0)                 // nothing handed to wp_mail
        ->and(emailLogCount())->toBe($before + 1); // but the attempt is logged

    remove_all_filters('pre_wp_mail');
});

it('binds the corex-core Mailer seam so Forms can delegate (detect-and-defer)', function () {
    $container = Boot::app()->container();

    expect($container->has(Mailer::class))->toBeTrue();

    add_filter('pre_wp_mail', '__return_true');
    $before = emailLogCount();

    $container->make(Mailer::class)->send(new MailRequest(
        to: ['admin@example.com'],
        templateName: 'contact-notification',
        context: ['submission' => ['name' => 'B', 'email' => 'b@example.com', 'message' => 'Hi'], 'form' => ['slug' => 'contact']],
    ));

    expect(emailLogCount())->toBe($before + 1);

    remove_all_filters('pre_wp_mail');
});
