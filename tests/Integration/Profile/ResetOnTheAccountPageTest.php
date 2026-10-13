<?php

/**
 * A password reset asked for at the account block, on real WordPress.
 *
 * The add-on asked WordPress for the email and WordPress wrote its own link into it:
 * `wp-login.php?login=…&key=…&action=rp`. The member chose the new password on WordPress's
 * screen and was sent on to `wp-admin`, and never came back to the site's account page, which
 * had no form that takes a reset key. Where login protection hides the login, the link was the
 * hidden address (measured 2026-10-13 on WordPress 7.1.3, DECISIONS #340).
 *
 * No email leaves: `pre_wp_mail` is answered, and what was handed over is read.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;
use Corex\Profile\Reset\AccountPageMark;
use Corex\Profile\Reset\ResetLanding;
use Corex\Profile\Reset\ResetLink;
use Corex\Profile\Reset\ResetState;
use Corex\Profile\Reset\ResetTicket;

const RESET_HIDDEN_SLUG = 'staff-door';

/**
 * Asks for a reset the way the block's form does, and answers with the email's text.
 */
function askForReset(string $login, ?string $pageMark): string
{
    $request = new WP_REST_Request('POST', '/corex/v1/account/reset-request');
    $request->set_body_params(array_filter(['login' => $login, 'page' => $pageMark], is_string(...)));

    expect(rest_do_request($request)->get_status())->toBe(200);

    return test()->mail[0]['message'] ?? '';
}

/**
 * The address in an email's text that carries the reset key.
 */
function resetAddressIn(string $message): string
{
    preg_match('~\S*key=\S*~', $message, $found);

    return $found[0] ?? '';
}

/**
 * The reset key of an address, whichever of the two links it is.
 */
function resetKeyIn(string $address): string
{
    parse_str((string) parse_url($address, PHP_URL_QUERY), $asked);

    return (string) ($asked[ResetLink::ARG_KEY] ?? $asked['key'] ?? '');
}

/**
 * Hides the login the way the Security Center does: the guard's own rewriting, on the filter
 * WordPress builds the email's link through.
 */
function hideTheLogin(): LoginRouteGuard
{
    $guard = new LoginRouteGuard(new LoginProtectionSettings(
        enabled: true,
        customSlug: RESET_HIDDEN_SLUG,
        blockDefaultEndpoints: true,
        threshold: 5,
        windowSeconds: 300,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: true,
    ));
    add_filter('network_site_url', [$guard, 'filterSiteUrl'], 20, 2);

    return $guard;
}

beforeEach(function () {
    $this->mail = [];
    $this->keepMail = function (mixed $answered, array $email): bool {
        $this->mail[] = $email;

        return true;
    };
    add_filter('pre_wp_mail', $this->keepMail, 10, 2);
    // WordPress writes the address a reset was asked from into its email.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    $tag          = strtolower(wp_generate_password(8, false, false));
    $this->login  = 'reset-' . $tag;
    $this->userId = (int) wp_insert_user([
        'user_login' => $this->login,
        'user_email' => $this->login . '@example.com',
        'user_pass'  => 'the-first-passphrase',
    ]);
    $this->pageId = (int) wp_insert_post([
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => 'Account ' . $tag,
        'post_content' => '<!-- wp:corex/account /-->',
    ]);

    $container     = Boot::app()->container();
    $this->marks   = $container->make(AccountPageMark::class);
    $this->ticket  = $container->make(ResetTicket::class);
    $this->landing = $container->make(ResetLanding::class);
    $this->guard   = null;
});

afterEach(function () {
    remove_filter('pre_wp_mail', $this->keepMail, 10);
    if ($this->guard !== null) {
        remove_filter('network_site_url', [$this->guard, 'filterSiteUrl'], 20);
    }
    unset($_COOKIE[ResetTicket::name()], $_GET[ResetLink::ARG_STEP], $_SERVER['REMOTE_ADDR']);

    wp_reset_query();
    wp_delete_post($this->pageId, true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->userId);
});

it('links the email to the page the reset was asked from', function () {
    $address = resetAddressIn(askForReset($this->login, $this->marks->of($this->pageId)));

    expect($this->mail)->toHaveCount(1)
        ->and($address)->toStartWith(get_permalink($this->pageId))
        ->and($address)->not->toContain('wp-login.php')
        ->and($address)->toContain(ResetLink::ARG_LOGIN . '=' . rawurlencode($this->login))
        ->and(check_password_reset_key(resetKeyIn($address), $this->login))->toBeInstanceOf(WP_User::class);
});

it('does not write the hidden login address into the email', function () {
    $this->guard = hideTheLogin();

    $message = askForReset($this->login, $this->marks->of($this->pageId));

    expect($message)->not->toContain(RESET_HIDDEN_SLUG)
        ->and(resetAddressIn($message))->toStartWith(get_permalink($this->pageId));
});

it('keeps WordPress\'s link when no page of this site with the block is named', function (Closure $mark) {
    $address = resetAddressIn(askForReset($this->login, $mark->call($this)));

    expect($address)->toContain('wp-login.php')
        ->and($address)->toContain('action=rp');
})->with([
    'no page'                       => [fn (): ?string => null],
    'a page number with no mark'    => [fn (): string => (string) $this->pageId],
    'a mark made for another page'  => [fn (): string => $this->pageId . strstr($this->marks->of($this->pageId + 1), '.')],
    'a page that is a draft by now' => [function (): string {
        wp_update_post(['ID' => $this->pageId, 'post_status' => 'draft']);

        return $this->marks->of($this->pageId);
    }],
]);

it('leaves the email of a reset asked at WordPress\'s own screen as WordPress writes it', function () {
    askForReset($this->login, $this->marks->of($this->pageId));

    retrieve_password($this->login);

    expect($this->mail)->toHaveCount(2)
        ->and(resetAddressIn($this->mail[1]['message']))->toContain('wp-login.php');
});

it('marks the forgot-password form with the page the block is on', function () {
    query_posts(['page_id' => $this->pageId]);

    $html = do_blocks('<!-- wp:corex/account /-->');

    expect(preg_match('~<input type="hidden" name="page" value="([^"]+)"~', $html, $field))->toBe(1)
        ->and($this->marks->publishedPage($field[1]))->toBe($this->pageId);
});

it('offers the new-password form to whoever arrives with the key from the email', function () {
    $key = resetKeyIn(resetAddressIn(askForReset($this->login, $this->marks->of($this->pageId))));
    $_COOKIE[ResetTicket::name()] = $key . ':' . $this->login;
    $_GET[ResetLink::ARG_STEP]    = '1';

    $this->landing->onRequest();
    $state = $this->landing->state();

    expect($state->step)->toBe(ResetState::CHOOSE)
        ->and($state->login)->toBe($this->login)
        ->and($state->key)->toBe($key);
});

it('says a key WordPress does not take has expired, and forgets it', function () {
    $_COOKIE[ResetTicket::name()] = 'notTheKeyThatWasSent:' . $this->login;
    $_GET[ResetLink::ARG_STEP]    = '1';

    $this->landing->onRequest();

    expect($this->landing->state()->step)->toBe(ResetState::EXPIRED)
        ->and($this->ticket->held())->toBeNull();
});

it('says the password was changed once, after the form was used', function () {
    $_COOKIE[ResetTicket::name()] = ResetTicket::USED;
    $_GET[ResetLink::ARG_STEP]    = '1';

    $this->landing->onRequest();
    $first = $this->landing->state()->step;
    $this->landing->onRequest();

    expect($first)->toBe(ResetState::DONE)
        ->and($this->landing->state()->step)->toBe(ResetState::NONE);
});

it('reads what a browser sends as its ticket as a key and a login, and nothing more', function () {
    $_COOKIE[ResetTicket::name()] = wp_slash('k3y<b>!:  sal:ma<script>  ');

    expect($this->ticket->held())->toBe(['login' => 'sal:ma', 'key' => 'k3yb']);
});

it('says nothing to a visitor who holds no key', function () {
    $_GET[ResetLink::ARG_STEP] = '1';

    $this->landing->onRequest();

    expect($this->landing->state()->step)->toBe(ResetState::NONE);
});
