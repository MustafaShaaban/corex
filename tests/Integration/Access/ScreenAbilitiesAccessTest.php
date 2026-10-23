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
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Boot;
use Corex\Config\Access\AbilityCompatibility;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Access\AccessService;
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

/*
 * DECISIONS #428: what only a site administrator may change, asked through the Access routes as a
 * person holding "Manage access" who is not one.
 */

/** A role change through its route, confirmed as the dialog confirms it. */
function applyRoleRoute(string $role, array $changes): WP_REST_Response
{
    $service = Boot::app()->container()->make(AccessService::class);

    return screenRoute('POST', 'access/roles/' . $role . '/apply', [
        'changes'      => $changes,
        'confirmation' => [
            'operation_kind' => AccessService::ROLE_CHANGE_OPERATION,
            'target_hash'    => $service->roleChangeHash($role, $changes),
            'actor_id'       => (string) get_current_user_id(),
            'expires_at'     => (new DateTimeImmutable('+5 minutes'))->format(DATE_ATOM),
        ],
    ]);
}

/** @return list<string> The codes a route's blocked result gives. */
function refusalCodes(WP_REST_Response $response): array
{
    return array_column((array) ($response->get_data()['data']['result']['errors'] ?? []), 'code');
}

/** The newest access event of this kind by this person, or null. */
function accessEventBy(string $kind, int $actorId): ?ActivityEvent
{
    $events = Boot::app()->container()->make(ActivityRepository::class)->query(['area' => ActivityEvent::AREA_ACCESS], 1, 100);
    foreach ($events as $event) {
        if ($event->kind === $kind && $event->actorId === $actorId) {
            return $event;
        }
    }

    return null;
}

it('refuses a manager who is not a site administrator a Critical Allow, and records it', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);

    $response = applyRoleRoute('editor', [CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_ALLOW]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['result']['state'])->toBe('blocked')
        ->and(refusalCodes($response))->toBe([AccessPolicy::CRITICAL_ALLOW])
        ->and($this->roles->effectsForRole('editor'))->toBe([])
        ->and(accessEventBy('access.role.refused', $this->people['subscriber']))->not->toBeNull();
});

it('refuses a manager who is not a site administrator any change to a role they hold', function () {
    (new WP_User($this->people['editor']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['editor']);

    $response = applyRoleRoute('editor', [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);

    expect(refusalCodes($response))->toBe([AccessPolicy::OWN_ACCESS])
        ->and($this->roles->effectsForRole('editor'))->toBe([]);
});

it('lets a manager who is not a site administrator allow an ability that is not Critical for a role they do not hold', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);

    $response = applyRoleRoute('editor', [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);

    expect($response->get_data()['data']['result']['state'])->toBe('completed')
        ->and($this->roles->effectsForRole('editor'))->toBe([CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);
});

it('refuses a manager who is not a site administrator to decide their own request, and records it', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);
    $created = screenRoute('POST', 'access/requests', ['ability' => CorexAbility::MANAGE_FORMS, 'reason' => 'I build the forms.']);
    $id      = (int) ($created->get_data()['data']['result']['affected_ids'][0] ?? 0);

    $denied = screenRoute('POST', 'access/requests/' . $id . '/decision', ['approved' => false, 'note' => 'Mine.']);

    try {
        expect($id)->toBeGreaterThan(0)
            ->and(refusalCodes($denied))->toBe([AccessService::OWN_REQUEST])
            ->and(current_user_can(CorexAbility::MANAGE_FORMS))->toBeFalse()
            ->and(accessEventBy('access.request.refused', $this->people['subscriber']))->not->toBeNull();
    } finally {
        global $wpdb;
        $wpdb->delete($this->migrator->fullName(AccessTables::REQUESTS), ['id' => $id]);
    }
});

it('lets a site administrator allow a Critical ability for a role', function () {
    wp_set_current_user($this->adminId);

    $response = applyRoleRoute('editor', [CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_ALLOW]);

    expect($response->get_data()['data']['result']['state'])->toBe('completed')
        ->and($this->roles->effectsForRole('editor'))->toBe([CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_ALLOW]);
});

it('counts refused flow, activity and Blog Pro routes in the access audit under their screens', function () {
    wp_set_current_user($this->people['subscriber']);

    $statuses = [
        screenRoute('GET', 'flows')->get_status(),
        screenRoute('GET', 'activity')->get_status(),
        screenRoute('GET', 'blog/authors')->get_status(),
    ];
    $sections = array_column(array_values(array_filter(
        Boot::app()->container()->make(AccessAuditLog::class)->entries(),
        fn (array $entry): bool => $entry['user'] === $this->people['subscriber'],
    )), 'section');

    expect($statuses)->toBe([403, 403, 403])
        ->and($sections)->toEqualCanonicalizing(['corex-forms', 'corex-settings', 'corex-blog-pro']);
});
