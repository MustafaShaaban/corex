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

use Corex\Access\AbilityCheck;
use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Assistant\Admin\AssistantScreen;
use Corex\Boot;
use Corex\Config\Access\AbilityCompatibility;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Access\AccessService;
use Corex\Config\Access\AccessTables;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeController;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Settings\AdminDashboard;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsSaver;
use Corex\Config\Settings\SettingsStore;
use Corex\Config\Submissions\SubmissionsInboxScreen;
use Corex\Database\Schema\Migrator;
use Corex\Tests\Support\SettingsScreen;

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

it('lets the view ability read Insights, and refuses and records a change and Operations & Security', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::VIEW_SCREENS);
    wp_set_current_user($this->people['subscriber']);

    // Operations & Security is not read-only (DECISIONS #422, amended 2026-11-11): its login
    // protection shows the trusted proxies and the login policy.
    $read   = screenRoute('GET', 'security/login-protection');
    $change = screenRoute('POST', 'security/login-protection', ['max_attempts' => 9]);
    $logged = array_column(Boot::app()->container()->make(AccessAuditLog::class)->entries(), null, 'section');

    expect(screenRoute('GET', 'insights')->get_status())->toBe(200)
        ->and($read->get_status())->toBe(403)
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

it('leaves how the whole site delivers mail as it was when someone who is not a site administrator saves Settings (FR-008)', function () {
    $container = Boot::app()->container();
    $store     = $container->make(SettingsStore::class);
    $kept      = settingsAsStored();
    $store->save('mail.live_delivery', '1');
    $store->save('mail.provider', 'wp-mail');

    ($this->allowForEditors)([CorexAbility::MANAGE_SETTINGS => AccessPolicy::EFFECT_ALLOW]);
    wp_set_current_user($this->people['editor']);

    try {
        // The page as such a person draws it: the two fields read-only, with the sentence.
        $page = SettingsScreen::draw('email', siteAdministrator: false);
        // A save of that page sends neither field, so the unposted switch must not read as off.
        $drawn = SettingsScreen::save('email', ['mail_from_name' => 'Acme Sales'], siteAdministrator: false);
        // A post made by hand sends the provider anyway.
        $byHand = $container->make(SettingsSaver::class)->save(
            'email',
            ['mail_provider' => '', 'mail_live_delivery' => '0', 'mail_from_name' => 'Acme Sales'],
            get_current_user_id(),
            false,
        );

        expect($page)->toContain('Only a site administrator can change how the whole site delivers mail.')
            ->and($page)->toMatch('/<select id="mail_provider" name="mail_provider"[^>]*disabled/')
            ->and($drawn?->refused)->toBe([])
            ->and($byHand?->refused)->toBe([])
            ->and($store->get('mail.from_name'))->toBe('Acme Sales')
            ->and($store->get('mail.live_delivery'))->toBe('1')
            ->and($store->get('mail.provider'))->toBe('wp-mail');
    } finally {
        putSettingsBack($kept);
    }
});

it('lets a site administrator change how the whole site delivers mail (FR-008)', function () {
    $store = Boot::app()->container()->make(SettingsStore::class);
    $kept  = settingsAsStored();
    $store->save('mail.provider', '');
    wp_set_current_user($this->adminId);

    try {
        SettingsScreen::save('email', ['mail_provider' => 'wp-mail']);

        expect($store->get('mail.provider'))->toBe('wp-mail');
    } finally {
        putSettingsBack($kept);
    }
});

it('refuses a Settings save from the holder of the view ability with 403, and records it (FR-005, FR-014)', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::VIEW_SCREENS);
    wp_set_current_user($this->people['subscriber']);
    $kept  = settingsAsStored();
    $_POST = [
        'corex_settings_nonce' => wp_create_nonce('corex_settings'),
        'corex_settings_page'  => 'general',
        'brand_company_name'   => 'Changed by a reader',
    ];
    $refusal = static fn (): callable => static function (string $message, string $title, array $args): void {
        throw new RuntimeException('refused ' . ($args['response'] ?? 0));
    };
    add_filter('wp_die_handler', $refusal);

    try {
        expect(fn () => Boot::app()->container()->make(AdminDashboard::class)->maybeSave())
            ->toThrow(RuntimeException::class, 'refused 403');

        $logged = array_column(Boot::app()->container()->make(AccessAuditLog::class)->entries(), null, 'section');
        expect($logged['corex-settings-config']['kind'] ?? null)->toBe('action')
            ->and(settingsAsStored()['brand.company_name'])->toBe($kept['brand.company_name']);
    } finally {
        remove_filter('wp_die_handler', $refusal);
        $_POST = [];
        putSettingsBack($kept);
    }
});

/**
 * Every stored setting, by key, so a test that saves can put them back.
 *
 * @return array<string,mixed>
 */
function settingsAsStored(): array
{
    $container = Boot::app()->container();
    $store     = $container->make(SettingsStore::class);
    $values    = [];
    foreach ($container->make(SettingsRegistry::class)->keys() as $key) {
        $values[$key] = get_option($store->optionName($key), null);
    }

    return $values;
}

/** @param array<string,mixed> $kept */
function putSettingsBack(array $kept): void
{
    $store = Boot::app()->container()->make(SettingsStore::class);
    foreach ($kept as $key => $value) {
        $value === null ? delete_option($store->optionName($key)) : update_option($store->optionName($key), $value);
    }
}

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

/*
 * A captured email's content is shown only to site administrators (DECISIONS #429).
 */
it('withholds a captured email\'s content from a holder of "Manage Email Studio" who is not a site administrator, and refuses them the capture switch', function () {
    $capture = Boot::app()->container()->make(\Corex\Email\Capture\CapturedEmailRepository::class)->capture(new \Corex\Email\Message\EmailMessage(
        ['someone@example.test'],
        [],
        [],
        null,
        'Spec 117 captured content',
        '<p>Spec 117 body</p>',
        ['X-Spec-117' => 'header'],
        plainText: 'Spec 117 body',
    ));

    try {
        (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_EMAIL);
        wp_set_current_user($this->people['subscriber']);
        $mine = array_values(array_filter(
            (array) (screenRoute('GET', 'email-studio')->get_data()['data']['captures'] ?? []),
            static fn (array $row): bool => $row['capture_id'] === $capture->captureId,
        ));
        $scope = screenRoute('POST', 'email-studio/capture-scope', ['scope' => 'corex']);

        wp_set_current_user($this->adminId);
        $theirs = array_values(array_filter(
            (array) (screenRoute('GET', 'email-studio')->get_data()['data']['captures'] ?? []),
            static fn (array $row): bool => $row['capture_id'] === $capture->captureId,
        ));

        expect($mine)->toHaveCount(1)
            ->and($mine[0]['subject'])->toBe('Spec 117 captured content')
            ->and($mine[0]['content_withheld'])->toBeTrue()
            ->and($mine[0])->not->toHaveKeys(['html_body', 'plain_text', 'headers'])
            ->and($scope->get_status())->toBe(403)
            ->and($theirs[0]['html_body'] ?? null)->toBe('<p>Spec 117 body</p>')
            ->and($theirs[0]['content_withheld'])->toBeFalse();
    } finally {
        wp_delete_post($capture->id, true);
    }
});

/** The pending request this person made, created through the route. */
function ownRequestThroughRoute(): int
{
    $created = screenRoute('POST', 'access/requests', ['ability' => CorexAbility::MANAGE_FORMS, 'reason' => 'I build the forms.']);

    return (int) ($created->get_data()['data']['result']['affected_ids'][0] ?? 0);
}

it('refuses a manager who is not a site administrator to set a Critical ability to Inherit', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);

    $response = applyRoleRoute('editor', [CorexAbility::RUN_DANGEROUS_ACTIONS => AccessPolicy::EFFECT_INHERIT]);

    expect(refusalCodes($response))->toBe([AccessPolicy::CRITICAL_ALLOW]);
});

it('refuses a manager who is not a site administrator to approve their own request', function () {
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);
    $id = ownRequestThroughRoute();

    $approved = screenRoute('POST', 'access/requests/' . $id . '/decision', [
        'approved'     => true,
        'note'         => 'Mine.',
        'expiry_days'  => 30,
        'confirmation' => [
            'operation_kind' => AccessService::APPROVAL_OPERATION,
            'target_hash'    => Boot::app()->container()->make(AccessService::class)->approvalHash($id, [CorexAbility::MANAGE_FORMS]),
            'actor_id'       => (string) get_current_user_id(),
            'expires_at'     => (new DateTimeImmutable('+5 minutes'))->format(DATE_ATOM),
        ],
    ]);

    try {
        expect($id)->toBeGreaterThan(0)
            ->and(refusalCodes($approved))->toBe([AccessService::OWN_REQUEST])
            ->and(current_user_can(CorexAbility::MANAGE_FORMS))->toBeFalse();
    } finally {
        global $wpdb;
        $wpdb->delete($this->migrator->fullName(AccessTables::REQUESTS), ['id' => $id]);
    }
});

it('refuses a change to a role the manager holds as a second role', function () {
    $manager = new WP_User($this->people['subscriber']);
    $manager->add_role('editor');
    $manager->add_cap(CorexAbility::MANAGE_ACCESS);
    freshAbilities();
    wp_set_current_user($this->people['subscriber']);

    $response = applyRoleRoute('editor', [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);

    expect(refusalCodes($response))->toBe([AccessPolicy::OWN_ACCESS])
        ->and($this->roles->effectsForRole('editor'))->toBe([]);
});

/*
 * A refusal is recorded once (spec 117, FR-005), asked through the whole REST server: WordPress
 * asks every permission callback of the matched route again for the `Allow` header, on
 * `rest_post_dispatch`, and none of those questions is a refusal.
 */

/** The REST route asked as a browser asks it: dispatched, then the response filters, `Allow` among them. */
function screenRouteServed(string $method, string $route): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/' . ltrim($route, '/'));
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return apply_filters('rest_post_dispatch', rest_ensure_response(rest_do_request($request)), rest_get_server(), $request);
}

/** @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> */
function refusalsOf(int $userId): array
{
    return array_values(array_filter(
        Boot::app()->container()->make(AccessAuditLog::class)->entries(),
        static fn (array $entry): bool => $entry['user'] === $userId,
    ));
}

it('counts a refused call once through the whole REST server, its Allow header included (FR-005)', function () {
    wp_set_current_user($this->people['editor']);

    $response = screenRouteServed('GET', 'corex/v1/security/login-protection');

    expect($response->get_status())->toBe(403)
        ->and(refusalsOf($this->people['editor']))->toHaveCount(1)
        ->and(refusalsOf($this->people['editor'])[0])->toMatchArray(['section' => 'corex-operations-security', 'count' => 1]);
});

it('records no refusal when a reader opens a read route whose write method they may not use (FR-005, FR-014)', function () {
    $abilities = Boot::app()->container()->make(AbilityCheck::class);
    register_rest_route('corex-spec-117/v1', '/reading', [
        [
            'methods'             => 'GET',
            'callback'            => static fn (): WP_REST_Response => new WP_REST_Response(['read' => true]),
            'permission_callback' => static fn (): bool => $abilities->allowsReading(CorexAbility::MANAGE_ADMIN, 'corex-insights'),
        ],
        [
            'methods'             => 'POST',
            'callback'            => static fn (): WP_REST_Response => new WP_REST_Response(['changed' => true]),
            'permission_callback' => static fn (): bool => $abilities->allows(CorexAbility::MANAGE_ADMIN, 'corex-insights'),
        ],
    ]);
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::VIEW_SCREENS);
    wp_set_current_user($this->people['subscriber']);

    $response = screenRouteServed('GET', 'corex-spec-117/v1/reading');

    expect($response->get_status())->toBe(200)
        ->and($response->get_headers()['Allow'] ?? '')->toBe('GET')
        ->and(refusalsOf($this->people['subscriber']))->toBe([]);
});

/*
 * The inbox's retention forms appear only to the people their handlers accept (FR-004): saving
 * the policy needs "Manage settings", applying it "Run dangerous actions". The handlers are tested
 * in `RetentionControllerTest`.
 */
it('shows the retention save form only with "Manage settings" and the prune form only with "Run dangerous actions" (FR-004)', function () {
    $keptDays = get_option('corex_retention_submissions_days', null);
    update_option('corex_retention_submissions_days', 30, false);
    $due    = wp_insert_post(['post_type' => 'corex_submission', 'post_status' => 'private', 'post_title' => 'Spec 117 due', 'post_date' => '2020-01-01 10:00:00']);
    $screen = Boot::app()->container()->make(SubmissionsInboxScreen::class);
    $panel  = static fn (): string => (string) (new ReflectionMethod($screen, 'retentionPanel'))->invoke($screen);
    wp_set_current_user($this->people['subscriber']);
    // The signed-in person's own object, so each ability given is held at once.
    $person = wp_get_current_user();
    $person->add_cap(CorexAbility::MANAGE_SUBMISSIONS);

    try {
        $neither = $panel();
        $person->add_cap(CorexAbility::MANAGE_SETTINGS);
        $settings = $panel();
        $person->remove_cap(CorexAbility::MANAGE_SETTINGS);
        $person->add_cap(CorexAbility::RUN_DANGEROUS_ACTIONS);
        $dangerous = $panel();

        expect($due)->toBeGreaterThan(0)
            ->and($neither)->not->toContain('corex_retention_save')
            ->and($neither)->not->toContain('corex_retention_prune')
            ->and($settings)->toContain('corex_retention_save')
            ->and($settings)->not->toContain('corex_retention_prune')
            ->and($dangerous)->toContain('corex_retention_prune')
            ->and($dangerous)->not->toContain('corex_retention_save');
    } finally {
        wp_delete_post((int) $due, true);
        $keptDays === null
            ? delete_option('corex_retention_submissions_days')
            : update_option('corex_retention_submissions_days', $keptDays, false);
    }
});

/*
 * Production and Maintenance also need "Run dangerous actions"; Staging needs only "Manage
 * operations and security" (FR-003). Through the real handler: its refusal and its redirect both end
 * in `exit`, so a `status_header` filter and a `wp_redirect` filter stop it first.
 */
function modeSwitchAnswer(string $mode): string
{
    $_POST      = [OperationsModeController::NONCE => wp_create_nonce(OperationsModeController::ACTION), 'corex_mode' => $mode];
    $refused    = static fn (string $header, int $code): never => throw new RuntimeException('refused ' . $code);
    $redirected = static fn (): never => throw new RuntimeException('redirected');
    add_filter('status_header', $refused, 10, 2);
    add_filter('wp_redirect', $redirected);

    try {
        Boot::app()->container()->make(OperationsModeController::class)->handle();
    } catch (RuntimeException $answer) {
        return $answer->getMessage();
    } finally {
        remove_filter('status_header', $refused, 10);
        remove_filter('wp_redirect', $redirected);
        $_POST = [];
    }

    return 'returned';
}

it('switches to Staging with "Manage operations and security" alone, and refuses Production and Maintenance without "Run dangerous actions" (FR-003)', function () {
    $keptMode = get_option(OperationsModeStore::OPTION, null);
    $keptLog  = get_option('corex_operations_mode_log', null);
    (new WP_User($this->people['subscriber']))->add_cap(CorexAbility::MANAGE_OPERATIONS);
    wp_set_current_user($this->people['subscriber']);

    try {
        $production  = modeSwitchAnswer(OperationsMode::PRODUCTION);
        $maintenance = modeSwitchAnswer(OperationsMode::MAINTENANCE);
        // The site is held in Staging for this one, so the handler's answer is "unchanged" and the
        // dev site's mode and its history are not written.
        $inStaging = static fn (): string => OperationsMode::STAGING;
        add_filter('pre_option_' . OperationsModeStore::OPTION, $inStaging);
        $staging = modeSwitchAnswer(OperationsMode::STAGING);
        remove_filter('pre_option_' . OperationsModeStore::OPTION, $inStaging);

        expect($production)->toBe('refused 403')
            ->and($maintenance)->toBe('refused 403')
            ->and($staging)->toBe('redirected')
            // Both refusals are of one ability in one PHP process, which stands for one request
            // here: counted once, as two posts of one request would be.
            ->and(array_column(refusalsOf($this->people['subscriber']), 'count', 'section'))->toBe(['corex-operations-security' => 1]);
    } finally {
        $keptMode === null ? delete_option(OperationsModeStore::OPTION) : update_option(OperationsModeStore::OPTION, $keptMode);
        $keptLog === null ? delete_option('corex_operations_mode_log') : update_option('corex_operations_mode_log', $keptLog, false);
    }
});

/*
 * The Settings routes spec 119 added follow Settings (spec 117, FR-002, FR-005): the provider test
 * and the Media library conversion ask for "Manage settings" and record a refusal under Settings.
 */
it('opens the provider test and the Media conversion routes for "Manage settings", and counts a refusal under Settings', function () {
    // A key saved on this site would be tried for real: the provider's answer is made up instead.
    add_filter('pre_http_request', $offline = static fn (): array => ['headers' => [], 'body' => '{}', 'response' => ['code' => 400, 'message' => 'Bad Request'], 'cookies' => []]);
    $routes = rest_get_server()->get_routes();
    expect($routes)->toHaveKey('/corex/v1/media/conversion')
        ->and($routes)->toHaveKey('/corex/v1/settings/providers/(?P<provider>pagespeed|cloudflare)/test');

    wp_set_current_user($this->people['subscriber']);
    $refusedTest  = screenRoute('POST', 'settings/providers/pagespeed/test')->get_status();
    $refusedMedia = screenRoute('GET', 'media/conversion')->get_status();
    $sections     = array_column(refusalsOf($this->people['subscriber']), 'count', 'section');

    ($this->allowForEditors)([CorexAbility::MANAGE_SETTINGS => AccessPolicy::EFFECT_ALLOW]);
    wp_set_current_user($this->people['editor']);

    expect($refusedTest)->toBe(403)
        ->and($refusedMedia)->toBe(403)
        ->and($sections)->toBe(['corex-settings-config' => 2])
        ->and(screenRoute('POST', 'settings/providers/pagespeed/test')->get_status())->not->toBe(403)
        ->and(screenRoute('GET', 'media/conversion')->get_status())->toBe(200);
    remove_filter('pre_http_request', $offline);
});

/*
 * The Assistant (spec 129) opens for "Access CoreX admin", from the screen map (DECISIONS #420,
 * amended 2026-11-11): site administrators see no change, and a refusal is counted under it.
 */
it('refuses the Assistant check route and save to someone without "Access CoreX admin", and counts both under the Assistant', function () {
    if (! isset(rest_get_server()->get_routes()['/corex/v1/assistant/check'])) {
        $this->markTestSkipped('The corex-assistant add-on is not active on this install.');
    }
    wp_set_current_user($this->people['editor']);
    $check = screenRoute('POST', 'assistant/check')->get_status();

    $_POST   = ['corex_assistant_nonce' => wp_create_nonce('corex_assistant_save')];
    $handler = static fn (): callable => static fn (string $message, string $title, array $args): never
        => throw new RuntimeException('refused ' . ($args['response'] ?? 0));
    add_filter('wp_die_handler', $handler);
    $save = 'saved';
    try {
        Boot::app()->container()->make(AssistantScreen::class)->save();
    } catch (RuntimeException $refused) {
        $save = $refused->getMessage();
    } finally {
        remove_filter('wp_die_handler', $handler);
        $_POST = [];
    }

    $counted = array_column(refusalsOf($this->people['editor']), 'count', 'section');
    wp_set_current_user($this->adminId);
    // A keyed service would be asked for real: its answer is made up instead.
    add_filter('pre_http_request', $offline = static fn (): array => ['headers' => [], 'body' => '{}', 'response' => ['code' => 401, 'message' => 'Unauthorized'], 'cookies' => []]);
    $administrator = screenRoute('POST', 'assistant/check')->get_status();
    remove_filter('pre_http_request', $offline);

    expect($check)->toBe(403)
        ->and($save)->toBe('refused 403')
        ->and($counted)->toBe(['corex-assistant' => 2])
        ->and($administrator)->toBe(200);
});
