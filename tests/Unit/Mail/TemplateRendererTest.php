<?php

/**
 * Unit tests for the template renderer (spec US1: FR-003, FR-004, FR-005, SC-003, SC-008).
 *
 * Merge is whitelisted and escaped: unknown paths render empty, markup values are escaped
 * (never live), and the body is wrapped in the brand layout.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\Layout;
use Corex\Email\Template\MailContext;
use Corex\Email\Template\TemplateRenderer;

final class GreetingTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'greeting';
    }

    public function subject(MailContext $context): string
    {
        return 'Hello {{ user.name }}';
    }

    public function body(MailContext $context): string
    {
        return '<p>Hi {{ user.name }}, welcome to {{ site.name }}.</p>';
    }
}

function mailRenderer(): TemplateRenderer
{
    return new TemplateRenderer(new Layout(['name' => 'Acme', 'color' => '#0B1F3B', 'logo' => '']));
}

it('merges whitelisted context into subject and body', function () {
    $rendered = mailRenderer()->render(new GreetingTemplate(), new MailContext([
        'user' => ['name' => 'Mustafa'],
        'site' => ['name' => 'Acme'],
    ]));

    expect($rendered->subject)->toBe('Hello Mustafa')
        ->and($rendered->body)->toContain('Hi Mustafa, welcome to Acme.');
});

it('renders an unknown or out-of-whitelist placeholder as empty', function () {
    $rendered = mailRenderer()->render(new GreetingTemplate(), new MailContext(['user' => []]));

    expect($rendered->body)->toContain('Hi , welcome to .');
});

it('escapes a merge value containing markup so it is not live HTML', function () {
    $rendered = mailRenderer()->render(new GreetingTemplate(), new MailContext([
        'user' => ['name' => '<script>alert(1)</script>'],
        'site' => ['name' => 'Acme'],
    ]));

    expect($rendered->body)->toContain('&lt;script&gt;')
        ->and($rendered->body)->not->toContain('<script>alert(1)</script>');
});

it('wraps the body in the brand layout (RTL-aware full document)', function () {
    $rendered = mailRenderer()->render(new GreetingTemplate(), new MailContext([
        'user' => ['name' => 'X'], 'site' => ['name' => 'Y'],
    ]));

    expect($rendered->body)->toContain('<!DOCTYPE html')
        ->toContain('Acme')      // brand name from the layout
        ->toContain('dir=');     // direction attribute for RTL correctness
});
