<?php

/**
 * Integration tests for the Coming soon guard against a real WordPress (spec 101, T028).
 *
 * `ComingSoonDecisionTest` proves the table. What it cannot prove is that the guard hands the
 * table true facts — that a real editor "can edit posts" and a real subscriber cannot, that the
 * home URL is recognised as home, that a feed address is not. Those are answers WordPress gives,
 * so they are asked of WordPress here.
 *
 * The guard's `handle()` ends the request for a redirect or a sitemap, so those two are asserted
 * through `decision()`; what is actually sent over HTTP is the browser suite's to prove.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\ComingSoonDecision;
use Corex\Config\Operations\ComingSoonGuard;
use Corex\Config\Operations\ComingSoonTemplate;
use Corex\Config\Operations\OperationsMode;
use Corex\Tests\Support\AdminScreen;

const COREX_COMING_SOON_MODE_OPTION = 'corex_operations_mode';

function corexComingSoonGuard(): ComingSoonGuard
{
    return Boot::app()->container()->make(ComingSoonGuard::class);
}

/**
 * Stand in for the parts of a front-end request the guard reads: the address asked for, and the
 * query variables WordPress matched in it.
 *
 * @param array<string,string> $queryVars
 */
function corexComingSoonVisit(string $path, array $queryVars = []): ComingSoonDecision
{
    $_SERVER['REQUEST_URI']    = (string) parse_url(home_url($path), PHP_URL_PATH)
        . (str_contains($path, '?') ? '?' . explode('?', $path, 2)[1] : '');
    $GLOBALS['wp']->query_vars = $queryVars;

    return corexComingSoonGuard()->decision();
}

function corexComingSoonUser(string $role): int
{
    $login = 'corex-coming-soon-' . $role;

    // A run that died before its clean-up leaves the user behind. Reuse it rather than fail on
    // the duplicate: `wp_insert_user()` would return an error, and an error cast to an id is 1 —
    // the administrator, which is the one user these tests must not be mistaken for.
    $existing = get_user_by('login', $login);
    if ($existing instanceof WP_User) {
        $existing->set_role($role);

        return $existing->ID;
    }

    $created = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(),
        'user_email' => 'coming-soon-' . $role . '@example.test',
        'role'       => $role,
    ]);

    if (is_wp_error($created)) {
        throw new RuntimeException('Could not create the ' . $role . ' test user: ' . $created->get_error_message());
    }

    return $created;
}

beforeEach(function () {
    $this->previousMode      = get_option(COREX_COMING_SOON_MODE_OPTION, null);
    $this->previousUri       = $_SERVER['REQUEST_URI'] ?? null;
    $this->previousQueryVars = $GLOBALS['wp']->query_vars;
    $this->previousPublic    = get_option('blog_public');
    $this->previousScreen    = $GLOBALS['current_screen'] ?? null;
    $this->createdUsers      = [];

    update_option(COREX_COMING_SOON_MODE_OPTION, OperationsMode::COMING_SOON);
    update_option('blog_public', '1');
    wp_set_current_user(0);
    // The suite before this one may have left an admin screen set; a front-end request has none.
    unset($GLOBALS['current_screen']);
});

afterEach(function () {
    remove_all_filters(ComingSoonGuard::BYPASS_FILTER);
    remove_all_filters(ComingSoonGuard::HOME_FILTER);
    remove_all_filters('template_include');
    remove_filter('show_admin_bar', '__return_false');
    remove_filter('body_class', [corexComingSoonGuard(), 'servedBodyClasses']);
    remove_action('wp_enqueue_scripts', [corexComingSoonGuard(), 'enqueuePageAssets']);
    remove_all_actions(ComingSoonGuard::ASSETS_ACTION);
    unset($_GET[ComingSoonGuard::VISITOR_VIEW], $GLOBALS['show_admin_bar'], $GLOBALS['wp_admin_bar']);
    wp_dequeue_style('admin-bar');
    wp_dequeue_script('admin-bar');
    wp_set_current_user(0);

    if (! function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($this->createdUsers as $userId) {
        // Delete by what the user is, not by a number this file was handed. The suite runs against
        // a developer's real install, and the first draft of this file deleted that install's
        // administrator, and every post they owned, on the strength of an id that was wrong.
        $user = get_userdata($userId);
        if ($user instanceof WP_User && str_starts_with($user->user_login, 'corex-coming-soon-')) {
            wp_delete_user($userId);
        }
    }

    if ($this->previousMode === null) {
        delete_option(COREX_COMING_SOON_MODE_OPTION);
    } else {
        update_option(COREX_COMING_SOON_MODE_OPTION, $this->previousMode);
    }
    update_option('blog_public', $this->previousPublic);

    if ($this->previousUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $this->previousUri;
    }
    $GLOBALS['wp']->query_vars = $this->previousQueryVars;
    $GLOBALS['wp_query']->is_robots  = false;
    $GLOBALS['wp_query']->is_favicon = false;

    if ($this->previousScreen === null) {
        unset($GLOBALS['current_screen']);
    } else {
        $GLOBALS['current_screen'] = $this->previousScreen;
    }
});

it('is registered on template_redirect before anything the theme or another plugin does there', function () {
    $guard = corexComingSoonGuard();

    expect(has_action('template_redirect', [$guard, 'handle']))->toBe(0);
});

it('serves an anonymous visitor the page at the home URL', function () {
    $decision = corexComingSoonVisit('/');

    expect($decision->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and($decision->noCache)->toBeFalse();
});

it('still serves the page when the home URL carries a campaign tag', function () {
    // Not a WordPress query variable, so WordPress matched nothing in it: it is the home URL.
    expect(corexComingSoonVisit('/?utm_source=launch&utm_medium=email')->outcome)->toBe(ComingSoonDecision::SERVE);
});

it('redirects an anonymous visitor from every other address', function (string $path, array $queryVars) {
    $decision = corexComingSoonVisit($path, $queryVars);

    expect($decision->outcome)->toBe(ComingSoonDecision::REDIRECT)
        ->and($decision->noCache)->toBeTrue();
})->with([
    'a page'                    => ['/about/', ['pagename' => 'about']],
    'a post'                    => ['/hello-world/', ['name' => 'hello-world']],
    'an address with nothing'   => ['/no-such-address/', ['name' => 'no-such-address']],
    'the feed'                  => ['/feed/', ['feed' => 'feed']],
    // The home path with a query variable WordPress acts on is not the home URL. Left as "home",
    // `/?feed=rss2` would be answered by WordPress's feed renderer, which runs before any
    // template is chosen — the unfinished site's posts, served to anybody who asked.
    'the feed by query string'  => ['/?feed=rss2', ['feed' => 'rss2']],
    'a post by query string'    => ['/?p=1', ['p' => '1']],
    'a search'                  => ['/?s=launch', ['s' => 'launch']],
    'a sub-sitemap'             => ['/wp-sitemap-posts-post-1.xml', ['sitemap' => 'posts', 'sitemap-subtype' => 'post', 'paged' => '1']],
]);

it('passes an administrator, at home and elsewhere', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);

    $home      = corexComingSoonVisit('/');
    $elsewhere = corexComingSoonVisit('/about/', ['pagename' => 'about']);

    expect($home->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($elsewhere->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($elsewhere->noCache)->toBeTrue();
});

it('passes an editor, who is not an administrator and can edit posts', function () {
    $this->createdUsers[] = $editorId = corexComingSoonUser('editor');
    wp_set_current_user($editorId);

    expect(current_user_can('manage_options'))->toBeFalse()
        ->and(current_user_can('edit_posts'))->toBeTrue()
        ->and(corexComingSoonVisit('/about/', ['pagename' => 'about'])->outcome)->toBe(ComingSoonDecision::PASS);
});

it('treats a signed-in subscriber exactly as an anonymous visitor', function () {
    $this->createdUsers[] = $subscriberId = corexComingSoonUser('subscriber');
    wp_set_current_user($subscriberId);

    expect(is_user_logged_in())->toBeTrue()
        ->and(current_user_can('edit_posts'))->toBeFalse()
        ->and(corexComingSoonVisit('/about/', ['pagename' => 'about'])->outcome)->toBe(ComingSoonDecision::REDIRECT)
        ->and(corexComingSoonVisit('/')->outcome)->toBe(ComingSoonDecision::SERVE);
});

it('lets client code allow a request through, without changing the stored mode', function () {
    add_filter(ComingSoonGuard::BYPASS_FILTER, '__return_true');

    expect(ComingSoonGuard::BYPASS_FILTER)->toBe('corex_coming_soon_bypass')
        ->and(corexComingSoonVisit('/privacy/', ['pagename' => 'privacy'])->outcome)->toBe(ComingSoonDecision::PASS)
        ->and(get_option(COREX_COMING_SOON_MODE_OPTION))->toBe(OperationsMode::COMING_SOON);
});

it('admits only a strict true from the bypass filter', function () {
    // The same contract as Maintenance mode's: a filter that returns something merely truthy —
    // a stray string, a 1 — has not allowed anything.
    add_filter(ComingSoonGuard::BYPASS_FILTER, static fn (): string => 'yes');

    expect(corexComingSoonVisit('/privacy/', ['pagename' => 'privacy'])->outcome)->toBe(ComingSoonDecision::REDIRECT);
});

it('lets a multilingual site name each language home as a home URL', function () {
    add_filter(
        ComingSoonGuard::HOME_FILTER,
        static fn (bool $isHome, string $path): bool => $isHome || trim($path, '/') === 'ar',
        10,
        2,
    );

    expect(ComingSoonGuard::HOME_FILTER)->toBe('corex_coming_soon_is_home')
        ->and(corexComingSoonVisit('/ar/', ['lang' => 'ar'])->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and(corexComingSoonVisit('/en/', ['lang' => 'en'])->outcome)->toBe(ComingSoonDecision::REDIRECT);
});

it('passes robots.txt and the favicon', function (string $flag) {
    $GLOBALS['wp_query']->{$flag} = true;

    $decision = corexComingSoonVisit('/whatever', ['robots' => '1']);

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS);
})->with(['robots.txt' => 'is_robots', 'the favicon' => 'is_favicon']);

it('answers the sitemap index with its own sitemap', function () {
    expect(corexComingSoonVisit('/wp-sitemap.xml', ['sitemap' => 'index'])->outcome)->toBe(ComingSoonDecision::SITEMAP)
        // Plain permalinks: the same address is the home path with a query string.
        ->and(corexComingSoonVisit('/?sitemap=index', ['sitemap' => 'index'])->outcome)->toBe(ComingSoonDecision::SITEMAP);
});

it('publishes no sitemap when WordPress is set to discourage search engines', function () {
    // WordPress serves none in that case. Publishing one here would be the mode overruling the
    // one control an operator has for keeping the page out of search results.
    update_option('blog_public', '0');

    expect(corexComingSoonVisit('/wp-sitemap.xml', ['sitemap' => 'index'])->outcome)->toBe(ComingSoonDecision::REDIRECT);
});

it('never intercepts the admin', function () {
    AdminScreen::set('dashboard');

    expect(is_admin())->toBeTrue()
        ->and(corexComingSoonVisit('/about/', ['pagename' => 'about'])->outcome)->toBe(ComingSoonDecision::PASS);
});

it('does nothing at all in any other mode, and consults no extension point', function (string $mode) {
    update_option(COREX_COMING_SOON_MODE_OPTION, $mode);
    $consulted = 0;
    $count     = static function ($value) use (&$consulted) {
        $consulted++;

        return $value;
    };
    add_filter(ComingSoonGuard::BYPASS_FILTER, $count);
    add_filter(ComingSoonGuard::HOME_FILTER, $count);

    $decision = corexComingSoonVisit('/about/', ['pagename' => 'about']);

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse()
        ->and($consulted)->toBe(0);
})->with([OperationsMode::DEVELOPMENT, OperationsMode::PRODUCTION, OperationsMode::MAINTENANCE]);

it('arranges for the coming-soon template to be what WordPress renders at the home URL', function () {
    global $_wp_current_template_content;
    $_SERVER['REQUEST_URI']    = (string) parse_url(home_url('/'), PHP_URL_PATH);
    $GLOBALS['wp']->query_vars = [];

    corexComingSoonGuard()->handle();
    $included = apply_filters('template_include', '/the/template/wordpress/chose.php');

    expect($included)->toEndWith('template-canvas.php')
        ->and((string) $_wp_current_template_content)->toContain('Coming soon')
        ->and((string) $_wp_current_template_content)->not->toContain('wp:template-part');
});

it('registers the default page as a block template a theme can replace', function () {
    $registered = WP_Block_Templates_Registry::get_instance()->get_registered(ComingSoonTemplate::NAME);

    expect($registered)->not->toBeNull()
        ->and($registered->slug)->toBe('coming-soon')
        ->and($registered->plugin)->toBe('corex-config')
        ->and($registered->content)->toContain('<!-- wp:heading');
});

it('adds no noindex of its own to the page, and keeps the one WordPress adds', function () {
    // FR-010, both ways. The page is rendered by the template canvas, so its robots directive is
    // whatever `wp_robots` resolves to: nothing from this mode, and WordPress's own when the
    // operator has asked search engines to stay away.
    $robots = static function (): string {
        ob_start();
        wp_robots();

        return (string) ob_get_clean();
    };

    corexComingSoonVisit('/');
    corexComingSoonGuard()->handle();
    $visible = $robots();

    update_option('blog_public', '0');
    $discouraged = $robots();

    expect($visible)->not->toContain('noindex')
        ->and($discouraged)->toContain('noindex');
});

it('accepts a form posted to the REST API while the mode is on', function () {
    // US2.3: a form on the coming-soon page submits to REST, which the mode never intercepts. This
    // dispatches through the real REST server, so anything the mode hooked there would be in the way.
    add_filter('pre_wp_mail', '__return_true');
    $request = new WP_REST_Request('POST', '/corex/v1/forms/contact');
    $request->set_body_params(['name' => 'Acme visitor', 'email' => 'visitor@example.test', 'message' => 'Tell me when you launch.']);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    $response = rest_get_server()->dispatch($request);
    remove_filter('pre_wp_mail', '__return_true');

    expect(get_option(COREX_COMING_SOON_MODE_OPTION))->toBe(OperationsMode::COMING_SOON)
        ->and($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue();
});

// The visitor view (spec 101, T034, US3.3).

it('serves the page to an administrator who asks to see it as a visitor, at any address', function (string $path, array $queryVars) {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';

    $decision = corexComingSoonVisit($path, $queryVars);

    expect(ComingSoonGuard::VISITOR_VIEW)->toBe('corex_visitor_view')
        ->and($decision->outcome)->toBe(ComingSoonDecision::SERVE)
        // The same address answers with the real site without the request, so a cache must not
        // keep either answer for the other.
        ->and($decision->noCache)->toBeTrue()
        ->and($decision->bar)->toBe(ComingSoonDecision::BAR_NONE);
})->with([
    'the home URL' => ['/?corex_visitor_view=1', []],
    'a page'       => ['/about/?corex_visitor_view=1', ['pagename' => 'about']],
]);

it('gives the visitor view to an editor as well', function () {
    $this->createdUsers[] = $editorId = corexComingSoonUser('editor');
    wp_set_current_user($editorId);
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';

    expect(corexComingSoonVisit('/?corex_visitor_view=1')->outcome)->toBe(ComingSoonDecision::SERVE);
});

it('changes nothing for somebody who asks for the visitor view and is a visitor already', function () {
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';

    $home      = corexComingSoonVisit('/?corex_visitor_view=1');
    $elsewhere = corexComingSoonVisit('/about/?corex_visitor_view=1', ['pagename' => 'about']);

    // Asking is not a way in, and not a way to make the home page uncacheable either.
    expect($home->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and($home->noCache)->toBeFalse()
        ->and($elsewhere->outcome)->toBe(ComingSoonDecision::REDIRECT);
});

it('shows the real site, with the notice, to an administrator who has not asked', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);

    $decision = corexComingSoonVisit('/');

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->bar)->toBe(ComingSoonDecision::BAR_NOTICE);
});

it('takes the WordPress toolbar off the page it serves, so a signed-in user sees what a visitor sees', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';
    corexComingSoonVisit('/?corex_visitor_view=1');

    // WordPress sets the toolbar up on this same hook, at this same priority, and was registered
    // first — so by the time the guard runs, the toolbar's styles are already queued and its
    // stylesheet has pushed the page down by the toolbar's height.
    _wp_admin_bar_init();
    $before = is_admin_bar_showing() && wp_style_is('admin-bar', 'enqueued');

    corexComingSoonGuard()->handle();

    expect($before)->toBeTrue()
        ->and(is_admin_bar_showing())->toBeFalse()
        ->and(wp_style_is('admin-bar', 'enqueued'))->toBeFalse()
        ->and(wp_script_is('admin-bar', 'enqueued'))->toBeFalse();
});

it('does not mark the page it serves as a signed-in one', function () {
    // The one thing left that told the visitor view from a visitor's own page, found by comparing
    // the two over HTTP: WordPress adds `logged-in` to the body, and a theme may style by it.
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    $before = get_body_class();
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';
    corexComingSoonVisit('/?corex_visitor_view=1');

    corexComingSoonGuard()->handle();

    expect($before)->toContain('logged-in')
        ->and(get_body_class())->not->toContain('logged-in')
        ->and(get_body_class())->not->toContain('admin-bar');
});

it('leaves the toolbar alone on the real site', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    corexComingSoonVisit('/');
    _wp_admin_bar_init();

    corexComingSoonGuard()->handle();

    expect(is_admin_bar_showing())->toBeTrue()
        ->and(wp_style_is('admin-bar', 'enqueued'))->toBeTrue();
});

// The page's own assets and body class (spec 101, T054; plan Decision 9).

it('offers a theme one action to load the assets of the coming-soon page on, only when that page is the response', function () {
    $guard = corexComingSoonGuard();
    $fired = 0;
    add_action(ComingSoonGuard::ASSETS_ACTION, static function () use (&$fired): void {
        $fired++;
    });

    corexComingSoonVisit('/');
    $guard->handle();

    expect(ComingSoonGuard::ASSETS_ACTION)->toBe('corex_coming_soon_enqueue_assets')
        // Hooked to WordPress's own moment for queueing front-end assets, so whatever a theme
        // enqueues there lands in the head with everything else.
        ->and(has_action('wp_enqueue_scripts', [$guard, 'enqueuePageAssets']))->not->toBeFalse()
        ->and($fired)->toBe(0);

    $guard->enqueuePageAssets();

    expect($fired)->toBe(1);
});

it('marks the body of the coming-soon page, so a theme can scope its styles to it', function () {
    $before = get_body_class();
    corexComingSoonVisit('/');

    corexComingSoonGuard()->handle();

    expect(ComingSoonGuard::BODY_CLASS)->toBe('corex-coming-soon')
        ->and($before)->not->toContain('corex-coming-soon')
        ->and(get_body_class())->toContain('corex-coming-soon');
});

it('loads the assets of the page and marks its body on the visitor view too, since it is the same page', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';
    corexComingSoonVisit('/about/?corex_visitor_view=1', ['pagename' => 'about']);
    $guard = corexComingSoonGuard();

    $guard->handle();

    expect(has_action('wp_enqueue_scripts', [$guard, 'enqueuePageAssets']))->not->toBeFalse()
        ->and(get_body_class())->toContain('corex-coming-soon');
});

it('neither loads those assets nor marks the body on any other response', function () {
    // Principle VI: a launch page's stylesheet and fonts load where it renders and nowhere else.
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    $guard = corexComingSoonGuard();

    // The real site, served to somebody who passes.
    corexComingSoonVisit('/about/', ['pagename' => 'about']);
    $guard->handle();
    $onTheRealSite = [has_action('wp_enqueue_scripts', [$guard, 'enqueuePageAssets']), in_array('corex-coming-soon', get_body_class(), true)];

    // Another mode altogether.
    update_option(COREX_COMING_SOON_MODE_OPTION, OperationsMode::PRODUCTION);
    wp_set_current_user(0);
    corexComingSoonVisit('/');
    $guard->handle();
    $inAnotherMode = [has_action('wp_enqueue_scripts', [$guard, 'enqueuePageAssets']), in_array('corex-coming-soon', get_body_class(), true)];

    expect($onTheRealSite)->toBe([false, false])
        ->and($inAnotherMode)->toBe([false, false]);
});
