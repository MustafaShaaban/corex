<?php

/**
 * Unit tests for the control-panel renderer (spec 044: US1, FR-001..FR-004).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\ControlPanel\ControlPanelStatus;
use Corex\Config\ControlPanel\ControlPanelView;
use Corex\Config\ControlPanel\OnboardingChecklist;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr')->returnArg();
    $this->view = new ControlPanelView(new ControlPanelStatus(), new OnboardingChecklist());
});

it('renders one status card per domain with its status class', function () {
    $html = $this->view->render([]);

    expect($html)->toContain('id="corex-domain-mail"')
        ->and($html)->toContain('id="corex-domain-captcha"')
        ->and($html)->toContain('corex-card is-configured');
});

it('shows the onboarding checklist with a setup link when a domain needs setup', function () {
    $html = $this->view->render(['mail.from.address' => '']);

    expect($html)->toContain('corex-onboarding')
        ->and($html)->toContain('#corex-domain-mail')
        ->and($html)->toContain('How to set this up')
        ->and($html)->not->toContain('is-complete');
});

it('shows the all-set state when every domain is configured', function () {
    $html = $this->view->render([
        'mail.from.address' => 'hi@example.com',
        'captcha.driver'    => 'honeypot',
    ]);

    expect($html)->toContain('corex-onboarding is-complete')
        ->and($html)->toContain('You are all set');
});

it('marks a key-requiring captcha driver without keys as needs_setup on its card', function () {
    $html = $this->view->render(['captcha.driver' => 'recaptcha']);

    expect($html)->toContain('corex-card is-needs_setup" id="corex-domain-captcha"')
        ->and($html)->toContain('Needs setup');
});
