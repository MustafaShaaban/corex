<?php

/**
 * Unit tests for the corex/drawer renderer (spec 068, US9) — a slide-in panel: a trigger + native
 * <dialog> with a labelled heading + close, sliding from a logical edge, escaped and token-only.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\DrawerRenderer;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('esc_html')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    Functions\when('esc_attr')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    $this->renderer = new DrawerRenderer();
});

it('renders a trigger button and a dialog labelled by the title on the chosen side', function () {
    $html = $this->renderer->render(
        ['title' => 'Menu', 'triggerLabel' => 'Open menu', 'content' => 'Hello', 'side' => 'start'],
        '',
        (object) [],
    );

    expect($html)->toContain('class="corex-drawer__trigger"')
        ->and($html)->toContain('aria-haspopup="dialog"')
        ->and($html)->toContain('<dialog')
        ->and($html)->toContain('corex-drawer--start')
        ->and($html)->toContain('aria-labelledby=')
        ->and($html)->toContain('Open menu')
        ->and($html)->toContain('Menu')
        ->and($html)->toContain('corex-drawer__close');
});

it('defaults to the end side and rejects an unknown side', function () {
    $end     = $this->renderer->render(['title' => 'Hi', 'content' => 'x'], '', (object) []);
    $unknown = $this->renderer->render(['title' => 'Hi', 'content' => 'x', 'side' => 'diagonal'], '', (object) []);

    expect($end)->toContain('corex-drawer--end')
        ->and($unknown)->toContain('corex-drawer--end')
        ->and($unknown)->not->toContain('diagonal');
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

it('defaults the trigger label and is token-only (no hardcoded hex)', function () {
    $html = $this->renderer->render(['title' => 'Hi', 'content' => 'x'], '', (object) []);

    expect($html)->toContain('Open')
        ->and($html)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/');
});
