<?php

/**
 * The login settings route on a real WordPress (spec 124, FR-015, FR-004c, User Story 7 scenarios 2
 * to 5).
 *
 * Every request goes through `rest_do_request()` with the REST nonce, so the route's permission check,
 * its arguments and its answer are the ones a screen gets. The stored option is put back exactly as
 * it was found, value and autoload, after each test; the access audit log too.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsLock;
use Corex\Support\Options\LoadedOptions;
use Corex\Tests\Support\OptionRow;

const LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE = [
    'hide_wp_login'            => false,
    'hide_admin'               => false,
    'limit_attempts'           => true,
    'enabled'                  => true,
    'custom_slug'              => 'team-door',
    'block_default_endpoints'  => false,
    'threshold'                => 5,
    'window_seconds'           => 30,
    'lockout_seconds'          => 900,
    'trusted_proxy_mode'       => false,
    'trusted_proxy_ranges'     => [],
    'retain_days'              => 30,
    'successful_login_logging' => true,
];

/** The login settings route, asked as the current user with their REST nonce. */
function loginSettingsRoute(string $method, array $body = [], bool $nonce = true): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/security/login-protection');
    if ($nonce) {
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    }
    if ($method !== 'GET') {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));
    }

    return rest_ensure_response(rest_do_request($request));
}

/** The option's row as the database holds it: the serialised value and its autoload. */
function loginSettingsRow(): ?array
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", LoginProtectionSettingsStore::OPTION),
        ARRAY_A,
    );

    return is_array($row) ? $row : null;
}

/**
 * A published page at `about`: the site's own when it has one, or one this test publishes, which
 * it deletes afterwards.
 */
function publishedAboutPage(object $test): void
{
    $page = get_page_by_path('about');
    if ($page instanceof WP_Post && $page->post_status === 'publish') {
        return;
    }

    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'About', 'post_name' => 'about']);
    expect($id)->toBeInt()->and(get_post_field('post_name', $id))->toBe('about');
    $test->pages[] = $id;
}

beforeEach(function () {
    $this->pages = [];
    $this->settingsRow = OptionRow::capture(LoginProtectionSettingsStore::OPTION);
    $this->auditRow = OptionRow::capture('corex_access_audit_log');
    $this->people = [];

    // As `wp option update` or a deploy script would leave it: a window that is not a whole minute.
    LoadedOptions::store(LoginProtectionSettingsStore::OPTION, LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE);
    wp_cache_delete(LoginProtectionSettingsStore::OPTION, 'options');

    wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);
});

afterEach(function () {
    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->people as $id) {
        wp_delete_user($id);
    }
    foreach ($this->pages as $id) {
        wp_delete_post($id, true);
    }

    OptionRow::restore(LoginProtectionSettingsStore::OPTION, $this->settingsRow);
    OptionRow::restore('corex_access_audit_log', $this->auditRow);
});

it('FR-015, scenario 2: keeps a 30-second window when only the maximum attempts are saved', function () {
    $response = loginSettingsRoute('POST', ['max_attempts' => 7]);
    $stored = get_option(LoginProtectionSettingsStore::OPTION);

    expect($response->get_status())->toBe(200)
        ->and($stored['threshold'])->toBe(7)
        ->and($stored['window_seconds'])->toBe(30)
        // Every key the request did not send is as it was.
        ->and(array_diff_key($stored, ['threshold' => true]))
        ->toEqual(array_diff_key(LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE, ['threshold' => true]))
        ->and($response->get_data()['login_protection']['max_attempts'])->toBe(7)
        ->and($response->get_data()['login_protection']['window_seconds'])->toBe(30)
        // Not a whole minute: no minutes, rather than a rounded number nobody chose.
        ->and($response->get_data()['login_protection']['window_minutes'])->toBeNull();
});

it('FR-015: answers a save with the settings as stored, the switches and the minutes', function () {
    $response = loginSettingsRoute('POST', ['window_minutes' => 10, 'lockout_minutes' => 20, 'hide_admin' => true]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['login_protection'])->toMatchArray([
            'hide_wp_login'   => false,
            'hide_admin'      => true,
            'limit_attempts'  => true,
            'window_minutes'  => 10,
            'window_seconds'  => 600,
            'lockout_minutes' => 20,
            'lockout_seconds' => 1200,
            'custom_slug'     => 'team-door',
        ])
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['window_seconds'])->toBe(600);
});

it('FR-015, scenario 3: refuses a lockout of 0 minutes with its range and saves nothing, not even the valid fields', function () {
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['max_attempts' => 9, 'lockout_minutes' => 0]);
    $data = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($data['ok'])->toBeFalse()
        ->and($data['code'])->toBe('validation_failed')
        ->and(array_keys($data['errors']))->toBe(['lockout_minutes'])
        ->and($data['errors']['lockout_minutes'])->toContain('1')->toContain(number_format_i18n(10080))
        ->and($data['details']['fields']['lockout_minutes'])->toBe(['reason' => 'out_of_range', 'min' => 1, 'max' => 10080])
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015, scenario 3: refuses 10.0.0.0/33 as a trusted proxy, naming that line, and saves nothing', function () {
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['trusted_proxies' => "203.0.113.0/24\n10.0.0.0/33"]);
    $data = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($data['errors']['trusted_proxies'])->toContain('10.0.0.0/33')->toContain('2')
        ->and($data['details']['fields']['trusted_proxies']['lines'])->toBe([['line' => 2, 'value' => '10.0.0.0/33']])
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015: refuses a trusted proxy range that trusts every address, naming the line, and saves nothing', function () {
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['trusted_proxies' => ['203.0.113.0/24', '0.0.0.0/0']]);
    $data = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($data['details']['fields']['trusted_proxies']['reason'])->toBe('trusts_every_address')
        ->and($data['errors']['trusted_proxies'])->toContain('0.0.0.0/0')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-013, FR-015: refuses hiding wp-login.php behind a cleared address with a sentence, and saves nothing', function () {
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['hide_wp_login' => true, 'custom_slug' => '']);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['errors']['custom_slug'])->toContain('wp-login.php')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015, scenario 4: saves 203.0.113.0/24 as a trusted proxy and answers with it, trusted', function () {
    $response = loginSettingsRoute('POST', ['trusted_proxies' => "203.0.113.0/24\n"]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['login_protection']['trusted_proxies'])->toBe(['203.0.113.0/24'])
        ->and($response->get_data()['login_protection']['trusted_proxy_mode'])->toBeTrue()
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['trusted_proxy_ranges'])->toBe(['203.0.113.0/24'])
        ->and(loginSettingsRoute('GET')->get_data()['login_protection']['trusted_proxies'])->toBe(['203.0.113.0/24']);
});

it('FR-004c, scenario 5: refuses an Editor with the standard access-denied answer, recorded once, and saves nothing', function () {
    $editor = wp_insert_user([
        'user_login' => 'corex-login-settings-editor-' . wp_generate_password(6, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-login-settings-', true) . '@example.test',
        'role'       => 'editor',
    ]);
    expect($editor)->toBeInt();
    $this->people[] = $editor;
    expect(user_can($editor, CorexAbility::MANAGE_OPERATIONS))->toBeFalse();

    wp_set_current_user($editor);
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['max_attempts' => 9]);
    $refusals = array_values(array_filter(
        Boot::app()->container()->make(AccessAuditLog::class)->entries(),
        static fn (array $entry): bool => $entry['user'] === $editor,
    ));

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['code'])->toBe('rest_forbidden')
        ->and($refusals)->toHaveCount(1)
        ->and($refusals[0])->toMatchArray(['section' => 'corex-operations-security', 'count' => 1])
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-004c: refuses a save without the REST nonce, even from an administrator', function () {
    $before = loginSettingsRow();

    expect(loginSettingsRoute('POST', ['max_attempts' => 9], nonce: false)->get_status())->toBe(403)
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015: refuses an address a published page already answers at, and saves nothing', function () {
    publishedAboutPage($this);
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['custom_slug' => 'about']);
    $data = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($data['details']['fields']['custom_slug'])->toBe(['reason' => 'address', 'detail' => 'taken_by_page'])
        ->and($data['errors']['custom_slug'])->toContain('published page')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-013, FR-015: says hiding wp-login.php needs an address of its own when the address is a page\'s', function () {
    publishedAboutPage($this);
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['hide_wp_login' => true, 'custom_slug' => 'about']);
    $data = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($data['details']['fields']['custom_slug'])->toBe(['reason' => 'address_needed_to_hide', 'detail' => 'taken_by_page'])
        ->and($data['errors']['custom_slug'])->toContain('wp-login.php can only be hidden behind a login address of your own.')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015: takes the route\'s own answer posted back with one field changed, and changes only that field', function () {
    $before = get_option(LoginProtectionSettingsStore::OPTION);
    $answer = loginSettingsRoute('GET')->get_data()['login_protection'];
    expect($answer['window_minutes'])->toBeNull()->and($answer['window_seconds'])->toBe(30);

    $response = loginSettingsRoute('POST', ['max_attempts' => 9] + $answer);
    $after = get_option(LoginProtectionSettingsStore::OPTION);
    $expected = ['threshold' => 9] + $before;
    ksort($after);
    ksort($expected);

    expect($response->get_status())->toBe(200)
        ->and($after)->toBe($expected)
        ->and($after['window_seconds'])->toBe(30);
});

it('FR-015: takes a whole-minute window posted back beside its seconds', function () {
    LoadedOptions::store(LoginProtectionSettingsStore::OPTION, ['window_seconds' => 600] + LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE);
    $answer = loginSettingsRoute('GET')->get_data()['login_protection'];

    $response = loginSettingsRoute('POST', ['max_attempts' => 4] + $answer);

    expect($answer['window_minutes'])->toBe(10)
        ->and($response->get_status())->toBe(200)
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['window_seconds'])->toBe(600)
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['threshold'])->toBe(4);
});

it('FR-014: the previous screen saving Max attempts leaves wp-login.php hidden and limits off as they are', function () {
    LoadedOptions::store(LoginProtectionSettingsStore::OPTION, [
        'hide_wp_login'           => true,
        'hide_admin'              => false,
        'limit_attempts'          => false,
        'enabled'                 => true,
        'block_default_endpoints' => false,
    ] + LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE);

    // What the previous screen sends: every field, the two old keys as it read them.
    $response = loginSettingsRoute('POST', [
        'enabled'                  => true,
        'block_default_endpoints'  => false,
        'custom_slug'              => 'team-door',
        'max_attempts'             => 8,
        'window_seconds'           => 60,
        'lockout_seconds'          => 900,
        'trusted_proxies'          => [],
        'retention_days'           => 30,
        'successful_login_logging' => true,
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['login_protection'])->toMatchArray([
            'hide_wp_login'  => true,
            'hide_admin'     => false,
            'limit_attempts' => false,
            'max_attempts'   => 8,
        ]);
});

it('FR-015: keeps a stored list the rules now refuse when the screen posts it back unchanged', function () {
    LoadedOptions::store(LoginProtectionSettingsStore::OPTION, [
        'trusted_proxy_mode'   => true,
        'trusted_proxy_ranges' => ['0.0.0.0/0'],
    ] + LOGIN_SETTINGS_SET_FROM_THE_COMMAND_LINE);

    $response = loginSettingsRoute('POST', ['trusted_proxies' => ['0.0.0.0/0'], 'max_attempts' => 6]);

    expect($response->get_status())->toBe(200)
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['trusted_proxy_ranges'])->toBe(['0.0.0.0/0'])
        ->and(get_option(LoginProtectionSettingsStore::OPTION)['threshold'])->toBe(6);
});

it('FR-015: stores a trusted proxy in one spelling', function () {
    loginSettingsRoute('POST', ['trusted_proxies' => "2001:DB8::1\n2001:0db8:0000:0000:0000:0000:0000:0002"]);

    expect(get_option(LoginProtectionSettingsStore::OPTION)['trusted_proxy_ranges'])->toBe(['2001:db8::1', '2001:db8::2']);
});

it('FR-015: answers a request with nothing to save with its own refusal, and writes nothing', function () {
    $before = loginSettingsRow();

    $response = loginSettingsRoute('POST', ['login_url' => 'https://example.test/']);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['details']['fields'])->toBe(['request' => ['reason' => 'nothing_to_save']])
        ->and($response->get_data()['errors']['request'])->toBeString()->not->toBe('')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-015: holds a named lock while it writes, and lets it go', function () {
    global $wpdb;

    $lock = Boot::app()->container()->make(LoginSettingsLock::class)->name();
    $heldAtWrite = null;
    $watch = static function (string $sql) use (&$heldAtWrite, $lock, $wpdb): string {
        if ($heldAtWrite === null && str_starts_with(ltrim($sql), 'UPDATE `' . $wpdb->options . '`')) {
            $heldAtWrite = false;
            $heldAtWrite = $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== null;
        }

        return $sql;
    };
    add_filter('query', $watch);

    try {
        $response = loginSettingsRoute('POST', ['max_attempts' => 7]);
    } finally {
        remove_filter('query', $watch);
    }

    expect($response->get_status())->toBe(200)
        ->and($heldAtWrite)->toBeTrue()
        ->and($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)))->toBeNull();
});

it('FR-015: refuses with "try again" and writes nothing while another save holds the lock', function () {
    $before = loginSettingsRow();
    $other = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $lock = Boot::app()->container()->make(LoginSettingsLock::class)->name();
    $other->query("SELECT GET_LOCK('" . $other->real_escape_string($lock) . "', 0)");

    try {
        $response = loginSettingsRoute('POST', ['max_attempts' => 7]);
    } finally {
        $other->query("SELECT RELEASE_LOCK('" . $other->real_escape_string($lock) . "')");
        $other->close();
    }

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['code'])->toBe('login_settings_busy')
        ->and($response->get_data()['message'])->toContain('try again')
        ->and(loginSettingsRow())->toBe($before);
});

it('FR-014: still takes a save from the previous screen, which sends every field with its two old keys', function () {
    $response = loginSettingsRoute('POST', [
        'enabled'                  => true,
        'block_default_endpoints'  => true,
        'custom_slug'              => 'team-door',
        'max_attempts'             => 5,
        'window_seconds'           => 300,
        'lockout_seconds'          => 900,
        'trusted_proxies'          => [],
        'retention_days'           => 30,
        'successful_login_logging' => true,
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['login_protection'])->toMatchArray([
            'hide_wp_login'  => true,
            'hide_admin'     => true,
            'limit_attempts' => true,
            'window_minutes' => 5,
        ]);
});
