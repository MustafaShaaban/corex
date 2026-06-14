<?php

/**
 * Unit tests for the control-panel per-domain status (spec 044: FR-002, US1).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\ControlPanel\ControlPanelStatus;
use Corex\Config\ControlPanel\DomainStatus;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->panel = new ControlPanelStatus();
});

/** @return array<string,DomainStatus> domain => status */
function statusesFor(ControlPanelStatus $panel, array $values, array $failedTests = []): array
{
    $byDomain = [];
    foreach ($panel->domains($values, $failedTests) as $domain) {
        $byDomain[$domain->domain] = $domain;
    }

    return $byDomain;
}

it('marks mail needs_setup when the from-address is empty, configured when set', function () {
    $empty = statusesFor($this->panel, ['mail.from.address' => '']);
    $set   = statusesFor($this->panel, ['mail.from.address' => 'hi@example.com']);

    expect($empty['mail']->status)->toBe(DomainStatus::NEEDS_SETUP)
        ->and($empty['mail']->missing)->not->toBeEmpty()
        ->and($set['mail']->status)->toBe(DomainStatus::CONFIGURED);
});

it('treats honeypot and none captcha as configured (no keys needed)', function () {
    expect(statusesFor($this->panel, ['captcha.driver' => 'honeypot'])['captcha']->status)->toBe(DomainStatus::CONFIGURED)
        ->and(statusesFor($this->panel, ['captcha.driver' => 'none'])['captcha']->status)->toBe(DomainStatus::CONFIGURED);
});

it('marks a key-requiring captcha driver needs_setup until both keys are set', function () {
    $missing = statusesFor($this->panel, ['captcha.driver' => 'recaptcha', 'captcha.site_key' => '', 'captcha.secret' => '']);
    $partial = statusesFor($this->panel, ['captcha.driver' => 'recaptcha', 'captcha.site_key' => 'abc', 'captcha.secret' => '']);
    $full    = statusesFor($this->panel, ['captcha.driver' => 'recaptcha', 'captcha.site_key' => 'abc', 'captcha.secret' => 'xyz']);

    expect($missing['captcha']->status)->toBe(DomainStatus::NEEDS_SETUP)
        ->and($partial['captcha']->status)->toBe(DomainStatus::NEEDS_SETUP)
        ->and($full['captcha']->status)->toBe(DomainStatus::CONFIGURED);
});

it('treats forms, brand, and insights as configured by default (optional config)', function () {
    $s = statusesFor($this->panel, []);

    expect($s['forms']->status)->toBe(DomainStatus::CONFIGURED)
        ->and($s['brand']->status)->toBe(DomainStatus::CONFIGURED)
        ->and($s['insights']->status)->toBe(DomainStatus::CONFIGURED);
});

it('marks a domain error when a recorded test failed', function () {
    $s = statusesFor($this->panel, ['captcha.driver' => 'recaptcha', 'captcha.site_key' => 'a', 'captcha.secret' => 'b'], ['captcha' => true]);

    expect($s['captcha']->status)->toBe(DomainStatus::ERROR);
});

it('gives every domain a label and a setup link', function () {
    foreach ($this->panel->domains([]) as $domain) {
        expect($domain->label)->not->toBe('')
            ->and($domain->setupLink)->not->toBe('');
    }
});
