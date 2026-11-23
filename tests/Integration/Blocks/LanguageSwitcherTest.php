<?php

/**
 * The language switcher inside WordPress (spec 137, US-NAV-05; FR-021, FR-029 to FR-031, FR-033).
 *
 * - WordPress's registry holds `corex/language-switcher` with the records editor kind and its own
 *   script and style.
 * - The source the container gives is CoreX's own, which returns the site's one language; the block
 *   then prints nothing, and its script and style are not left on the page.
 * - A source bound in its place, as a site or spec 148 would bind one, is what the block reads, and
 *   what an editor set on the block (an anchor, a class, a margin) reaches its root.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\Languages\Language;
use Corex\Blocks\Languages\LanguageSource;
use Corex\Blocks\Languages\WordPressLanguageSource;
use Corex\Boot;

/** Runs `$test` with `$source` bound, and puts the shipped source back after. */
function withLanguageSource(LanguageSource $source, callable $test): void
{
    $container = Boot::app()->container();
    $container->instance(LanguageSource::class, $source);

    try {
        $test();
    } finally {
        $container->instance(LanguageSource::class, new WordPressLanguageSource());
    }
}

function twoLanguages(): LanguageSource
{
    return new class () implements LanguageSource {
        public function languages(): array
        {
            return [
                new Language('en', 'English', 'ltr', home_url('/'), true),
                new Language('ar', 'العربية', 'rtl', home_url('/ar/'), false),
            ];
        }
    };
}

/** The block's script and style handles, as WordPress registered them. */
function languageSwitcherHandles(): array
{
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/language-switcher');

    return [$type->view_script_handles, $type->style_handles];
}

it('FR-033: is registered with its renderer, its own script and style, and no Navigation parent', function () {
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/language-switcher');

    expect($type)->toBeInstanceOf(WP_Block_Type::class)
        ->and($type->title)->toBe('Language switcher')
        ->and($type->parent)->toBeNull()
        ->and($type->attributes['form']['default'])->toBe('menu')
        ->and($type->view_script_handles)->not->toBe([])
        ->and($type->style_handles)->not->toBe([])
        ->and(is_callable($type->render_callback))->toBeTrue();
});

it('FR-029, FR-030: the container gives the source that reads WordPress, with the site\'s one language', function () {
    $source = Boot::app()->container()->make(LanguageSource::class);
    $languages = $source->languages();

    expect($source)->toBeInstanceOf(WordPressLanguageSource::class)
        ->and($languages)->toHaveCount(1)
        ->and($languages[0]->code)->toBe(str_replace('_', '-', determine_locale()))
        ->and($languages[0]->current)->toBeTrue();
});

it('FR-031, FR-033: with one language prints nothing, and leaves neither its script nor its style queued', function () {
    [$scripts, $styles] = languageSwitcherHandles();
    foreach ([...$scripts, ...$styles] as $handle) {
        wp_dequeue_script($handle);
        wp_dequeue_style($handle);
    }

    expect(do_blocks('<!-- wp:corex/language-switcher {"anchor":"languages","form":"toggle"} /-->'))->toBe('');

    foreach ($scripts as $handle) {
        expect(wp_script_is($handle, 'enqueued'))->toBeFalse("{$handle} was queued for an empty block");
    }
    foreach ($styles as $handle) {
        expect(wp_style_is($handle, 'enqueued'))->toBeFalse("{$handle} was queued for an empty block");
    }
});

it('FR-021, FR-029: reads a source bound in its place, and puts what an editor set on its root', function () {
    withLanguageSource(twoLanguages(), function (): void {
        $html = do_blocks(
            '<!-- wp:corex/language-switcher {"form":"toggle","anchor":"languages","className":"site-languages",'
            . '"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} /-->',
        );

        $tag = new WP_HTML_Tag_Processor($html);
        expect($tag->next_tag())->toBeTrue()
            ->and($tag->get_tag())->toBe('DIV')
            ->and($tag->has_class('corex-language-switcher--toggle'))->toBeTrue()
            ->and($tag->has_class('wp-block-corex-language-switcher'))->toBeTrue()
            ->and($tag->has_class('site-languages'))->toBeTrue()
            ->and($tag->get_attribute('id'))->toBe('languages')
            ->and((string) $tag->get_attribute('style'))->toContain('margin-top');

        expect($tag->next_tag('a'))->toBeTrue()
            ->and($tag->get_attribute('lang'))->toBe('ar')
            ->and($tag->get_attribute('href'))->toBe(home_url('/ar/'));

        [$scripts, $styles] = languageSwitcherHandles();
        foreach ($styles as $handle) {
            expect(wp_style_is($handle, 'enqueued'))->toBeTrue("{$handle} was not queued where the block rendered");
        }
    });
});
