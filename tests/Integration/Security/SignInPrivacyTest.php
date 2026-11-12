<?php

/**
 * Sign-in identities stay unreadable wherever CoreX records them (spec 124, US-SEC-10, SC-003), on
 * real WordPress.
 *
 * A lockout is caused through WordPress's own `authenticate` path, with a username and an address no
 * real sign-in uses, and then every CoreX table, option, user setting and post on the site, and every
 * email WordPress was asked to send, is searched for either. Neither may be found (FR-020). The same
 * file proves the lockout names its account when there is one (FR-016) and that a site updating with
 * an active lockout and older lockout notices keeps the lockout and rewrites the notices (FR-019,
 * FR-020, scenario 5).
 *
 * Every row these tests write is removed afterwards, and only theirs: the attempts by the address
 * they come from, notices and activity events by id. No email leaves the machine: `pre_wp_mail`
 * keeps each one here, because corex.local delivers through a real mailbox.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Activity\SecurityActivityRecorder;
use Corex\Config\Notifications\Lockouts\LoginLockoutNotice;
use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginProtectionEnforcer;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Config\Security\OperationsSecurityScreen;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Events\EventDispatcher;
use Corex\Notifications\NotificationRecipient;

/** An address reserved for documentation: no real sign-in comes from it. */
const SIGN_IN_PRIVACY_IP = '198.51.100.77';

const SIGN_IN_PRIVACY_THRESHOLD = 3;

function signInPrivacyTable(string $name): string
{
    return (new Migrator())->fullName($name);
}

function signInPrivacyPolicy(): LoginProtectionPolicy
{
    return Boot::app()->container()->make(LoginProtectionPolicy::class);
}

/**
 * Every place in a CoreX store that holds the text, as "table.column", "option:name", "usermeta:key"
 * or "post:id". The search ignores case, so a value stored in lower case is found as well.
 *
 * @return list<string>
 */
function corexPlacesHolding(string $needle): array
{
    global $wpdb;

    $like = '%' . $wpdb->esc_like($needle) . '%';
    $found = [];

    foreach ($wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'corex_') . '%')) as $table) {
        foreach ($wpdb->get_col('SHOW COLUMNS FROM `' . $table . '`') as $column) {
            $hits = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM `' . $table . '` WHERE CAST(`' . $column . '` AS CHAR) LIKE %s',
                $like,
            ));
            if ($hits > 0) {
                $found[] = $table . '.' . $column;
            }
        }
    }

    foreach ($wpdb->get_col($wpdb->prepare(
        'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s AND option_value LIKE %s',
        '%corex%',
        $like,
    )) as $option) {
        $found[] = 'option:' . $option;
    }

    foreach ($wpdb->get_col($wpdb->prepare(
        'SELECT meta_key FROM ' . $wpdb->usermeta . ' WHERE meta_key LIKE %s AND meta_value LIKE %s',
        '%corex%',
        $like,
    )) as $key) {
        $found[] = 'usermeta:' . $key;
    }

    foreach ($wpdb->get_col($wpdb->prepare(
        'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type LIKE %s AND (post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s)',
        'corex%',
        $like,
        $like,
        $like,
    )) as $postId) {
        $found[] = 'post:' . $postId;
    }

    return $found;
}

/** Fail to sign in as this username until the lockout starts, through WordPress's own path. */
function failUntilLockedOut(string $username): void
{
    for ($attempt = 0; $attempt < SIGN_IN_PRIVACY_THRESHOLD; $attempt++) {
        wp_authenticate($username, 'not-the-password-' . $attempt);
    }
}

/** The lockout notices written since the test began. */
function lockoutNoticesSince(int $id): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . signInPrivacyTable(NotificationTable::NAME) . ' WHERE id > %d AND type = %s ORDER BY id ASC',
        $id,
        LoginLockoutNotice::TYPE,
    ), ARRAY_A);
}

beforeEach(function () {
    global $wpdb;
    $container = Boot::app()->container();

    $this->noticesBefore  = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . signInPrivacyTable(NotificationTable::NAME));
    $this->activityBefore = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . signInPrivacyTable('activity_events'));
    $this->schemaVersion  = get_option('corex_product_foundation_schema_version', null);
    $this->users = [];

    $this->server = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = SIGN_IN_PRIVACY_IP;
    $_SERVER['HTTP_USER_AGENT'] = 'CoreX Test Browser';

    // Every email WordPress is asked to send is kept here and none is sent.
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
        threshold: SIGN_IN_PRIVACY_THRESHOLD,
        windowSeconds: 600,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: true,
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
});

afterEach(function () {
    global $wpdb;

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

    // This test's attempts, under the keyed address and the plain one a "previous release" row used.
    foreach ([signInPrivacyPolicy()->networkHashOf(SIGN_IN_PRIVACY_IP), hash('sha256', SIGN_IN_PRIVACY_IP)] as $network) {
        $wpdb->delete(signInPrivacyTable(LoginAttemptTable::NAME), ['network_hash' => $network]);
    }

    $notices = $wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . signInPrivacyTable(NotificationTable::NAME) . ' WHERE id > %d AND type = %s',
        $this->noticesBefore,
        LoginLockoutNotice::TYPE,
    ));
    foreach ($notices as $id) {
        $wpdb->delete(signInPrivacyTable('notification_user_state'), ['notification_id' => (int) $id]);
        $wpdb->delete(signInPrivacyTable(NotificationTable::NAME), ['id' => (int) $id]);
    }
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . signInPrivacyTable('activity_events') . ' WHERE id > %d AND kind = %s',
        $this->activityBefore,
        SecurityActivityRecorder::LOGIN_LOCKED,
    ));

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $userId) {
        wp_delete_user($userId);
    }

    if ($this->schemaVersion === null) {
        delete_option('corex_product_foundation_schema_version');
    } else {
        update_option('corex_product_foundation_schema_version', $this->schemaVersion);
    }
    $_SERVER = $this->server;
});

it('SC-003, US-SEC-10 scenarios 1 and 3: after a lockout of an unknown username, no CoreX store and no email holds the username or the address', function () {
    $username = 'Sc003-Nobody-' . wp_generate_password(10, false);

    failUntilLockedOut($username);

    $notices = lockoutNoticesSince($this->noticesBefore);
    $rendered = json_decode((string) ($notices[0]['rendered_json'] ?? ''), true);
    expect($notices)->toHaveCount(1)
        // The lockout happened and was announced: the notice says what it is.
        ->and($rendered['title'])->toContain('unknown username')
        ->and($rendered['body'])->toContain(signInPrivacyPolicy()->fingerprint(signInPrivacyPolicy()->identityHashOf($username)))
        ->and(corexPlacesHolding($username))->toBe([])
        ->and(corexPlacesHolding(SIGN_IN_PRIVACY_IP))->toBe([])
        ->and((string) wp_json_encode($this->mail))->not->toContain($username)
        ->and((string) wp_json_encode($this->mail))->not->toContain(SIGN_IN_PRIVACY_IP);
});

it('FR-019: stores the attempts under keyed values, never the plain hash of the username or the address', function () {
    global $wpdb;
    $username = 'sc003-keyed-' . wp_generate_password(10, false);

    failUntilLockedOut($username);

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT identity_hash, network_hash, outcome FROM ' . signInPrivacyTable(LoginAttemptTable::NAME) . ' WHERE network_hash = %s ORDER BY id ASC',
        signInPrivacyPolicy()->networkHashOf(SIGN_IN_PRIVACY_IP),
    ), ARRAY_A);

    expect(array_column($rows, 'outcome'))->toBe(['failed', 'failed', 'locked'])
        ->and(array_unique(array_column($rows, 'identity_hash')))->toBe([signInPrivacyPolicy()->identityHashOf($username)])
        ->and((int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . signInPrivacyTable(LoginAttemptTable::NAME) . ' WHERE identity_hash = %s OR network_hash = %s',
            hash('sha256', $username),
            hash('sha256', SIGN_IN_PRIVACY_IP),
        )))->toBe(0);
});

it('US-SEC-10 scenario 2, FR-016: a lockout of an account typed by its email names the account, records it, and keeps no address', function () {
    global $wpdb;
    $login = 'sc003acct' . strtolower(wp_generate_password(8, false));
    $email = $login . '-typed@example.test';
    $userId = wp_insert_user(['user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password(24)]);
    expect($userId)->toBeInt();
    $this->users[] = $userId;

    failUntilLockedOut($email);

    $notices = lockoutNoticesSince($this->noticesBefore);
    $rendered = json_decode((string) $notices[0]['rendered_json'], true);
    $locked = $wpdb->get_row($wpdb->prepare(
        'SELECT user_id FROM ' . signInPrivacyTable(LoginAttemptTable::NAME) . ' WHERE network_hash = %s AND outcome = %s',
        signInPrivacyPolicy()->networkHashOf(SIGN_IN_PRIVACY_IP),
        LoginAttemptRecord::LOCKED,
    ), ARRAY_A);

    expect($rendered['title'])->toContain($login)
        ->and($rendered['title'])->not->toContain('unknown username')
        ->and($notices[0]['dedup_key'])->toBe('security.lockout:account:' . $userId)
        ->and((int) $locked['user_id'])->toBe($userId)
        // The account is named; what was typed (its email) and the address are kept nowhere.
        ->and(corexPlacesHolding($email))->toBe([])
        ->and(corexPlacesHolding(SIGN_IN_PRIVACY_IP))->toBe([]);
});

it('US-SEC-10 scenario 5, FR-019: a lockout stored before the update still refuses the sign-in until it ends', function () {
    $username = 'sc005-held-' . wp_generate_password(10, false);
    $now = new DateTimeImmutable('now');
    Boot::app()->container()->make(WpLoginAttemptStore::class)->record(new LoginAttemptRecord(
        identityHash: hash('sha256', mb_strtolower($username)),
        networkHash: hash('sha256', SIGN_IN_PRIVACY_IP),
        outcome: LoginAttemptRecord::LOCKED,
        reasonCode: 'threshold_exceeded',
        userId: null,
        occurredAt: $now->modify('-1 minute'),
        retentionUntil: $now->modify('+30 days'),
        lockedUntil: $now->modify('+10 minutes'),
    ));

    $refused = wp_authenticate($username, 'any password');

    expect($refused)->toBeInstanceOf(WP_Error::class)
        ->and($refused->get_error_code())->toBe('corex_login_locked');
});

it('US-SEC-10 scenario 5, FR-020: the update rewrites a lockout notice stored before it, once', function () {
    global $wpdb;
    $username = 'Sc005-Notice-' . wp_generate_password(10, false);
    $table = signInPrivacyTable(NotificationTable::NAME);
    $now = gmdate('Y-m-d H:i:s');
    // The row exactly as the release before this one wrote it.
    $wpdb->insert($table, [
        'uuid'               => wp_generate_uuid4(),
        'type'               => LoginLockoutNotice::TYPE,
        'category'           => 'security',
        'severity'           => 'warning',
        'source_module'      => 'security',
        'source_type'        => 'login_identity',
        'source_id'          => $username,
        'title_key'          => 'notifications.security.lockout.title',
        'message_key'        => 'notifications.security.lockout.body',
        'rendered_json'      => wp_json_encode([
            'title' => 'Repeated login failures locked out “' . $username . '”',
            'body'  => 'The attempts came from ' . SIGN_IN_PRIVACY_IP . '. Review them in Operations & Security.',
        ]),
        'dedup_key'          => 'security.lockout:' . $username,
        'occurrences'        => 2,
        'first_occurred_at'  => $now,
        'latest_occurred_at' => $now,
        'created_at'         => $now,
        'updated_at'         => $now,
        'resolved_at'        => $now,
        'recipient_json'     => wp_json_encode(NotificationRecipient::forAbility(CorexAbility::MANAGE_OPERATIONS)->toArray()),
        'metadata_json'      => wp_json_encode(['client_ip' => SIGN_IN_PRIVACY_IP, 'locked_until' => '2026-11-12T10:15:00+00:00']),
    ]);
    $id = (int) $wpdb->insert_id;
    $runner = Boot::app()->container()->make(SiteMigrationRunner::class);

    // The site was on the release before: its foundation schema said 4.
    update_option('corex_product_foundation_schema_version', '4');
    $result = $runner->runForSite(get_current_blog_id());
    $rewritten = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE id = %d', $id), ARRAY_A);
    update_option('corex_product_foundation_schema_version', '4');
    $again = $runner->runForSite(get_current_blog_id());
    $twice = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE id = %d', $id), ARRAY_A);

    expect($result->outcome)->toBe(SiteMigrationOutcome::Migrated)
        ->and($result->componentsMigrated)->toContain('product-foundation')
        ->and(get_option('corex_product_foundation_schema_version'))->toBe('5')
        ->and(corexPlacesHolding($username))->toBe([])
        ->and(corexPlacesHolding(SIGN_IN_PRIVACY_IP))->toBe([])
        ->and(json_decode((string) $rewritten['rendered_json'], true)['title'])->toContain('unknown username')
        ->and($rewritten['dedup_key'])->toBe('security.lockout:legacy:' . $id)
        ->and(json_decode((string) $rewritten['metadata_json'], true)['locked_until'])->toBe('2026-11-12T10:15:00+00:00')
        // A resolved notice stays resolved, and keeps its count.
        ->and($rewritten['resolved_at'])->not->toBeNull()
        ->and((int) $rewritten['occurrences'])->toBe(2)
        ->and($again->succeeded())->toBeTrue()
        ->and($twice)->toBe($rewritten);
});

it('FR-020: the Lockouts panel shows an old unkeyed row by fingerprints that are no prefix of its hashes, and the same ones a notice shows', function () {
    $username = 'sc003-panel-' . wp_generate_password(10, false);
    $now = new DateTimeImmutable('now');
    $plainIdentity = hash('sha256', $username);
    $plainNetwork = hash('sha256', SIGN_IN_PRIVACY_IP);
    Boot::app()->container()->make(WpLoginAttemptStore::class)->record(new LoginAttemptRecord(
        identityHash: $plainIdentity,
        networkHash: $plainNetwork,
        outcome: LoginAttemptRecord::LOCKED,
        reasonCode: 'threshold_exceeded',
        userId: null,
        occurredAt: $now,
        retentionUntil: $now->modify('+30 days'),
        lockedUntil: $now->modify('+20 years'),
    ));
    $screen = Boot::app()->container()->make(OperationsSecurityScreen::class);

    $panel = (new ReflectionMethod($screen, 'lockoutsPayload'))->invoke($screen);
    $shown = (string) wp_json_encode($panel);
    $row = array_values(array_filter($panel, static fn (array $lockout): bool => $lockout['network'] === signInPrivacyPolicy()->fingerprint($plainNetwork)));

    expect($row)->toHaveCount(1)
        ->and($row[0]['identity'])->toBe(signInPrivacyPolicy()->fingerprint($plainIdentity))
        ->and($shown)->not->toContain(substr($plainNetwork, 0, 12))
        ->and($shown)->not->toContain(substr($plainIdentity, 0, 12))
        ->and($shown)->not->toContain(SIGN_IN_PRIVACY_IP);
});

it('FR-020: the Lockouts panel and the notice give a new lockout the same fingerprints', function () {
    $username = 'sc003-lineup-' . wp_generate_password(10, false);

    failUntilLockedOut($username);

    $screen = Boot::app()->container()->make(OperationsSecurityScreen::class);
    $panel = (new ReflectionMethod($screen, 'lockoutsPayload'))->invoke($screen);
    $metadata = json_decode((string) lockoutNoticesSince($this->noticesBefore)[0]['metadata_json'], true);
    $row = array_values(array_filter($panel, static fn (array $lockout): bool => $lockout['identity'] === $metadata['identity_fingerprint']));

    expect($row)->toHaveCount(1)
        ->and($row[0]['network'])->toBe($metadata['network_fingerprint']);
});
