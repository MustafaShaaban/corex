<?php

/**
 * Unit tests for spec 055 Free/Core vs Pro boundary readiness.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\FreeProBoundaryDefaults;
use Corex\Cli\Release\FreeProBoundaryItem;
use Corex\Cli\Release\FreeProBoundaryMatrix;
use Corex\Cli\Release\FreeProBoundaryReadinessCheck;
use Corex\Cli\Release\ReadinessFinding;

it('keeps required adoption and security basics in Free/Core', function () {
    $matrix = FreeProBoundaryDefaults::matrix();

    expect($matrix->missingCapabilities([
        'core framework',
        'basic blocks and DLS',
        'basic forms and contact form',
        'basic config and options',
        'basic media fields',
        'basic captcha and honeypot',
        'accessibility',
        'RTL',
        'i18n',
        'basic make:site',
        'basic docs and deployment docs',
    ]))->toBe([])
        ->and($matrix->securityCriticalProCandidates())->toBe([])
        ->and($matrix->itemFor('accessibility')->classification)->toBe('free-core')
        ->and($matrix->itemFor('basic captcha and honeypot')->classification)->toBe('free-core');
});

it('rejects security-critical capabilities classified as Pro candidates', function () {
    expect(fn () => FreeProBoundaryItem::fromArray([
        'capability' => 'security headers',
        'classification' => 'pro-candidate',
        'reason' => 'Security basics must not be paywalled.',
        'securityCritical' => true,
    ]))->toThrow(InvalidArgumentException::class);
});

it('allows advanced commercial capabilities to be Pro candidates', function () {
    $matrix = FreeProBoundaryDefaults::matrix();

    expect($matrix->itemFor('advanced newsletter')->classification)->toBe('pro-candidate')
        ->and($matrix->itemFor('bookings')->classification)->toBe('pro-candidate')
        ->and($matrix->itemFor('white-label admin')->classification)->toBe('pro-candidate')
        ->and($matrix->itemFor('Azure and DevOps automation')->classification)->toBe('pro-candidate');
});

it('reports Free/Core boundary readiness as passing when defaults protect trust basics', function () {
    $finding = (new FreeProBoundaryReadinessCheck())->evaluate(FreeProBoundaryDefaults::matrix());

    expect($finding->category)->toBe('free-pro')
        ->and($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and($finding->blocking)->toBeFalse()
        ->and($finding->evidence)->toContain('free-core:accessibility', 'free-core:basic make:site', 'pro-candidate:bookings');
});

it('reports missing required basics as blocking readiness issues', function () {
    $matrix = FreeProBoundaryMatrix::fromItems([
        FreeProBoundaryItem::fromArray([
            'capability' => 'advanced newsletter',
            'classification' => 'pro-candidate',
            'reason' => 'Advanced segmentation and automation are commercial scope.',
            'securityCritical' => false,
        ]),
    ]);

    $finding = (new FreeProBoundaryReadinessCheck())->evaluate($matrix);

    expect($finding->status)->toBe(ReadinessFinding::STATUS_FAIL)
        ->and($finding->blocking)->toBeTrue()
        ->and($finding->evidence)->toContain('missing:accessibility', 'missing:basic make:site');
});

