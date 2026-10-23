<?php

/**
 * Every CoreX screen asks for its own ability (spec 117): on a real WordPress, people given one
 * ability in the role matrix reach that screen's routes and are refused the others, the refusal is
 * counted in the access audit, site administrators keep everything whatever the matrix stores, the
 * view ability reads and never writes, and the mail delivery fields stay with site administration.
 *
 * The people are this file's own: an editor, given abilities through the Editor role in the role
 * matrix, and a subscriber given abilities on their account, the way an approved request gives them.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Boot;
use Corex\Config\Access\AbilityCompatibility;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Access\AccessTables;
use Corex\Config\Settings\AdminDashboard;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsStore;
use Corex\Database\Schema\Migrator;

/** The CoreX REST route asked as the current user, with that user's REST nonce. */
function screenRoute(string $method, string $route, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/' . $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($method !== 'GET') {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));
    }

    return rest_get_server()->dispatch($request);
}

/**
 * Who holds what is read again from the matrix, as on the next request: the filter keeps each role's
 * effects for the request, so a fresh one stands in for it, and the site's own comes back at the end.
 */
function freshAbilities(bool $restore = false): void
{
    $container = Boot::app()->container();
    $site      = $container->make(AbilityCompatibility::class);

    remove_filter('user_has_cap', [$site, 'filter'], 20);
    if (($GLOBALS['corex_test_abilities'] ?? null) instanceof AbilityCompatibility) {
        remove_filter('user_has_cap', [$GLOBALS['corex_test_abilities'], 'filter'], 20);
    }

    if ($restore) {
        unset($GLOBALS['corex_test_abilities']);
        $site->register();

        return;
    }

    $GLOBALS['corex_test_abilities'] = new AbilityCompatibility(
        $container->make(CorexAbilityCatalog::class),
        $container->make(RoleAbilityStore::class),
    );
    $GLOBALS['corex_test_abilities']->register();
}

beforeEach(function () {
    $container      = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    $this->roles    = $container->make(RoleAbilityStore::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }

    global $wpdb;
    $grants         = $this->migrator->fullName(AccessTables::ROLE_GRANTS);
    $this->keptRows = $wpdb->get_results("SELECT * FROM {$grants} WHERE role_key IN ('editor', 'administrator')", ARRAY_A);
    $wpdb->query("DELETE FROM {$grants} WHERE role_key IN ('editor', 'administrator')");
    $this->keptAudit = get_option('corex_access_audit_log', []);
    delete_option('corex_access_audit_log');

    $this->adminId = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
    $this->people  = [];
    foreach (['editor', 'subscriber'] as $role) {
        $id = wp_insert_user([
            'user_login' => 'corex-screens-' . $role . '-' . wp_generate_password(6, false),
            'user_pass'  => wp_generate_password(),
            'user_email' => uniqid('corex-screens-', true) . '@example.test',
            'role'       => $role,
        ]);
        expect($id)->toBeInt();
        $this->people[$role] = $id;
    }

    $this->allowForEditors = function (array $effects): void {
        $this->roles->apply('editor', $effects, $this->adminId, new DateTimeImmutable('now'));
        freshAbilities();
    };
    freshAbilities();
});

afterEach(function () {
    global $wpdb;
    $grants = $this->migrator->fullName(AccessTables::ROLE_GRANTS);
    $wpdb->query("DELETE FROM {$grants} WHERE role_key IN ('editor', 'administrator')");
    foreach ($this->keptRows as $row) {
        $wpdb->insert($grants, $row);
    }
    update_option('corex_access_audit_log', $this->keptAudit, false);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->people as $id) {
        wp_delete_user($id);
    }
    freshAbilities(restore: true);
});

it('opens the routes of the one screen a role is allowed and refuses the others with 403', function () {
    ($this->allowForEditors)([CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);
    wp_set_current_user($this->people['editor']);

    expect(screenRoute('GET', 'flows')->get_status())->toBe(200)
        ->and(screenRoute('GET', 'email-studio')->get_status())->toBe(403)
        ->and(screenRoute('GET', 'access/catalog')->get_status())->toBe(403)
        ->and(screenRoute('POST', 'captcha/test')->get_status())->toBe(403)
        ->and(screenRoute('GET', 'insights')->get_status())->toBe(403)
        ->and(screenRoute('GET', 'setup/state')->get_status())->toBe(403);
});

it('counts a refused route in the access audit under the screen it belongs to', function () {
    wp_set_current_user($this->people['editor']);

    screenRoute('GET', 'email-studio');
    screenRoute('GET', 'email-studio');
    $entries = array_values(array_filter(
        Boot::app()->container()->make(AccessAuditLog::class)->entries(),
        fn (array $entry): bool => $entry['user'] === $this->people['editor'],
    ));

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toMatchArray(['kind' => 'action', 'section' => 'corex-email-studio', 'count' => 2]);
});

it('opens Email Studio and its routes for a person granted "Manage Email Studio"', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_EMAIL);
    wp_set_current_user($this->people['subscriber']);

    expect(screenRoute('GET', 'email-studio')->get_status())->toBe(200)
        ->and(screenRoute('GET', 'flows')->get_status())->toBe(403);
});

it('keeps every CoreX ability for a site administrator whatever the matrix stores for the role', function () {
    $this->roles->apply('administrator', [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY], $this->adminId, new DateTimeImmutable('now'));
    freshAbilities();
    wp_set_current_user($this->adminId);

    foreach (CorexAbilityCatalog::defaults()->all() as $ability) {
        expect(current_user_can($ability->key))->toBeTrue();
    }
    expect(screenRoute('GET', 'email-studio')->get_status())->toBe(200);
});

it('lets the view ability read Operations & Security and Insights, and refuses and records a change', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::VIEW_SCREENS);
    wp_set_current_user($this->people['subscriber']);

    $read   = screenRoute('GET', 'security/login-protection');
    $change = screenRoute('POST', 'security/login-protection', ['max_attempts' => 9]);
    $logged = array_column(Boot::app()->container()->make(AccessAuditLog::class)->entries(), null, 'section');

    expect($read->get_status())->toBe(200)
        ->and(screenRoute('GET', 'insights')->get_status())->toBe(200)
        ->and($change->get_status())->toBe(403)
        ->and(screenRoute('POST', 'insights/run')->get_status())->toBe(403)
        ->and(screenRoute('GET', 'email-studio')->get_status())->toBe(403)
        ->and($logged['corex-operations-security']['kind'] ?? null)->toBe('action');
});

it('needs "Manage Blog Pro" for Blog Pro, which an editor no longer holds by default', function () {
    wp_set_current_user($this->people['editor']);
    expect(screenRoute('GET', 'blog/authors')->get_status())->toBe(403);

    ($this->allowForEditors)([CorexAbility::MANAGE_BLOG => AccessPolicy::EFFECT_ALLOW]);
    expect(screenRoute('GET', 'blog/authors')->get_status())->toBe(200);
});

it('leaves how the whole site delivers mail as it was when someone who is not a site administrator saves Settings', function () {
    $container = Boot::app()->container();
    $store     = $container->make(SettingsStore::class);
    // A save writes every field it is given and every switch it is not, so all of them are kept.
    $kept = [];
    foreach ($container->make(SettingsRegistry::class)->keys() as $key) {
        $kept[$key] = get_option($store->optionName($key), null);
    }
    $store->save('mail.live_delivery', '1');
    $store->save('mail.provider', 'wp-mail');

    ($this->allowForEditors)([CorexAbility::MANAGE_SETTINGS => AccessPolicy::EFFECT_ALLOW]);
    wp_set_current_user($this->people['editor']);
    // A save of the form as such a person draws it: the two delivery fields are not sent.
    $_POST = ['corex_settings_nonce' => wp_create_nonce('corex_settings')];

    try {
        Boot::app()->container()->make(AdminDashboard::class)->maybeSave();

        expect($store->get('mail.live_delivery'))->toBe('1')
            ->and($store->get('mail.provider'))->toBe('wp-mail');
    } finally {
        $_POST = [];
        foreach ($kept as $key => $value) {
            $value === null ? delete_option($store->optionName($key)) : update_option($store->optionName($key), $value);
        }
    }
});
