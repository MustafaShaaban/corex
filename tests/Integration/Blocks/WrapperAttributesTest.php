<?php

/**
 * What an editor sets on a CoreX block reaches the page: the anchor, the alignment and the extra class
 * land on the block's root element, through WordPress's own render (spec 136, User Story 1, FR-001 to
 * FR-006; DECISIONS #670). The six form and flow blocks with nothing set print their renderer's bytes.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\Languages\Language;
use Corex\Blocks\Languages\LanguageSource;
use Corex\Blocks\Languages\WordPressLanguageSource;
use Corex\Blocks\WordPressBlockWrapper;
use Corex\Boot;
use Corex\Environment\SiteEnvironment;
use Corex\Forms\Block\FlowBlockRenderer;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Portfolio\Blocks\WpProjectMetaProvider;
use Corex\Tests\Support\CreatedPosts;
use Corex\Ui\Blocks\HeroRenderer;
use Corex\Tests\Support\SavedOption;
use Corex\Tests\Support\WatchedTransients;

/** The blocks whose root is not always their first element name it in block.json (`corex.root`, FR-003). */
const WRAPPER_TEST_NAMED_ROOTS = ['corex/form' => ['corex-flow', 'corex-form']];

function wrapperTestRequest(string $method, string $route, array $payload = [], array $urlParams = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_header('Content-Type', 'application/json');
    $request->set_body((string) wp_json_encode($payload));
    $request->set_url_params($urlParams);

    return $request;
}

/** A flow asking for one email, published at version 1; its id. */
function wrapperTestPublishedFlow(string $slug): int
{
    $controller = Boot::app()->container()->make(FlowController::class);
    $created = $controller->create(wrapperTestRequest('POST', '/corex/v1/flows', [
        'slug' => $slug,
        'name' => 'Wrapper probe',
        'owner_id' => get_current_user_id(),
        'configuration' => [
            'schema' => [['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];
    $controller->publish(wrapperTestRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]));

    return $flowId;
}

/**
 * The smallest attributes each block needs to print something. Flow blocks need a published flow;
 * social share and project meta need a current post, which the test sets.
 *
 * @return array<string,array<string,mixed>>
 */
function wrapperTestMinimalAttributes(int $flowId): array
{
    return [
        'corex/account'           => [],
        'corex/accordion'         => ['items' => [['title' => 'Q', 'content' => 'A']]],
        // A child block, drawn alone here: inside its accordion the page prints it the same way.
        'corex/accordion-item'    => ['question' => 'Q'],
        'corex/alert'           => ['message' => 'M'],
        'corex/badge'             => ['label' => 'L'],
        'corex/breadcrumbs'       => [],
        'corex/carousel'          => ['slides' => [['content' => 'S']]],
        'corex/copyright'         => [],
        'corex/cta'               => ['title' => 'T'],
        'corex/drawer'            => ['title' => 'T'],
        'corex/entity-field'      => ['value' => 'V'],
        'corex/gallery'           => ['images' => [['url' => 'https://example.test/a.jpg', 'alt' => 'A']]],
        'corex/hero'              => ['title' => 'T'],
        'corex/jobs'              => [],
        // Prints only with two languages: the test binds a source that has them (spec 137, FR-031).
        'corex/language-switcher' => ['form' => 'list'],
        'corex/modal'             => ['title' => 'T'],
        'corex/newsletter-signup' => [],
        'corex/posts'             => [],
        'corex/pricing'           => ['plan' => 'P'],
        'corex/project-meta'      => [],
        'corex/projects'          => [],
        'corex/social-share'      => [],
        'corex/stat'              => ['value' => '1'],
        'corex/tabs'              => ['tabs' => [['label' => 'One', 'content' => 'C']]],
        'corex/team'              => ['members' => [['name' => 'N']]],
        'corex/testimonial'       => ['quote' => 'Q'],
        'corex/form'              => ['formSlug' => 'contact'],
        'corex/flow'              => ['flowId' => $flowId],
        'corex/cta-flow'          => ['flowId' => $flowId],
        'corex/subscribe'         => ['flowId' => $flowId],
        'corex/survey'            => ['flowId' => $flowId],
        'corex/success-message'   => ['flowId' => $flowId],
    ];
}

/**
 * The block's root element in its printed markup: the first element carrying one of the named classes,
 * else the first element; null when it printed none.
 *
 * @param list<string> $rootClasses
 */
function wrapperTestRoot(string $html, array $rootClasses = []): ?WP_HTML_Tag_Processor
{
    $tags = new WP_HTML_Tag_Processor($html);
    while ($rootClasses !== [] && $tags->next_tag()) {
        foreach ($rootClasses as $class) {
            if ($tags->has_class($class)) {
                return $tags;
            }
        }
    }

    $tags = new WP_HTML_Tag_Processor($html);

    return $tags->next_tag() ? $tags : null;
}

/**
 * @return list<string> Every `corex/*` block a person can insert, as WordPress has it registered. The
 *                      assistant (`inserter: false`) is not one; it still passes through the wrapper.
 */
function wrapperTestInsertableBlocks(): array
{
    $names = [];
    foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
        if (str_starts_with($name, 'corex/') && ($type->supports['inserter'] ?? true) !== false) {
            $names[] = $name;
        }
    }
    sort($names);

    return $names;
}

beforeEach(function () {
    Boot::app()->container()->make(WpFlowStore::class)->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission', 'post');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mode = get_option(SiteEnvironment::MODE_OPTION, null);
    $this->globalPost = $GLOBALS['post'] ?? null;
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $GLOBALS['post'] = $this->globalPost;
    SavedOption::restore(SiteEnvironment::MODE_OPTION, $this->mode);
    $this->posts->delete();
    $this->transients->restore();
    wp_set_current_user(0);
});

it('FR-006: prints a hero\'s anchor, alignment and class on its section, beside its own classes', function () {
    $html = do_blocks('<!-- wp:corex/hero {"anchor":"about","align":"full","className":"home-hero","title":"T"} /-->');
    $root = wrapperTestRoot($html);

    expect($html)->toStartWith('<section')
        ->and($root?->get_tag())->toBe('SECTION')
        ->and($root?->get_attribute('id'))->toBe('about')
        ->and($root?->has_class('alignfull'))->toBeTrue()
        ->and($root?->has_class('home-hero'))->toBeTrue()
        ->and($root?->has_class('corex-hero'))->toBeTrue()
        ->and($root?->has_class('wp-block-corex-hero'))->toBeTrue();
});

it('FR-004: every CoreX block a person can insert declares the anchor', function () {
    $missing = [];
    foreach (wrapperTestInsertableBlocks() as $name) {
        $type = WP_Block_Type_Registry::get_instance()->get_registered($name);
        if (($type->supports['anchor'] ?? false) !== true) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([]);
});

it('FR-004: the section blocks declare wide and full alignment', function () {
    $sections = ['hero', 'cta', 'team', 'posts', 'gallery', 'tabs', 'accordion', 'carousel', 'newsletter-signup', 'jobs', 'projects', 'testimonial', 'pricing'];
    $missing = [];
    foreach ($sections as $section) {
        $align = WP_Block_Type_Registry::get_instance()->get_registered('corex/' . $section)?->supports['align'] ?? null;
        if (! is_array($align) || array_diff(['wide', 'full'], $align) !== []) {
            $missing[] = $section;
        }
    }

    expect($missing)->toBe([])
        ->and(WP_Block_Type_Registry::get_instance()->get_registered('corex/social-share')->supports['align'])
        ->toBe(['left', 'center', 'right']);
});

it('FR-001, FR-006: an anchor reaches the root of every CoreX block a person can insert', function () {
    $postId = wp_insert_post(['post_title' => 'Wrapper probe', 'post_status' => 'publish', 'post_type' => 'post']);
    update_post_meta($postId, WpProjectMetaProvider::CLIENT, 'Client');
    $GLOBALS['post'] = get_post($postId);
    $minimal = wrapperTestMinimalAttributes(wrapperTestPublishedFlow('wrapper-every-block'));

    // A new block fails here until it is given what it needs to print.
    expect(array_diff(wrapperTestInsertableBlocks(), array_keys($minimal)))->toBe([]);

    // The language switcher prints nothing for the site's one language; give it two for this test.
    $container = Boot::app()->container();
    $container->instance(LanguageSource::class, new class () implements LanguageSource {
        public function languages(): array
        {
            return [
                new Language('en', 'English', 'ltr', home_url('/'), true),
                new Language('ar', 'العربية', 'rtl', home_url('/ar/'), false),
            ];
        }
    });

    $missed = [];
    try {
        foreach (wrapperTestInsertableBlocks() as $name) {
            $html = do_blocks(sprintf('<!-- wp:%s %s /-->', $name, wp_json_encode(['anchor' => 'about'] + $minimal[$name], JSON_UNESCAPED_SLASHES)));
            $root = wrapperTestRoot($html, WRAPPER_TEST_NAMED_ROOTS[$name] ?? []);
            // The id must sit on the block's own element: one that carries a class of CoreX's.
            $own = $root === null ? [] : array_filter(
                explode(' ', (string) $root->get_attribute('class')),
                static fn (string $class): bool => str_starts_with($class, 'corex-'),
            );
            if ($root === null || $root->get_attribute('id') !== 'about' || $own === []) {
                $missed[$name] = substr($html, 0, 160);
            }
        }
    } finally {
        $container->instance(LanguageSource::class, new WordPressLanguageSource());
    }

    expect($missed)->toBe([]);
});

it('FR-005: the six form and flow blocks with nothing set print their renderer\'s bytes', function () {
    $flowId = wrapperTestPublishedFlow('wrapper-bytes');
    $GLOBALS['post'] = null;
    $blocks = [
        'corex/form'            => [FormBlockRenderer::class, ['formSlug' => 'contact']],
        'corex/flow'            => [FlowBlockRenderer::class, ['flowId' => $flowId]],
        'corex/cta-flow'        => [FlowBlockRenderer::class, ['flowId' => $flowId]],
        'corex/subscribe'       => [FlowBlockRenderer::class, ['flowId' => $flowId]],
        'corex/survey'          => [FlowBlockRenderer::class, ['flowId' => $flowId]],
        'corex/success-message' => [FlowBlockRenderer::class, ['flowId' => $flowId]],
    ];

    foreach ($blocks as $name => [$rendererClass, $attributes]) {
        $comment = sprintf('<!-- wp:%s %s /-->', $name, wp_json_encode($attributes));
        $block = new WP_Block(parse_blocks($comment)[0]);
        $raw = Boot::app()->container()->make($rendererClass)->render($block->attributes, '', $block);

        expect($raw)->not->toBe('', $name . ' printed nothing, so the comparison proves nothing')
            ->and(do_blocks($comment))->toBe($raw, $name . ' changed its bytes');
    }
});

it('FR-003: puts a registered form\'s anchor on the form, not on the test-site notice printed before it', function () {
    update_option(SiteEnvironment::MODE_OPTION, SiteEnvironment::STAGING, false);

    $html = do_blocks('<!-- wp:corex/form {"formSlug":"contact","anchor":"contact-us"} /-->');
    $first = wrapperTestRoot($html);
    $form = wrapperTestRoot($html, ['corex-form']);

    expect($first?->has_class('corex-flow-notice'))->toBeTrue('the markup starts with the test-site notice')
        ->and($first?->get_attribute('id'))->toBeNull()
        ->and($form?->get_tag())->toBe('FORM')
        ->and($form?->get_attribute('id'))->toBe('contact-us');
})->skip(
    fn (): bool => ! class_exists(\Corex\Forms\Block\NoticePanel::class),
    'this release line has no notice panel before a form',
);

it('FR-003: puts a flow-backed form\'s anchor and class on its section, not on the form inside it', function () {
    $flowId = wrapperTestPublishedFlow('wrapper-form-flow');

    $html = do_blocks(sprintf('<!-- wp:corex/form {"source":"flow","flowId":%d,"anchor":"apply","className":"home-apply"} /-->', $flowId));
    $first = wrapperTestRoot($html);
    $form = wrapperTestRoot($html, ['corex-form']);

    expect($first?->get_tag())->toBe('SECTION')
        ->and($first?->has_class('corex-flow'))->toBeTrue()
        ->and($first?->get_attribute('id'))->toBe('apply')
        ->and($first?->has_class('home-apply'))->toBeTrue()
        ->and($form?->get_tag())->toBe('FORM')
        ->and($form?->get_attribute('id'))->toBeNull()
        ->and($form?->has_class('home-apply'))->toBeFalse();
});

it('FR-003: puts a paused form\'s anchor on the notice that stands in its place', function () {
    update_option(SiteEnvironment::MODE_OPTION, 'forms-paused', false);
    wp_set_current_user(0); // An administrator passes the pause and gets the real form.

    $html = do_blocks('<!-- wp:corex/form {"formSlug":"contact","anchor":"contact-us"} /-->');
    $first = wrapperTestRoot($html);

    expect(wrapperTestRoot($html, ['corex-form'])?->has_class('corex-form'))->toBeFalse('no form is printed while paused')
        ->and($first?->has_class('corex-flow-notice'))->toBeTrue()
        ->and($first?->get_attribute('id'))->toBe('contact-us');
})->skip(
    fn (): bool => ! class_exists(\Corex\Forms\Block\NoticePanel::class),
    'this release line has no notice panel before a form',
);

it('FR-006a: the editor\'s preview request gets the markup without the merge, the page still gets it', function () {
    $attributes = ['title' => 'T', 'anchor' => 'about', 'className' => 'home-hero'];
    $request = new WP_REST_Request('GET', '/wp/v2/block-renderer/corex/hero');
    $request->set_param('context', 'edit');
    $request->set_param('attributes', $attributes);

    $response = rest_do_request($request);
    $preview = wrapperTestRoot((string) ($response->get_data()['rendered'] ?? ''));
    $page = wrapperTestRoot(do_blocks('<!-- wp:corex/hero ' . wp_json_encode($attributes) . ' /-->'));

    expect(current_user_can('edit_posts'))->toBeTrue()
        ->and($response->get_status())->toBe(200)
        ->and($preview?->has_class('corex-hero'))->toBeTrue('the preview drew the hero')
        ->and($preview?->get_attribute('id'))->toBeNull()
        ->and($preview?->has_class('home-hero'))->toBeFalse()
        ->and($preview?->has_class('wp-block-corex-hero'))->toBeFalse()
        ->and($page?->get_attribute('id'))->toBe('about')
        ->and($page?->has_class('home-hero'))->toBeTrue();
});

it('FR-002: a render callback called with no block being rendered returns the renderer\'s markup, with no warning', function () {
    $callback = WP_Block_Type_Registry::get_instance()->get_registered('corex/hero')->render_callback;
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $html = $callback(['title' => 'T', 'anchor' => 'about'], '', (object) []);
    } finally {
        restore_error_handler();
    }

    expect(WP_Block_Supports::$block_to_render)->toBeNull()
        ->and($warnings)->toBe([])
        ->and($html)->toBe(Boot::app()->container()->make(HeroRenderer::class)->render(['title' => 'T', 'anchor' => 'about'], '', (object) []));
});

it('US1 scenario 5: a block whose renderer prints nothing prints nothing, anchor or not', function () {
    expect(do_blocks('<!-- wp:corex/hero {"anchor":"about","align":"full","className":"home-hero"} /-->'))->toBe('');
});

// The collaborator itself, with real markup and WordPress's own tag processor (FR-002).

it('FR-002: returns markup untouched, byte for byte, when there is nothing to merge', function () {
    $markup = "<div class=\"corex-stat\"  data-x='1'><p>Text</p></div>\n";

    expect((new WordPressBlockWrapper())->merge($markup, ''))->toBe($markup);
});

it('FR-002: adds each class once, appends a style, and keeps an attribute the root already has', function () {
    $merged = (new WordPressBlockWrapper())->merge(
        '<div class="corex-alert" id="own" role="alert" style="color:var(--x)"><p>M</p></div>',
        'class="wp-block-corex-alert corex-alert home-note" id="about" aria-label="Note" style="margin-top:1rem"',
    );
    $root = wrapperTestRoot($merged);

    expect($root?->get_attribute('class'))->toBe('corex-alert wp-block-corex-alert home-note')
        ->and($root?->get_attribute('id'))->toBe('own')
        ->and($root?->get_attribute('aria-label'))->toBe('Note')
        ->and($root?->get_attribute('style'))->toBe('color:var(--x);margin-top:1rem')
        ->and($merged)->toEndWith('><p>M</p></div>');
});

it('FR-003: puts the attributes on the first element with any named class, or on the first element when none has one', function () {
    $wrapper = new WordPressBlockWrapper();
    $roots = ['corex-flow', 'corex-form'];
    $notice = '<div class="corex-flow-notice"><p>Test site</p></div>';

    $afterNotice = $wrapper->merge($notice . '<form class="corex-form"></form>', 'id="about"', $roots);
    $flow = $wrapper->merge('<section class="corex-flow"><form class="corex-form"></form></section>', 'id="about"', $roots);
    $noticeOnly = $wrapper->merge($notice, 'id="about"', $roots);

    // WordPress's tag processor writes a new attribute right after the tag name. Whole class names
    // match: `corex-flow-notice` is not `corex-flow`.
    expect($afterNotice)->toBe($notice . '<form id="about" class="corex-form"></form>')
        ->and($flow)->toBe('<section id="about" class="corex-flow"><form class="corex-form"></form></section>')
        ->and($noticeOnly)->toBe('<div id="about" class="corex-flow-notice"><p>Test site</p></div>');
});

it('FR-002: leaves markup with no element in it untouched', function () {
    expect((new WordPressBlockWrapper())->merge('Plain text', 'id="about"'))->toBe('Plain text');
});
