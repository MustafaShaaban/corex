<?php

/**
 * Unit tests for the pure Email Studio view model (spec 063, Phase 2). No WordPress.
 * Contract: truthful engine state — real templates only, honest env-derived delivery advisory.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Email\EmailStudio;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('reports the active engine, its real templates, and a live-sending advisory in production', function () {
    $overview = (new EmailStudio())->overview(
        true,
        ['contact-notification', 'newsletter-confirmation'],
        ['mode' => 'production', 'label' => 'Production'],
    );

    expect($overview['active'])->toBeTrue()
        ->and($overview['templateCount'])->toBe(2)
        ->and($overview['hasTemplates'])->toBeTrue()
        ->and($overview['delivery']['tone'])->toBe(EmailStudio::TONE_WARNING);
});

it('advises development delivery in a non-production environment', function () {
    $overview = (new EmailStudio())->overview(true, [], ['mode' => 'local', 'label' => 'Local']);

    expect($overview['delivery']['tone'])->toBe(EmailStudio::TONE_INFO)
        ->and($overview['hasTemplates'])->toBeFalse()
        ->and($overview['templateCount'])->toBe(0);
});

it('reports the engine inactive without fabricating templates when the add-on is off', function () {
    $overview = (new EmailStudio())->overview(false, [], ['mode' => 'production', 'label' => 'Production']);

    expect($overview['active'])->toBeFalse()
        ->and($overview['templates'])->toBe([]);
});

it('flags staging delivery as a warning to protect real contacts', function () {
    $overview = (new EmailStudio())->overview(true, ['x'], ['mode' => 'staging', 'label' => 'Staging']);

    expect($overview['delivery']['tone'])->toBe(EmailStudio::TONE_WARNING)
        ->and($overview['delivery']['label'])->not->toBe('');
});

it('derives the variable browser from placeholders in real registered template sources', function () {
    $variables = (new EmailStudio())->variables([
        'contact' => ['Subject', '<p>{{ submission.name }} {{ submission.email }}</p>'],
        'receipt' => ['Hi {{ recipient.name }}', '<p>{{ submission.email }}</p>'],
    ]);

    expect($variables)->toBe([
        'submission.name'  => ['contact'],
        'submission.email' => ['contact', 'receipt'],
        'recipient.name'   => ['receipt'],
    ]);
});
