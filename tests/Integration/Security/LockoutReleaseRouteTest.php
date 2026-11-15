<?php

/**
 * The lockout release routes on a real WordPress (spec 124, User Story 8 scenarios 1 to 6, FR-016,
 * FR-017, SC-004 on the server).
 *
 * Every request goes through `rest_do_request()` with the REST nonce, so the routes' permission check
 * and their answers are the ones the screen gets. The lockouts are started through WordPress's own
 * sign-in path, by a login guard with this test's limits from a documentation address, and every row
 * the tests write is removed afterwards: the attempts by that address (keyed and plain), the lockout
 * notices, the lockout and release activity events by id, and the access-denied events of the people
 * the tests create. A lockout of the site's own that a "release all" or the recovery command ends is
 * put back as it was found, and so are the login settings and the access audit log. No email leaves
 * the machine: `pre_wp_mail` keeps each one here.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Activity\ActivityRepository;
use Corex\Boot;
use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Activity\SecurityActivityRecorder;
use Corex\Config\Notifications\Lockouts\LoginLockoutNotice;
use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Security\LockoutRows;
use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginLockoutStore;
use Corex\Config\Security\LoginProtection\LoginProtectionEnforcer;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Config\Security\OperationsSecurityScreen;
use Corex\Database\Schema\Migrator;
use Corex\Events\EventDispatcher;
use Corex\Tests\Support\OptionRow;

/** An address reserved for documentation: no real sign-in comes from it. */
const LOCKOUT_RELEASE_IP = '198.51.100.91';

const LOCKOUT_RELEASE_THRESHOLD = 3;

function lockoutReleaseTable(string $name): string
{
    return (new Migrator())->fullName($name);
}

function lockoutReleasePolicy(): LoginProtectionPolicy
{
    return Boot::app()->container()->make(LoginProtectionPolicy::class);
}

/** A release route, asked as the current user with their REST nonce, with a JSON body when given. */
function lockoutReleaseRoute(string $path, bool $nonce = true, ?array $body = null): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/security/lockouts/' . $path);
    if ($nonce) {
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    }
    if ($body !== null) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));
    }

    return rest_ensure_response(rest_do_request($request));
}

/** Fail to sign in as this username until the lockout starts, through WordPress's own path. */
function lockOutFromTheTestNetwork(string $username): void
{
    for ($attempt = 0; $attempt < LOCKOUT_RELEASE_THRESHOLD; $attempt++) {
        wp_authenticate($username, 'not-the-password-' . $attempt);
    }
}

/** The panel's row of the lockout from the test network under this username. */
function lockoutReleaseRow(string $username): array
{
    $policy = lockoutReleasePolicy();
    $identity = $policy->identityFingerprint($policy->identityHashOf($username));
    $network = $policy->networkFingerprint($policy->networkHashOf(LOCKOUT_RELEASE_IP));
    $rows = Boot::app()->container()->make(LockoutRows::class)->rows(new DateTimeImmutable('now'));
    $mine = array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['identity'] === $identity && $row['network'] === $network,
    ));

    expect($mine)->toHaveCount(1);

    return $mine[0];
}

/** The activity events of this kind written since the test began. */
function lockoutReleaseEvents(int $since, string $kind): array
{
    global $wpdb;

    $ids = $wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . lockoutReleaseTable('activity_events') . ' WHERE id > %d AND kind = %s ORDER BY id ASC',
        $since,
        $kind,
    ));
    $repository = Boot::app()->container()->make(ActivityRepository::class);

    return array_map(static fn (string $id) => $repository->find((int) $id), $ids);
}

/** A lockout row stored directly, for the states the sign-in path cannot reach on demand. */
function storeLockoutRow(string $username, DateTimeImmutable $lockedUntil, string $reason = 'threshold_exceeded'): int
{
    $policy = lockoutReleasePolicy();

    return storeAttemptRow(
        $policy->identityHashOf($username),
        $policy->networkHashOf(LOCKOUT_RELEASE_IP),
        LoginAttemptRecord::LOCKED,
        new DateTimeImmutable('-1 hour'),
        $lockedUntil,
        $reason,
    );
}

/** One attempt row stored directly under these hashes, keyed or plain, through the real store. */
function storeAttemptRow(
    string $identityHash,
    string $networkHash,
    string $outcome,
    DateTimeImmutable $occurredAt,
    ?DateTimeImmutable $lockedUntil = null,
    string $reason = 'threshold_exceeded',
): int {
    global $wpdb;

    Boot::app()->container()->make(WpLoginAttemptStore::class)->record(new LoginAttemptRecord(
        identityHash: $identityHash,
        networkHash: $networkHash,
        outcome: $outcome,
        reasonCode: $outcome === LoginAttemptRecord::LOCKED ? $reason : 'invalid_credentials',
        userId: null,
        occurredAt: $occurredAt,
        retentionUntil: $occurredAt->modify('+30 days'),
        lockedUntil: $lockedUntil,
    ));

    return (int) $wpdb->insert_id;
}

/** How many lockout rows the table holds for this username from the test network, keyed. */
function lockoutRowsOf(string $username): int
{
    global $wpdb;
    $policy = lockoutReleasePolicy();

    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . lockoutReleaseTable(LoginAttemptTable::NAME) . ' WHERE identity_hash = %s AND network_hash = %s AND outcome = %s',
        $policy->identityHashOf($username),
        $policy->networkHashOf(LOCKOUT_RELEASE_IP),
        LoginAttemptRecord::LOCKED,
    ));
}

/** An account the tests create, with the password it signs in with. */
function lockoutReleaseAccount(object $test, string $prefix, array $fields = []): array
{
    $login = $prefix . wp_generate_password(6, false);
    $password = wp_generate_password(20);
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'role' => 'subscriber'] + $fields);
    expect($id)->toBeInt();
    $test->users[] = $id;

    return [$login, $password, $id];
}

/** The stored lockout row as the table holds it. */
function lockoutReleaseStoredRow(int $id): array
{
    global $wpdb;

    return (array) $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . lockoutReleaseTable(LoginAttemptTable::NAME) . ' WHERE id = %d',
        $id,
    ), ARRAY_A);
}

beforeEach(function () {
    global $wpdb;
    $container = Boot::app()->container();

    $this->noticesBefore = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . lockoutReleaseTable(NotificationTable::NAME));
    $this->activityBefore = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . lockoutReleaseTable('activity_events'));
    $this->users = [];
    $this->settingsRow = OptionRow::capture(LoginProtectionSettingsStore::OPTION);
    $this->auditRow = OptionRow::capture('corex_access_audit_log');

    // The site's own active lockouts, so a "release all" can put them back as they were.
    $this->siteLockouts = $wpdb->get_results($wpdb->prepare(
        'SELECT id, locked_until, reason_code FROM ' . lockoutReleaseTable(LoginAttemptTable::NAME) . ' WHERE outcome = %s AND locked_until > %s',
        LoginAttemptRecord::LOCKED,
        gmdate('Y-m-d H:i:s'),
    ), ARRAY_A);

    $this->server = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = LOCKOUT_RELEASE_IP;
    $_SERVER['HTTP_USER_AGENT'] = 'CoreX Test Browser';

    $this->mail = [];
    $this->keepMail = function (mixed $short, array $atts): bool {
        $this->mail[] = $atts;

        return true;
    };
    add_filter('pre_wp_mail', $this->keepMail, PHP_INT_MAX, 2);

    // The site's own guard steps aside, so this test's limits apply whatever the site saved.
    $this->siteEnforcer = $container->make(LoginProtectionEnforcer::class);
    $this->siteBlocks = has_filter('authenticate', [$this->siteEnforcer, 'blockLockedOut']);
    $this->siteRecords = has_action('wp_login_failed', [$this->siteEnforcer, 'recordFailure']);
    remove_filter('authenticate', [$this->siteEnforcer, 'blockLockedOut'], 30);
    remove_action('wp_login_failed', [$this->siteEnforcer, 'recordFailure'], 10);

    $settings = new LoginProtectionSettings(
        enabled: true,
        customSlug: 'team-login',
        blockDefaultEndpoints: false,
        threshold: LOCKOUT_RELEASE_THRESHOLD,
        windowSeconds: 600,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: false,
    );
    $this->enforcer = new LoginProtectionEnforcer(
        new LoginProtectionService(
            new LoginProtectionPolicy($settings, $container->make(LoginHashKey::class)),
            $container->make(WpLoginAttemptStore::class),
            $container->make(LoginAccounts::class),
        ),
        new ClientIpResolver($settings),
        $settings,
        $container->make(EventDispatcher::class),
    );
    $this->enforcer->register();

    $this->admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($this->admin);
});

afterEach(function () {
    global $wpdb;

    wp_set_current_user(0);
    remove_filter('authenticate', [$this->enforcer, 'blockLockedOut'], 30);
    remove_action('wp_login_failed', [$this->enforcer, 'recordFailure'], 10);
    remove_action('wp_login', [$this->enforcer, 'recordSuccess'], 10);
    if ($this->siteBlocks !== false) {
        add_filter('authenticate', [$this->siteEnforcer, 'blockLockedOut'], 30, 3);
    }
    if ($this->siteRecords !== false) {
        add_action('wp_login_failed', [$this->siteEnforcer, 'recordFailure'], 10, 1);
    }
    remove_filter('pre_wp_mail', $this->keepMail, PHP_INT_MAX);

    foreach ([lockoutReleasePolicy()->networkHashOf(LOCKOUT_RELEASE_IP), lockoutReleasePolicy()->legacyNetworkHashOf(LOCKOUT_RELEASE_IP)] as $network) {
        $wpdb->delete(lockoutReleaseTable(LoginAttemptTable::NAME), ['network_hash' => $network]);
    }
    foreach ($this->siteLockouts as $row) {
        $wpdb->update(
            lockoutReleaseTable(LoginAttemptTable::NAME),
            ['locked_until' => $row['locked_until'], 'reason_code' => $row['reason_code']],
            ['id' => (int) $row['id']],
        );
    }
    $notices = $wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . lockoutReleaseTable(NotificationTable::NAME) . ' WHERE id > %d AND type = %s',
        $this->noticesBefore,
        LoginLockoutNotice::TYPE,
    ));
    foreach ($notices as $id) {
        $wpdb->delete(lockoutReleaseTable('notification_user_state'), ['notification_id' => (int) $id]);
        $wpdb->delete(lockoutReleaseTable(NotificationTable::NAME), ['id' => (int) $id]);
    }
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . lockoutReleaseTable('activity_events') . ' WHERE id > %d AND kind IN (%s, %s, %s)',
        $this->activityBefore,
        SecurityActivityRecorder::LOGIN_LOCKED,
        SecurityActivityRecorder::LOGIN_RELEASED,
        SecurityActivityRecorder::LOGIN_RELEASED_ALL,
    ));
    // A refused request writes an access.denied event for the person refused: only this test's people.
    foreach ($this->users as $userId) {
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . lockoutReleaseTable('activity_events') . ' WHERE id > %d AND kind = %s AND actor_id = %d',
            $this->activityBefore,
            SecurityActivityRecorder::ACCESS_DENIED,
            $userId,
        ));
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $userId) {
        wp_delete_user($userId);
    }
    OptionRow::restore(LoginProtectionSettingsStore::OPTION, $this->settingsRow);
    OptionRow::restore('corex_access_audit_log', $this->auditRow);
    $_SERVER = $this->server;
});

it('FR-017: registers the two release routes in corex/v1, for POST only', function () {
    $routes = rest_get_server()->get_routes('corex/v1');

    expect($routes)->toHaveKey('/corex/v1/security/lockouts/(?P<id>[0-9]+)/release')
        ->and($routes)->toHaveKey('/corex/v1/security/lockouts/release-all')
        ->and(array_keys($routes['/corex/v1/security/lockouts/release-all'][0]['methods']))->toBe(['POST']);
});

it('FR-016, User Story 8 scenario 1: the panel names the account, says when the lockout ends, and gives the row a release id', function () {
    $login = 'corex-release-sara-' . wp_generate_password(6, false);
    $this->users[] = (int) wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);

    lockOutFromTheTestNetwork($login);
    $row = lockoutReleaseRow($login);
    $screen = Boot::app()->container()->make(OperationsSecurityScreen::class);
    $panel = (new ReflectionMethod($screen, 'lockoutsPayload'))->invoke($screen);

    expect($row['account'])->toBe($login)
        ->and($row['active'])->toBeTrue()
        ->and($row['released'])->toBeFalse()
        ->and($row['id'])->toMatch('/^[1-9][0-9]*$/')
        ->and(strtotime($row['locked_until']))->toBeGreaterThan(time())
        // The screen draws on load the rows a release answers with.
        ->and($panel)->toContain($row);
});

it('FR-017, User Story 8 scenario 2, SC-004: a released account signs in at once from the same network, and the activity log says who released whose lockout', function () {
    $login = 'corex-release-sara-' . wp_generate_password(6, false);
    $password = wp_generate_password(20);
    $sara = (int) wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'role' => 'subscriber', 'display_name' => 'Sara Adel']);
    $this->users[] = $sara;

    lockOutFromTheTestNetwork($login);
    expect(wp_authenticate($login, $password))->toBeInstanceOf(WP_Error::class);
    $row = lockoutReleaseRow($login);

    $response = lockoutReleaseRoute($row['id'] . '/release');
    $data = $response->get_data();
    $signedIn = wp_authenticate($login, $password);
    $events = lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED);
    $redrawn = array_values(array_filter($data['data']['lockouts'], static fn (array $shown): bool => $shown['id'] === $row['id']));

    expect($response->get_status())->toBe(200)
        ->and($data['ok'])->toBeTrue()
        ->and($data['message'])->not->toBe('')
        ->and($data['data']['released'])->toBe(1)
        ->and($redrawn)->toHaveCount(1)
        ->and($redrawn[0]['active'])->toBeFalse()
        ->and($redrawn[0]['released'])->toBeTrue()
        ->and($redrawn[0]['reason'])->toBe(LoginAttemptRecord::RELEASED_BY_OWNER)
        ->and($signedIn)->toBeInstanceOf(WP_User::class)
        ->and($signedIn->ID)->toBe($sara)
        ->and($events)->toHaveCount(1)
        ->and($events[0]->actorId)->toBe($this->admin)
        ->and($events[0]->context['account_id'])->toBe($sara);

    $sentence = Boot::app()->container()->make(ActivitySentences::class)->of($events[0])['text'];
    // Named as the lockout notice names the account, by one rule (LoginAccounts::nameOf()).
    expect($sentence)->toContain(get_userdata($this->admin)->display_name)
        ->and(Boot::app()->container()->make(LoginAccounts::class)->nameOf($sara))->toBe('Sara Adel (' . $login . ')')
        ->and($sentence)->toContain('Sara Adel (' . $login . ')');
});

it('FR-017: after a release, one more mistyped password does not lock the person out again', function () {
    [$login, $password] = lockoutReleaseAccount($this, 'corex-release-typo-');

    lockOutFromTheTestNetwork($login);
    lockoutReleaseRoute(lockoutReleaseRow($login)['id'] . '/release');
    // A failure in the second of the release is not after it, so the typo waits for the next one.
    sleep(1);
    wp_authenticate($login, 'one-typo');

    // The attempt that would start a lockout is answered as a wrong password too, so the answer to
    // the typo proves nothing: what the typo left behind does.
    expect(lockoutRowsOf($login))->toBe(1)
        ->and(wp_authenticate($login, $password))->toBeInstanceOf(WP_User::class);
});

it('FR-017: after a release the count starts again, so as many failures as the limit lock the person out again', function () {
    [$login, $password] = lockoutReleaseAccount($this, 'corex-release-again-');

    lockOutFromTheTestNetwork($login);
    lockoutReleaseRoute(lockoutReleaseRow($login)['id'] . '/release');
    sleep(1);
    lockOutFromTheTestNetwork($login);
    $refused = wp_authenticate($login, $password);

    expect(lockoutRowsOf($login))->toBe(2)
        ->and($refused)->toBeInstanceOf(WP_Error::class)
        ->and($refused->get_error_codes())->toContain('corex_login_locked');
});

it('FR-017: after wp corex security reset-login, one more mistyped password does not lock the person out again', function () {
    [$login, $password] = lockoutReleaseAccount($this, 'corex-recovery-typo-');

    lockOutFromTheTestNetwork($login);
    $id = (int) lockoutReleaseRow($login)['id'];
    $container = Boot::app()->container();
    $result = (new SecurityResetLoginCommand(
        $container->make(LoginLockoutStore::class),
        $container->make(LoginProtectionSettingsStore::class),
    ))->restore(new DateTimeImmutable('now'));
    sleep(1);
    wp_authenticate($login, 'one-typo');

    expect($result['released_lockouts'])->toBe(1 + count($this->siteLockouts))
        ->and(lockoutReleaseStoredRow($id)['reason_code'])->toBe(LoginAttemptRecord::RELEASED_BY_RECOVERY)
        ->and(lockoutRowsOf($login))->toBe(1)
        ->and(wp_authenticate($login, $password))->toBeInstanceOf(WP_User::class);
});

it('FR-017: after wp corex security reset-login the count starts again, so as many failures as the limit lock again', function () {
    [$login, $password] = lockoutReleaseAccount($this, 'corex-recovery-again-');
    $container = Boot::app()->container();

    lockOutFromTheTestNetwork($login);
    (new SecurityResetLoginCommand(
        $container->make(LoginLockoutStore::class),
        $container->make(LoginProtectionSettingsStore::class),
    ))->restore(new DateTimeImmutable('now'));
    sleep(1);
    lockOutFromTheTestNetwork($login);
    $refused = wp_authenticate($login, $password);

    expect(lockoutRowsOf($login))->toBe(2)
        ->and($refused)->toBeInstanceOf(WP_Error::class)
        ->and($refused->get_error_codes())->toContain('corex_login_locked');
});

it('FR-017: releasing one lockout leaves another username from the same network locked', function () {
    [$released, $releasedPassword] = lockoutReleaseAccount($this, 'corex-release-one-');
    [$kept, $keptPassword] = lockoutReleaseAccount($this, 'corex-release-kept-');

    lockOutFromTheTestNetwork($released);
    lockOutFromTheTestNetwork($kept);
    $keptId = (int) lockoutReleaseRow($kept)['id'];
    $keptBefore = lockoutReleaseStoredRow($keptId);

    $response = lockoutReleaseRoute(lockoutReleaseRow($released)['id'] . '/release');
    $refused = wp_authenticate($kept, $keptPassword);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['released'])->toBe(1)
        ->and(wp_authenticate($released, $releasedPassword))->toBeInstanceOf(WP_User::class)
        ->and(lockoutReleaseStoredRow($keptId))->toBe($keptBefore)
        ->and($refused)->toBeInstanceOf(WP_Error::class)
        ->and($refused->get_error_codes())->toContain('corex_login_locked');
});

it('FR-017: releases the lockout the URL names, not one a body id names', function () {
    $named = 'corex-release-url-' . wp_generate_password(6, false);
    $other = 'corex-release-body-' . wp_generate_password(6, false);
    lockOutFromTheTestNetwork($named);
    lockOutFromTheTestNetwork($other);
    $otherId = (int) lockoutReleaseRow($other)['id'];
    $otherBefore = lockoutReleaseStoredRow($otherId);

    $response = lockoutReleaseRoute(lockoutReleaseRow($named)['id'] . '/release', body: ['id' => $otherId]);

    expect($response->get_status())->toBe(200)
        ->and(lockoutReleaseRow($named)['released'])->toBeTrue()
        ->and(lockoutReleaseStoredRow($otherId))->toBe($otherBefore);
});

it('FR-017: a lockout released already, though its first end is still ahead, says it has ended and is not changed', function () {
    $id = storeLockoutRow('corex-release-owner-' . wp_generate_password(6, false), new DateTimeImmutable('+10 minutes'), LoginAttemptRecord::RELEASED_BY_OWNER);
    $before = lockoutReleaseStoredRow($id);

    $response = lockoutReleaseRoute($id . '/release');

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['code'])->toBe('lockout_already_ended')
        ->and(lockoutReleaseStoredRow($id))->toBe($before)
        ->and(lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED))->toBe([]);
});

it('FR-017, FR-019: a lockout stored before the update under the plain pair is released by its id, and its failures stop counting', function () {
    [$login, $password] = lockoutReleaseAccount($this, 'corex-release-legacy-');
    $policy = lockoutReleasePolicy();
    $identity = $policy->legacyIdentityHashOf($login);
    $network = $policy->legacyNetworkHashOf(LOCKOUT_RELEASE_IP);
    for ($attempt = 0; $attempt < LOCKOUT_RELEASE_THRESHOLD; $attempt++) {
        storeAttemptRow($identity, $network, LoginAttemptRecord::FAILED, new DateTimeImmutable('-2 minutes'));
    }
    $id = storeAttemptRow($identity, $network, LoginAttemptRecord::LOCKED, new DateTimeImmutable('-2 minutes'), new DateTimeImmutable('+20 minutes'));
    expect(wp_authenticate($login, $password))->toBeInstanceOf(WP_Error::class);

    $response = lockoutReleaseRoute($id . '/release');
    $signedIn = wp_authenticate($login, $password);
    sleep(1);
    wp_authenticate($login, 'one-typo');

    expect($response->get_status())->toBe(200)
        ->and(lockoutReleaseStoredRow($id)['reason_code'])->toBe(LoginAttemptRecord::RELEASED_BY_OWNER)
        ->and($signedIn)->toBeInstanceOf(WP_User::class)
        // The plain pair's three failures no longer count beside the keyed typo.
        ->and(lockoutRowsOf($login))->toBe(0)
        ->and(wp_authenticate($login, $password))->toBeInstanceOf(WP_User::class);
});

it('FR-016, User Story 8 scenario 3: an unknown username reads by its fingerprint, in the panel and the activity log, never as typed', function () {
    $typed = 'nobody-here-' . wp_generate_password(8, false);

    lockOutFromTheTestNetwork($typed);
    $row = lockoutReleaseRow($typed);
    $response = lockoutReleaseRoute($row['id'] . '/release');
    $events = lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED);
    $sentence = Boot::app()->container()->make(ActivitySentences::class)->of($events[0])['text'];

    expect($row['account'])->toBe('')
        ->and($row['identity'])->toMatch('/^[0-9a-f]{12}$/')
        ->and((string) wp_json_encode($response->get_data()))->not->toContain($typed)
        ->and($events[0]->context['account_id'])->toBe(0)
        ->and($sentence)->toContain($row['identity'])
        ->and($sentence)->not->toContain($typed)
        ->and((string) wp_json_encode($events[0]->context))->not->toContain($typed)
        ->and((string) wp_json_encode($events[0]->context))->not->toContain(LOCKOUT_RELEASE_IP);
});

it('FR-017, User Story 8 scenario 4: release all ends every active lockout, recorded once, and changes no other setting', function () {
    $first = 'corex-release-all-a-' . wp_generate_password(6, false);
    $second = 'corex-release-all-b-' . wp_generate_password(6, false);
    lockOutFromTheTestNetwork($first);
    lockOutFromTheTestNetwork($second);
    $ended = storeLockoutRow('corex-release-all-ended-' . wp_generate_password(6, false), new DateTimeImmutable('-1 hour'));
    $endedBefore = lockoutReleaseStoredRow($ended);
    $settings = get_option(LoginProtectionSettingsStore::OPTION);
    $mode = get_option(OperationsModeStore::OPTION);

    $response = lockoutReleaseRoute('release-all');
    $data = $response->get_data();
    $active = array_filter($data['data']['lockouts'], static fn (array $row): bool => $row['active']);
    $events = lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED_ALL);

    expect($response->get_status())->toBe(200)
        ->and($data['data']['released'])->toBe(2 + count($this->siteLockouts))
        ->and($active)->toBe([])
        ->and(lockoutReleaseRow($first)['released'])->toBeTrue()
        ->and(lockoutReleaseRow($second)['released'])->toBeTrue()
        // A lockout that had ended on its own keeps its end and its reason.
        ->and(lockoutReleaseStoredRow($ended))->toBe($endedBefore)
        ->and(get_option(LoginProtectionSettingsStore::OPTION))->toBe($settings)
        ->and(get_option(OperationsModeStore::OPTION))->toBe($mode)
        ->and($events)->toHaveCount(1)
        ->and($events[0]->actorId)->toBe($this->admin)
        ->and($events[0]->context['count'])->toBe(2 + count($this->siteLockouts));
});

it('FR-017, User Story 8 scenario 4: release all with no active lockout says so, changes nothing and records nothing', function () {
    lockoutReleaseRoute('release-all');
    $before = (int) $GLOBALS['wpdb']->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . lockoutReleaseTable('activity_events'));

    $response = lockoutReleaseRoute('release-all');

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['ok'])->toBeFalse()
        ->and($response->get_data()['code'])->toBe('no_active_lockouts')
        ->and($response->get_data()['message'])->not->toBe('')
        ->and($response->get_data()['details']['lockouts'])->toBeArray()
        ->and(lockoutReleaseEvents($before, SecurityActivityRecorder::LOGIN_RELEASED_ALL))->toBe([]);
});

it('FR-017, User Story 8 scenario 5: releasing a lockout that has already ended says so and changes nothing', function () {
    $id = storeLockoutRow('corex-release-ended-' . wp_generate_password(6, false), new DateTimeImmutable('-5 minutes'));
    $before = lockoutReleaseStoredRow($id);

    $response = lockoutReleaseRoute($id . '/release');

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['code'])->toBe('lockout_already_ended')
        ->and($response->get_data()['message'])->not->toBe('')
        ->and($response->get_data()['details']['lockouts'])->toBeArray()
        ->and(lockoutReleaseStoredRow($id))->toBe($before)
        ->and(lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED))->toBe([]);
});

it('FR-017: releasing the same lockout twice ends it once and says the second time that it has ended', function () {
    $login = 'corex-release-twice-' . wp_generate_password(6, false);
    lockOutFromTheTestNetwork($login);
    $id = lockoutReleaseRow($login)['id'];

    $first = lockoutReleaseRoute($id . '/release');
    $stored = lockoutReleaseStoredRow((int) $id);
    $second = lockoutReleaseRoute($id . '/release');

    expect($first->get_status())->toBe(200)
        ->and($second->get_status())->toBe(409)
        ->and($second->get_data()['code'])->toBe('lockout_already_ended')
        ->and(lockoutReleaseStoredRow((int) $id))->toBe($stored)
        ->and(lockoutReleaseEvents($this->activityBefore, SecurityActivityRecorder::LOGIN_RELEASED))->toHaveCount(1);
});

it('FR-017: an id that names no lockout is not found', function () {
    global $wpdb;
    $none = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . lockoutReleaseTable(LoginAttemptTable::NAME)) + 1000;

    $response = lockoutReleaseRoute($none . '/release');

    expect($response->get_status())->toBe(404)
        ->and($response->get_data()['code'])->toBe('lockout_not_found')
        ->and($response->get_data()['message'])->not->toBe('');
});

it('FR-017, FR-004c, User Story 8 scenario 6: an Editor gets the standard access-denied answer from both routes, and nothing is released', function () {
    $editor = wp_insert_user([
        'user_login' => 'corex-release-editor-' . wp_generate_password(6, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-release-', true) . '@example.test',
        'role'       => 'editor',
    ]);
    expect($editor)->toBeInt();
    $this->users[] = $editor;
    expect(user_can($editor, CorexAbility::MANAGE_OPERATIONS))->toBeFalse();

    $login = 'corex-release-denied-' . wp_generate_password(6, false);
    lockOutFromTheTestNetwork($login);
    $row = lockoutReleaseRow($login);
    $stored = lockoutReleaseStoredRow((int) $row['id']);

    wp_set_current_user($editor);
    $refusals = static fn (): array => array_values(array_filter(
        Boot::app()->container()->make(AccessAuditLog::class)->entries(),
        static fn (array $entry): bool => $entry['user'] === $editor,
    ));
    $one = lockoutReleaseRoute($row['id'] . '/release');
    $afterOne = $refusals();
    $all = lockoutReleaseRoute('release-all');

    expect($one->get_status())->toBe(403)
        ->and($one->get_data()['code'])->toBe('rest_forbidden')
        ->and($all->get_status())->toBe(403)
        ->and($all->get_data()['code'])->toBe('rest_forbidden')
        // Each refusal is counted once in the access log, under the screen.
        ->and($afterOne)->toHaveCount(1)
        ->and($afterOne[0])->toMatchArray(['section' => 'corex-operations-security', 'count' => 1])
        ->and($refusals())->toHaveCount(1)
        ->and($refusals()[0])->toMatchArray(['section' => 'corex-operations-security', 'count' => 2])
        ->and(lockoutReleaseStoredRow((int) $row['id']))->toBe($stored);
});

it('FR-017: refuses a release without the REST nonce, even from an administrator', function () {
    $login = 'corex-release-nonce-' . wp_generate_password(6, false);
    lockOutFromTheTestNetwork($login);
    $row = lockoutReleaseRow($login);

    expect(lockoutReleaseRoute($row['id'] . '/release', nonce: false)->get_status())->toBe(403)
        ->and(lockoutReleaseRoute('release-all', nonce: false)->get_status())->toBe(403)
        ->and(lockoutReleaseRow($login)['active'])->toBeTrue();
});
