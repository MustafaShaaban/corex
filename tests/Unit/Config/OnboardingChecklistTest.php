<?php

/**
 * Unit tests for the dashboard onboarding checklist (spec 044: FR-004, US1).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\ControlPanel\DomainStatus;
use Corex\Config\ControlPanel\OnboardingChecklist;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->checklist = new OnboardingChecklist();
});

function domain(string $name, string $status): DomainStatus
{
    return new DomainStatus($name, ucfirst($name), $status, [], '#corex-domain-' . $name);
}

it('lists only the not-configured domains as steps', function () {
    $domains = [
        domain('brand', DomainStatus::CONFIGURED),
        domain('mail', DomainStatus::NEEDS_SETUP),
        domain('captcha', DomainStatus::ERROR),
    ];

    $steps = $this->checklist->steps($domains);

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->domain)->toBe('mail')
        ->and($steps[0]->done)->toBeFalse()
        ->and($steps[1]->domain)->toBe('captcha');
});

it('reports allSet when every domain is configured', function () {
    $domains = [domain('brand', DomainStatus::CONFIGURED), domain('mail', DomainStatus::CONFIGURED)];

    expect($this->checklist->steps($domains))->toBe([])
        ->and($this->checklist->allSet($domains))->toBeTrue();
});

it('reports not allSet when any domain needs attention', function () {
    $domains = [domain('mail', DomainStatus::NEEDS_SETUP)];

    expect($this->checklist->allSet($domains))->toBeFalse();
});

it('carries a deep link per step', function () {
    $steps = $this->checklist->steps([domain('mail', DomainStatus::NEEDS_SETUP)]);

    expect($steps[0]->link)->toBe('#corex-domain-mail');
});
