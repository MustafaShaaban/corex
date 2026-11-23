<?php

/**
 * The mega menu item's renderer (spec 137 slice 3; FR-011, FR-012; DECISIONS #691).
 *
 * On the page the item is a list item of the navigation's list holding a button and its panel. The
 * button says whether the panel is open and which panel it controls; the panel is a group named by
 * its button; every copy on a page has its own ids. An item whose panel is missing, empty or already
 * being drawn is not drawn at all, and neither is an item with no label.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\MegaMenuPanels;
use Corex\Ui\Blocks\MegaMenuRenderer;

/** A panel source that answers from a list, and remembers what it was asked. */
function megaMenuRendererPanels(array $markup): MegaMenuPanels
{
    return new class ($markup) implements MegaMenuPanels {
        /** @var list<string> */
        public array $asked = [];

        /** @param array<string,string> $markup */
        public function __construct(private readonly array $markup)
        {
        }

        public function render(string $slug): string
        {
            $this->asked[] = $slug;

            return $this->markup[$slug] ?? '';
        }
    };
}

/** The element of the markup that carries a class, as an associative list of its attributes. */
function megaMenuRendererElement(string $html, string $class): array
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><ul>' . $html . '</ul>', LIBXML_NOERROR);
    libxml_clear_errors();
    $nodes = (new DOMXPath($document))->query(sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class));
    $element = $nodes === false ? null : $nodes->item(0);
    if (! $element instanceof DOMElement) {
        return [];
    }

    $attributes = ['tag' => strtolower($element->tagName), 'text' => trim($element->textContent)];
    foreach ($element->attributes as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
    }

    return $attributes;
}

beforeEach(function () {
    $counter = 0;
    Functions\when('wp_unique_id')->alias(function (string $prefix = '') use (&$counter): string {
        return $prefix . (string) ++$counter;
    });
    Functions\when('sanitize_title')->alias(fn ($text) => strtolower(trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $text), '-')));
    Functions\when('wp_strip_all_tags')->alias(fn ($text) => strip_tags((string) $text));
    // As WordPress escapes: an entity RichText saved (`&amp;`) is not encoded twice.
    Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false));
    Functions\when('esc_attr')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false));
});

it('FR-011: draws a list item holding a button and the panel it controls', function () {
    $renderer = new MegaMenuRenderer(megaMenuRendererPanels(['services' => '<div class="wp-block-template-part"><p>Consulting</p></div>']));

    $html = $renderer->render(['label' => 'Services', 'panel' => 'services'], '', (object) []);
    $item = megaMenuRendererElement($html, 'corex-mega-menu');
    $button = megaMenuRendererElement($html, 'corex-nav__trigger');
    $panel = megaMenuRendererElement($html, 'corex-nav__panel');

    expect($html)->toStartWith('<li ')
        ->and($item['tag'] ?? null)->toBe('li')
        ->and($item['class'] ?? '')->toContain('wp-block-navigation-item')
        ->and($item)->toHaveKey('data-corex-mega-menu')
        ->and($button['tag'] ?? null)->toBe('button')
        ->and($button['type'] ?? null)->toBe('button')
        ->and($button['class'] ?? '')->toContain('wp-block-navigation-item__content')
        ->and($button['class'] ?? '')->toContain('corex-mega-menu__trigger')
        ->and($button['aria-expanded'] ?? null)->toBe('false')
        ->and($button['aria-controls'] ?? null)->toBe($panel['id'] ?? 'no panel id')
        ->and($button['text'] ?? null)->toBe('Services')
        ->and($panel['tag'] ?? null)->toBe('div')
        ->and($panel['role'] ?? null)->toBe('group')
        ->and($panel['aria-labelledby'] ?? null)->toBe($button['id'] ?? 'no button id')
        ->and($panel)->toHaveKey('hidden')
        ->and($panel['text'] ?? null)->toBe('Consulting');
});

it('FR-011: names the label in its own element and draws a caret no reader announces', function () {
    $renderer = new MegaMenuRenderer(megaMenuRendererPanels(['services' => '<p>Panel</p>']));

    $html = $renderer->render(['label' => 'Services', 'panel' => 'services'], '', (object) []);
    $caret = megaMenuRendererElement($html, 'corex-mega-menu__caret');

    expect(megaMenuRendererElement($html, 'corex-mega-menu__label')['text'] ?? null)->toBe('Services')
        ->and($caret['tag'] ?? null)->toBe('svg')
        ->and($caret['aria-hidden'] ?? null)->toBe('true')
        ->and($caret['focusable'] ?? null)->toBe('false');
});

it('FR-011: gives every copy of an item on a page its own ids (the bar and the phone menu)', function () {
    $renderer = new MegaMenuRenderer(megaMenuRendererPanels(['services' => '<p>Panel</p>']));
    $attributes = ['label' => 'Services', 'panel' => 'services'];

    $first = $renderer->render($attributes, '', (object) []);
    $second = $renderer->render($attributes, '', (object) []);
    $ids = [
        megaMenuRendererElement($first, 'corex-nav__trigger')['id'] ?? '',
        megaMenuRendererElement($first, 'corex-nav__panel')['id'] ?? '',
        megaMenuRendererElement($second, 'corex-nav__trigger')['id'] ?? '',
        megaMenuRendererElement($second, 'corex-nav__panel')['id'] ?? '',
    ];

    expect(array_filter($ids))->toHaveCount(4)
        ->and(array_unique($ids))->toHaveCount(4)
        ->and(megaMenuRendererElement($second, 'corex-nav__trigger')['aria-controls'] ?? null)->toBe($ids[3]);
});

it('FR-012: draws nothing, and asks for no panel, when the item names none', function (array $attributes) {
    $panels = megaMenuRendererPanels(['services' => '<p>Panel</p>']);

    expect((new MegaMenuRenderer($panels))->render($attributes, '', (object) []))->toBe('')
        ->and($panels->asked)->toBe([]);
})->with([
    'no panel attribute' => [['label' => 'Services']],
    'an empty panel' => [['label' => 'Services', 'panel' => '']],
    'a panel that is no slug' => [['label' => 'Services', 'panel' => '  ']],
]);

it('FR-012: draws nothing when its panel is missing, empty or already being drawn', function () {
    // The source answers '' for each of the three; the item then has no trigger that opens nothing.
    $panels = megaMenuRendererPanels([]);

    expect((new MegaMenuRenderer($panels))->render(['label' => 'Services', 'panel' => 'services'], '', (object) []))->toBe('')
        ->and($panels->asked)->toBe(['services']);
});

it('draws nothing for an item with no label, so no button is left without a name', function () {
    $panels = megaMenuRendererPanels(['services' => '<p>Panel</p>']);

    expect((new MegaMenuRenderer($panels))->render(['label' => ' <b></b> ', 'panel' => 'services'], '', (object) []))->toBe('')
        ->and($panels->asked)->toBe([]);
});

it('prints the label as text: markup typed into it is dropped and the rest escaped', function () {
    $renderer = new MegaMenuRenderer(megaMenuRendererPanels(['services' => '<p>Panel</p>']));

    $html = $renderer->render(['label' => '<strong>R&amp;D</strong> "labs"', 'panel' => 'services'], '', (object) []);

    expect($html)->not->toContain('<strong>')
        ->and(megaMenuRendererElement($html, 'corex-mega-menu__label')['text'] ?? null)->toBe('R&D "labs"');
});

it('asks for the panel by its slug as WordPress writes one', function () {
    $panels = megaMenuRendererPanels(['our-services' => '<p>Panel</p>']);

    (new MegaMenuRenderer($panels))->render(['label' => 'Services', 'panel' => 'Our Services'], '', (object) []);

    expect($panels->asked)->toBe(['our-services']);
});
