<?php

/**
 * Unit tests for the corex/modal renderer (spec 054, US3) — the one justified new DLS block.
 * Renders a trigger + native <dialog> with a labelled heading + close, escaped and token-only.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\ModalRenderer;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    // Real escaping so the escaping test observes it (passthrough would hide it).
    Functions\when('esc_html')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    Functions\when('esc_attr')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    $this->renderer = new ModalRenderer();
});

it('renders a trigger button and a dialog labelled by the title', function () {
    $html = $this->renderer->render(
        ['title' => 'Terms', 'triggerLabel' => 'Read terms', 'content' => 'Hello'],
        '',
        (object) [],
    );

    expect($html)->toContain('class="corex-modal__trigger"')
        ->and($html)->toContain('aria-haspopup="dialog"')
        ->and($html)->toContain('<dialog')
        ->and($html)->toContain('aria-labelledby=')
        ->and($html)->toContain('Read terms')
        ->and($html)->toContain('Terms')
        ->and($html)->toContain('corex-modal__close');
});

it('escapes the title and trigger label', function () {
    $html = $this->renderer->render(
        ['title' => '<script>x</script>', 'triggerLabel' => '<b>y</b>', 'content' => 'ok'],
        '',
        (object) [],
    );

    expect($html)->not->toContain('<script>x</script>')
        ->and($html)->not->toContain('<b>y</b>');
});

it('renders nothing when there is no title and no content', function () {
    expect($this->renderer->render(['title' => '', 'content' => ''], '', (object) []))->toBe('');
});

it('defaults the trigger label when none is given', function () {
    $html = $this->renderer->render(['title' => 'Hi', 'content' => 'x'], '', (object) []);

    expect($html)->toContain('Open'); // the default trigger label
});

it('is token-only — no hardcoded hex colour in the markup', function () {
    $html = $this->renderer->render(['title' => 'Hi', 'content' => 'x'], '', (object) []);

    expect($html)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/');
});
