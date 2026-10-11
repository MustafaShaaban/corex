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

it('puts the close button in the header and before the body, with a title and without one', function (string $title, string $heading) {
    $html = $this->renderer->render(
        ['title' => $title, 'content' => '<a href="#terms">The terms</a>'],
        '',
        (object) [],
    );

    // The first thing in the dialog to take focus is where a browser opens it: the close button,
    // not the link in what it holds.
    expect($html)->toMatch(
        '~<dialog [^>]*><div class="corex-drawer__header">' . $heading
        . '<button type="button" class="corex-drawer__close"[^>]*>&times;</button></div>'
        . '<div class="corex-drawer__body"><a href="#terms">The terms</a></div></dialog>~',
    );
})->with([
    'a title'  => ['Terms', '<h2 id="[^"]+" class="corex-drawer__title">Terms</h2>'],
    'no title' => ['', ''],
]);

it('names a dialog that has a title by its heading, and by nothing else', function () {
    $html = $this->renderer->render(
        ['title' => 'Terms', 'triggerLabel' => 'Read terms', 'content' => 'Hello'],
        '',
        (object) [],
    );

    expect($html)->toMatch('~<dialog [^>]*aria-labelledby="([^"]+)"[^>]*>.*<h2 id="\1"~s')
        ->and($html)->not->toMatch('~<dialog [^>]*aria-label=~');
});

it('names a dialog that has no title by the label of its trigger', function (string $triggerLabel, string $name) {
    $html = $this->renderer->render(
        ['triggerLabel' => $triggerLabel, 'content' => 'Hello'],
        '',
        (object) [],
    );

    // No heading to point at, so the name is written on the dialog.
    expect($html)->toMatch('~<dialog [^>]*aria-label="' . $name . '"[^>]*>~')
        ->and($html)->not->toContain('aria-labelledby=');
})->with([
    'the label the editor wrote'                    => ['Read terms', 'Read terms'],
    'the label a trigger gets when none is written' => ['', 'Open'],
]);

it('escapes the trigger label where it names the dialog', function () {
    $html = $this->renderer->render(
        ['triggerLabel' => 'Terms" onclick="x', 'content' => 'ok'],
        '',
        (object) [],
    );

    expect($html)->toContain('aria-label="Terms&quot; onclick=&quot;x"');
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
