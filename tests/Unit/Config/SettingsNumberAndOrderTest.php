<?php

/**
 * Two Settings field types the Assistant's screen needs (spec 129, FR-018 and FR-024): a whole
 * number, refused unless it is one, and an order of choices the owner rearranges, saved as their
 * keys joined by commas and cleaned rather than refused.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\FieldState;
use Corex\Config\Settings\SettingsControls;
use Corex\Config\Settings\SettingsSanitizer;

const COREX_TEST_SERVICES = ['gemini' => 'Google Gemini', 'groq' => 'Groq', 'openai' => 'OpenAI'];

beforeEach(function () {
    foreach (['__', 'esc_attr', 'esc_html', 'esc_url', 'esc_html__', 'esc_attr__'] as $function) {
        Functions\when($function)->returnArg();
    }
});

it('takes a whole number of 0 or more and refuses anything else', function (string $typed, ?string $saved) {
    expect((new SettingsSanitizer())->sanitize($typed, 'number'))->toBe($saved);
})->with([
    'a number' => ['20', '20'],
    'spaced' => [' 50 ', '50'],
    'zero' => ['0', '0'],
    'leading zeros' => ['007', '7'],
    'words after it' => ['20 a day', null],
    'negative' => ['-5', null],
    'a fraction' => ['2.5', null],
    'too long to be a count' => ['9999999999', null],
    'blank, which keeps the default' => ['', ''],
]);

it('draws a number as a number box that says what is stored', function () {
    $html = (new SettingsControls())->control('assistant.paid_cap', ['label' => 'Paid answers a day', 'type' => 'number'], FieldState::plain('50'));

    expect($html)->toContain('type="number" min="0" step="1" inputmode="numeric" value="50"')
        ->and($html)->toContain('data-corex-saved="50"');
});

it('cleans an order: known keys once each in the order given, then the missing ones', function (string $value, array $keys) {
    expect(SettingsControls::ordered(COREX_TEST_SERVICES, $value))->toBe($keys);
})->with([
    'nothing stored is the default order' => ['', ['gemini', 'groq', 'openai']],
    'rearranged' => ['openai,gemini,groq', ['openai', 'gemini', 'groq']],
    'one moved, the rest follow' => ['groq', ['groq', 'gemini', 'openai']],
    'an unknown key dropped' => ['mystery,groq,gemini', ['groq', 'gemini', 'openai']],
    'a repeated key once' => ['groq,groq,openai', ['groq', 'openai', 'gemini']],
    'spaces around keys' => [' openai , groq ', ['openai', 'groq', 'gemini']],
]);

it('saves an order as the cleaned keys, never refusing it', function () {
    expect((new SettingsSanitizer())->sanitize('openai,nope,gemini', 'order', COREX_TEST_SERVICES))->toBe('openai,gemini,groq');
});

it('draws an order as a named list with a move up and a move down per item, and the input that is saved', function () {
    $html = (new SettingsControls())->control(
        'assistant.order',
        ['label' => 'Order the services are asked in', 'type' => 'order', 'options' => COREX_TEST_SERVICES],
        new FieldState('groq,gemini', 'groq,gemini'),
    );

    expect($html)->toContain('<ol class="corex-order" data-corex-order="assistant_order" aria-label="Order the services are asked in">')
        ->and($html)->toContain('type="hidden" id="assistant_order" name="assistant_order" value="groq,gemini,openai" data-corex-saved="groq,gemini,openai"')
        ->and(strpos($html, '>Groq<'))->toBeLessThan(strpos($html, '>Google Gemini<'))
        ->and($html)->toContain('aria-label="Move Groq up" disabled')
        ->and($html)->toContain('aria-label="Move OpenAI down" disabled')
        ->and($html)->toContain('aria-label="Move Google Gemini up">')
        ->and($html)->toContain('aria-live="polite"');
});

it('lets nothing in an order move while its add-on is off', function () {
    $html = (new SettingsControls())->control(
        'assistant.order',
        ['label' => 'Order', 'type' => 'order', 'options' => COREX_TEST_SERVICES],
        FieldState::plain(''),
        true,
    );

    expect(substr_count($html, ' disabled>'))->toBe(6)
        ->and($html)->toContain('data-corex-saved="gemini,groq,openai" disabled');
});
