<?php

/**
 * Unit tests for the form block renderer (spec US4: FR-013, FR-015, SC-005, SC-007).
 *
 * Accessible, token-only, i18n markup: every field has an associated label, required
 * markers, a nonce carrier, and the honeypot — with no hardcoded colors or sizes.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\FormRegistry;
use Corex\Forms\Forms\ContactForm;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;

function renderContactForm(array $attributes): string
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    // Mirror WP's attribute encoding so the embedded JSON is realistically escaped.
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('sanitize_key')->alias(fn (string $key): string => strtolower($key));
    Functions\when('wp_create_nonce')->justReturn('test-nonce');
    // The renderer conditionally enqueues the shared runtime (spec 043); no-op them here.
    Functions\when('wp_enqueue_script')->justReturn(null);
    Functions\when('wp_enqueue_style')->justReturn(null);
    Functions\when('rest_url')->alias(fn (string $path): string => 'https://example.test/wp-json/' . $path);
    Functions\when('wp_json_encode')->alias(static fn ($data): string => (string) json_encode($data));

    $registry = new FormRegistry();
    $registry->register(new ContactForm());

    $renderer = new FormBlockRenderer(
        $registry,
        new SchemaResolver(new RuleRegistry()),
        new SchemaExporter(),
        new FieldRenderer(),
    );

    return $renderer->render($attributes, '', (object) []);
}

it('renders every field with an associated label, required marker, nonce, and honeypot', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->toContain('<label for="corex-contact-name"')
        ->toContain('id="corex-contact-name"')
        ->toContain('<label for="corex-contact-email"')
        ->toContain('id="corex-contact-email"')
        ->toContain('type="email"')
        ->toContain('<label for="corex-contact-message"')
        ->toContain('id="corex-contact-message"')
        ->toContain('<textarea')
        ->toContain('aria-required="true"')        // required fields marked for AT
        ->toContain('data-corex-nonce="test-nonce"') // nonce carried for the JS X-WP-Nonce header
        ->toContain('name="corex_hp"');             // honeypot present
});

it('embeds the exported schema and accessible error regions for the shared validator', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->toContain('data-corex-schema=')                 // schema exported to the client
        ->toContain('&quot;name&quot;:&quot;email&quot;') // a known field is in the embedded JSON
        ->toContain('data-corex-field="email"')           // field wrapper hook for JS targeting
        ->toContain('id="corex-contact-email-error"')     // per-field error region
        ->toContain('aria-describedby="corex-contact-email-error"'); // input points at it
});

it('uses no hardcoded colors or sizes in the rendered markup (token-only)', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->not->toMatch('/#[0-9a-fA-F]{3,6}\b/') // no hex colors
        ->not->toContain('px');                  // no pixel sizes
});

it('renders nothing for an unknown form slug (non-fatal)', function () {
    expect(renderContactForm(['formSlug' => 'does-not-exist']))->toBe('');
});
