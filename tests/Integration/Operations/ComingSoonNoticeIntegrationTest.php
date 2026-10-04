<?php

/**
 * Integration tests for the Coming soon notice against a real WordPress (spec 101, T032, T033).
 *
 * ComingSoonNoticeTest proves what the bar says. This proves where it appears: printed for real
 * users who are served the real site and for nobody else, its stylesheet queued only with it, and
 * the same message as a toolbar node in the admin.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\ComingSoonGuard;
use Corex\Config\Operations\ComingSoonNotice;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

/** A fresh notice: the container's has printed-once state that must not leak between tests. */
function corexComingSoonNotice(): ComingSoonNotice
{
    $container = Boot::app()->container();

    return new ComingSoonNotice($container->make(ComingSoonGuard::class), $container->make(OperationsModeStore::class));
}

function corexComingSoonNoticeOutput(ComingSoonNotice $notice): string
{
    ob_start();
    $notice->render();

    return (string) ob_get_clean();
}

function corexComingSoonNoticeUser(string $role): int
{
    $login    = 'corex-coming-soon-notice-' . $role;
    $existing = get_user_by('login', $login);
    if ($existing instanceof WP_User) {
        $existing->set_role($role);

        return $existing->ID;
    }

    $created = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(),
        'user_email' => 'coming-soon-notice-' . $role . '@example.test',
        'role'       => $role,
    ]);
    if (is_wp_error($created)) {
        throw new RuntimeException('Could not create the ' . $role . ' test user: ' . $created->get_error_message());
    }

    return $created;
}

function corexComingSoonNoticeAdministrator(): int
{
    return (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
}

/** The toolbar as WordPress would build it for the current user, with this notice's node added. */
function corexComingSoonToolbar(ComingSoonNotice $notice): WP_Admin_Bar
{
    require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
    $toolbar = new WP_Admin_Bar();
    $notice->addToolbarNode($toolbar);

    return $toolbar;
}

beforeEach(function () {
    $this->previousMode      = get_option('corex_operations_mode', null);
    $this->previousUri       = $_SERVER['REQUEST_URI'] ?? null;
    $this->previousQueryVars = $GLOBALS['wp']->query_vars;
    $this->previousScreen    = $GLOBALS['current_screen'] ?? null;
    $this->createdUsers      = [];

    update_option('corex_operations_mode', OperationsMode::COMING_SOON);
    wp_set_current_user(0);
    unset($GLOBALS['current_screen']);

    // An ordinary page of the real site.
    $_SERVER['REQUEST_URI']    = (string) parse_url(home_url('/about/'), PHP_URL_PATH);
    $GLOBALS['wp']->query_vars = ['pagename' => 'about'];
});

afterEach(function () {
    wp_set_current_user(0);
    wp_dequeue_style(ComingSoonNotice::STYLE);
    wp_deregister_style(ComingSoonNotice::STYLE);
    unset($_GET[ComingSoonGuard::VISITOR_VIEW]);

    if (! function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($this->createdUsers as $userId) {
        $user = get_userdata($userId);
        if ($user instanceof WP_User && str_starts_with($user->user_login, 'corex-coming-soon-notice-')) {
            wp_delete_user($userId);
        }
    }

    if ($this->previousMode === null) {
        delete_option('corex_operations_mode');
    } else {
        update_option('corex_operations_mode', $this->previousMode);
    }

    if ($this->previousUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $this->previousUri;
    }
    $GLOBALS['wp']->query_vars = $this->previousQueryVars;

    if ($this->previousScreen === null) {
        unset($GLOBALS['current_screen']);
    } else {
        $GLOBALS['current_screen'] = $this->previousScreen;
    }
});

it('is hooked where the page opens, where its styles are queued, and into the toolbar', function () {
    $notice = Boot::app()->container()->make(ComingSoonNotice::class);

    expect(has_action('wp_body_open', [$notice, 'render']))->toBe(1)
        ->and(has_action('wp_footer', [$notice, 'render']))->toBe(1)
        ->and(has_action('wp_enqueue_scripts', [$notice, 'enqueue']))->not->toBeFalse()
        ->and(has_action('admin_bar_menu', [$notice, 'addToolbarNode']))->not->toBeFalse();
});

it('prints the bar for an administrator on the real site, with the way to the screen that changes it', function () {
    wp_set_current_user(corexComingSoonNoticeAdministrator());

    $html = corexComingSoonNoticeOutput(corexComingSoonNotice());

    expect($html)->toContain('corex-coming-soon-bar')
        ->and($html)->toContain('Coming soon is on')
        ->and($html)->toContain(ComingSoonGuard::VISITOR_VIEW . '=1')
        ->and($html)->toContain('page=corex-operations-security');
});

it('prints the bar for an editor without the screen they cannot open', function () {
    $this->createdUsers[] = $editorId = corexComingSoonNoticeUser('editor');
    wp_set_current_user($editorId);

    $html = corexComingSoonNoticeOutput(corexComingSoonNotice());

    expect($html)->toContain('Coming soon is on')
        ->and($html)->toContain(ComingSoonGuard::VISITOR_VIEW . '=1')
        ->and($html)->not->toContain('corex-operations-security');
});

it('prints nothing for a visitor, signed in or not', function () {
    $anonymous = corexComingSoonNoticeOutput(corexComingSoonNotice());

    $this->createdUsers[] = $subscriberId = corexComingSoonNoticeUser('subscriber');
    wp_set_current_user($subscriberId);
    $subscriber = corexComingSoonNoticeOutput(corexComingSoonNotice());

    expect($anonymous)->toBe('')
        ->and($subscriber)->toBe('');
});

it('prints nothing on the visitor view', function () {
    wp_set_current_user(corexComingSoonNoticeAdministrator());
    $_GET[ComingSoonGuard::VISITOR_VIEW] = '1';

    expect(corexComingSoonNoticeOutput(corexComingSoonNotice()))->toBe('');
});

it('prints nothing in any other mode', function () {
    update_option('corex_operations_mode', OperationsMode::PRODUCTION);
    wp_set_current_user(corexComingSoonNoticeAdministrator());

    expect(corexComingSoonNoticeOutput(corexComingSoonNotice()))->toBe('');
});

it('prints the bar once, though it listens at the top of the page and at the foot', function () {
    wp_set_current_user(corexComingSoonNoticeAdministrator());
    $notice = corexComingSoonNotice();

    $atTheTop  = corexComingSoonNoticeOutput($notice);
    $atTheFoot = corexComingSoonNoticeOutput($notice);

    expect($atTheTop)->not->toBe('')
        ->and($atTheFoot)->toBe('');
});

it('queues its stylesheet, and the tokens it is drawn with, only on a response that shows the bar', function () {
    corexComingSoonNotice()->enqueue();
    $forAVisitor = wp_style_is(ComingSoonNotice::STYLE, 'enqueued');

    wp_set_current_user(corexComingSoonNoticeAdministrator());
    corexComingSoonNotice()->enqueue();
    $registered = wp_styles()->registered[ComingSoonNotice::STYLE] ?? null;

    expect($forAVisitor)->toBeFalse()
        ->and(wp_style_is(ComingSoonNotice::STYLE, 'enqueued'))->toBeTrue()
        ->and($registered->deps)->toBe(['corex-admin-tokens'])
        ->and($registered->src)->toEndWith('/assets/css/coming-soon-bar.css')
        ->and(is_file(dirname(COREX_CONFIG_FILE) . '/assets/css/coming-soon-bar.css'))->toBeTrue();
});

it('puts the message in the toolbar in the admin, for everybody who is served the real site', function () {
    set_current_screen('dashboard');

    wp_set_current_user(corexComingSoonNoticeAdministrator());
    $forAdministrator = corexComingSoonToolbar(corexComingSoonNotice());

    $this->createdUsers[] = $editorId = corexComingSoonNoticeUser('editor');
    wp_set_current_user($editorId);
    $forEditor = corexComingSoonToolbar(corexComingSoonNotice());

    expect($forAdministrator->get_node(ComingSoonNotice::TOOLBAR_NODE)->title)->toBe('Coming soon is on')
        ->and($forAdministrator->get_node(ComingSoonNotice::TOOLBAR_NODE . '-view')->href)->toContain(ComingSoonGuard::VISITOR_VIEW . '=1')
        ->and($forAdministrator->get_node(ComingSoonNotice::TOOLBAR_NODE . '-operations')->href)->toContain('page=corex-operations-security')
        ->and($forEditor->get_node(ComingSoonNotice::TOOLBAR_NODE))->not->toBeNull()
        ->and($forEditor->get_node(ComingSoonNotice::TOOLBAR_NODE . '-view'))->not->toBeNull()
        ->and($forEditor->get_node(ComingSoonNotice::TOOLBAR_NODE . '-operations'))->toBeNull();
});

it('adds nothing to the toolbar in another mode, on the front end, or for a user who is a visitor', function () {
    wp_set_current_user(corexComingSoonNoticeAdministrator());
    $onTheFrontEnd = corexComingSoonToolbar(corexComingSoonNotice());

    set_current_screen('dashboard');
    update_option('corex_operations_mode', OperationsMode::MAINTENANCE);
    $inAnotherMode = corexComingSoonToolbar(corexComingSoonNotice());

    update_option('corex_operations_mode', OperationsMode::COMING_SOON);
    $this->createdUsers[] = $subscriberId = corexComingSoonNoticeUser('subscriber');
    wp_set_current_user($subscriberId);
    $forASubscriber = corexComingSoonToolbar(corexComingSoonNotice());

    expect($onTheFrontEnd->get_node(ComingSoonNotice::TOOLBAR_NODE))->toBeNull()
        ->and($inAnotherMode->get_node(ComingSoonNotice::TOOLBAR_NODE))->toBeNull()
        ->and($forASubscriber->get_node(ComingSoonNotice::TOOLBAR_NODE))->toBeNull();
});
