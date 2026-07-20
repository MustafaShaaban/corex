<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Hides the default login and admin endpoints behind a custom slug, without moving core files.
 *
 * The hiding is only obscurity — it cuts automated probing, it is not an access control. The
 * throttling in LoginProtectionEnforcer is what actually defends credentials.
 *
 * Obscurity that announces itself is worth nothing, and the previous implementation announced
 * itself twice (both reproduced live, DECISIONS #140): core's own `wp_redirect_admin_locations`
 * still redirected /login straight to the secret slug, and the "hidden" endpoint answered with a
 * 2.5 KB wp_die page where a real miss returns the ~80 KB theme 404 — telling a scanner "something
 * is hidden here" on response size alone. So the rules here are: let WordPress produce the 404
 * itself, and make sure nothing anywhere still points at the default endpoint.
 */
final class LoginRouteGuard
{
    /** What to do with the current request. Decided once, in {@see captureRequest()}. */
    private const SERVE_LOGIN = 'serve_login';

    private const HIDE = 'hide';

    private const PASS = 'pass';

    /**
     * A path nothing can ever match, swapped in for a hidden request.
     *
     * The 404 must be WordPress's own, not one we render: that is the only way it is genuinely
     * identical to a missing page. So instead of intercepting, point the request at somewhere that
     * definitely does not exist and let core's ordinary routing 404 it.
     */
    private const NOWHERE = '/-/-/-/-/-/-/-/-/-/-/';

    private string $action = self::PASS;

    private string $requestPath = '';

    /**
     * @param bool $unguarded The documented break-glass (COREX_LOGIN_UNGUARD in wp-config.php),
     *                        resolved by the container. Injected rather than read from the
     *                        constant here so recovery is testable: the previous test passed
     *                        "unguarded" as a call argument and so never exercised the real
     *                        mechanism at all. The constant is fixed before WordPress loads, so
     *                        resolving it once at construction loses nothing.
     */
    public function __construct(
        private readonly LoginProtectionSettings $settings,
        private readonly bool $unguarded = false,
    ) {
    }

    public function register(): void
    {
        if (! $this->settings->enabled) {
            return;
        }

        // Corex boots on plugins_loaded:10, so a plugins_loaded:1 hook would never fire — capture
        // now instead. This is still ahead of everything that matters: $pagenow is set in
        // wp-includes/vars.php before plugins_loaded, pluggable.php and the auth_redirect() that
        // would leak the login both come later, and the main query does not run until after
        // wp_loaded.
        $this->captureRequest();

        add_action('wp_loaded', [$this, 'serveRequest'], 1);

        // Everything core builds from these must point at the slug. This is what makes hiding the
        // default endpoint from *everyone* safe: nothing legitimate references it any more.
        add_filter('site_url', [$this, 'filterSiteUrl'], 20, 2);
        add_filter('network_site_url', [$this, 'filterSiteUrl'], 20, 2);
        add_filter('login_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('logout_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('lostpassword_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('register_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('wp_redirect', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('site_option_welcome_email', [$this, 'filterWelcomeEmail'], 20, 1);

        if (! $this->hidesDefaults()) {
            return;
        }

        // Core redirects /login, /dashboard, and /admin to wp-login.php. With the filters above
        // in place it redirected them to the *custom slug* instead — handing the secret to anyone
        // who guessed /login, which is the first thing anyone guesses (DECISIONS #140).
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
    }

    /**
     * Decide once what this request is, and get out of WordPress's way.
     *
     * Runs before the main query, so a hidden request is re-pointed at {@see NOWHERE} and core
     * 404s it on its own terms.
     */
    public function captureRequest(): void
    {
        global $pagenow;

        $this->requestPath = $this->requestPath();
        $this->action = $this->entryPointFor($this->requestPath, is_admin());

        if ($this->action === self::SERVE_LOGIN) {
            $pagenow = 'wp-login.php';

            return;
        }

        if ($this->action === self::HIDE) {
            $_SERVER['REQUEST_URI'] = self::NOWHERE;
            $pagenow = 'index.php';
        }
    }

    /**
     * What this URL is, before the login state is knowable.
     *
     * Pure, so the rules are testable without a request. This answers "is this URL the slug, the
     * default endpoint, or unrelated?" — a question that needs no login state. Whether the *admin
     * area* is hidden is a different question with a different answer, asked later by
     * {@see hidesAdminArea()} once pluggable.php has loaded. Two questions, two methods; the bug
     * this replaces was two methods answering the *same* question differently.
     *
     * @return self::SERVE_LOGIN|self::HIDE|self::PASS
     */
    public function entryPointFor(string $path, bool $isAdmin): string
    {
        if (! $this->settings->enabled) {
            return self::PASS;
        }

        if ($this->isCustomLoginPath($path)) {
            return self::SERVE_LOGIN;
        }

        // is_admin() covers admin-ajax.php and admin-post.php, which post to wp-login.php-adjacent
        // paths and must never be hidden or scheduled work and async features break.
        if (! $isAdmin && $this->isDefaultLoginPath($path) && $this->hidesDefaults()) {
            return self::HIDE;
        }

        return self::PASS;
    }

    /**
     * Whether the admin area must be hidden from this visitor.
     *
     * Asked on wp_loaded because it needs a login state, which does not exist at capture time —
     * pluggable.php loads after plugins_loaded.
     */
    public function hidesAdminArea(bool $isAdmin, bool $loggedIn, bool $ajax, string $script, string $path): bool
    {
        if (! $this->hidesDefaults() || ! $isAdmin || $loggedIn || $ajax) {
            return false;
        }

        // admin-post.php accepts unauthenticated posts by design (nopriv actions), and options.php
        // is how core's own settings forms submit.
        return ! in_array($script, ['admin-ajax.php', 'admin-post.php'], true)
            && rtrim($path, '/') !== '/wp-admin/options.php';
    }

    /** Act on the decision {@see captureRequest()} already made. */
    public function serveRequest(): void
    {
        if ($this->action === self::HIDE) {
            $this->render404();
        }

        if ($this->hidesAdminArea(
            is_admin(),
            is_user_logged_in(),
            function_exists('wp_doing_ajax') && wp_doing_ajax(),
            $this->script($this->requestPath),
            $this->requestPath,
        )) {
            $this->render404();
        }

        if ($this->action === self::SERVE_LOGIN) {
            $this->serveLogin();
        }
    }

    /**
     * Hand off to the real login handler.
     *
     * wp-login.php shares state through variables it treats as global ($user_login, $error,
     * $action, $interim_login); because it is included from inside a method they must be declared
     * global here or PHP reports them as undefined.
     */
    private function serveLogin(): void
    {
        global $pagenow, $error, $interim_login, $action, $user_login;

        $pagenow = 'wp-login.php';

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    /**
     * Let WordPress 404 this request as if the URL never existed.
     *
     * Deliberately not wp_die(): that renders a bare page a real 404 never produces, which is what
     * made the previous implementation trivially fingerprintable. Running the real query and the
     * real template loader means the response *is* the theme's 404 — byte-identical to any other
     * missing page, which is the whole point.
     */
    private function render404(): void
    {
        global $pagenow;

        $pagenow = 'index.php';

        // Present this as an ordinary front-end request for a path that does not exist, then let
        // core route it. Every part of that is load-bearing:
        //   - REQUEST_URI must point at NOWHERE or the query would resolve to something real.
        //   - PHP_SELF/SCRIPT_NAME must not say wp-admin. WP::parse_request() skips rule matching
        //     entirely when PHP_SELF contains 'wp-admin/' (wp-includes/class-wp.php) and serves the
        //     home page instead — so hiding /wp-admin without this returns a 200 front page, which
        //     is not a 404 by any measure.
        //   - PATH_INFO would otherwise be preferred over REQUEST_URI as the requested permalink.
        $_SERVER['REQUEST_URI'] = self::NOWHERE;
        $_SERVER['PHP_SELF'] = '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        unset($_SERVER['PATH_INFO']);

        if (is_admin()) {
            $this->dropAdminContext();
        }

        if (! defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        wp();

        nocache_headers();

        require_once ABSPATH . WPINC . '/template-loader.php';

        exit;
    }

    /**
     * Strip the admin-ness that would otherwise leak into a hidden admin request's 404.
     *
     * WP_ADMIN is a constant and cannot be unset, so is_admin() stays true while we render the
     * theme's 404 and core keeps taking admin branches it should not. Each one has to be undone
     * by hand. Only applied when the hidden request really is an admin one — doing it to a
     * front-end request would strip things a genuine front-end 404 has, differing in the other
     * direction.
     *
     * KNOWN LIMITATION: this narrows the difference, it does not erase it. A hidden /wp-admin
     * still carries fewer inline styles than a front-end 404, because whether the front-end asset
     * pipeline registers at all is decided at `init` while is_admin() is true — long before this
     * runs on wp_loaded. Correcting it earlier is not possible: the only hook before `init` is
     * plugins_loaded, where the login state is unknowable (pluggable.php has not loaded), and
     * making is_admin() false for every admin request would break the admin for the people
     * entitled to use it. Hiding /wp-login.php — the endpoint that actually identifies a hidden
     * login — IS byte-identical; see the spec for the measured sizes.
     *
     * Public only so it can be tested. render404() exits, so nothing downstream of it is
     * observable from a test; this hook surgery is the part that has to be right, and the defect
     * it fixes shipped precisely because nothing could see it.
     *
     * @internal
     */
    public function dropAdminContext(): void
    {
        // is_admin_bar_showing() returns true on is_admin() alone (wp-includes/admin-bar.php),
        // short-circuiting both the logged-out check and the show_admin_bar filter — so filtering
        // does nothing and the init itself has to go. With no $wp_admin_bar the render no-ops.
        // Without this, a logged-out visitor's "missing page" arrives carrying admin bar markup.
        remove_action('template_redirect', '_wp_admin_bar_init', 0);

        $this->relocateEmojiStyles();
    }

    /**
     * Move core's deprecated emoji-style shim to where core will look for it.
     *
     * Core registers print_emoji_styles() on `wp_print_styles` only (default-filters.php) and
     * unhooks it from wp_enqueue_emoji_styles(), which picks its target from is_admin():
     *
     *     $action = is_admin() ? 'admin_print_styles' : 'wp_print_styles';
     *     if ( ! has_action( $action, 'print_emoji_styles' ) ) { return; }
     *
     * On a hidden /wp-admin, is_admin() is still true, so core looked at `admin_print_styles`,
     * found nothing, and returned without unhooking. wp_head() then fired `wp_print_styles`, the
     * deprecated function ran, and with WP_DEBUG_DISPLAY on the notice was printed into the body
     * of the "missing page" — the loudest possible way to announce that something is hidden here.
     *
     * Removing the hook outright would silence it but leave the response missing the emoji styles
     * a real front-end 404 carries, widening the size gap that SC-001 is about. Moving it to the
     * hook core is about to inspect makes core's own unhook succeed, so we get exactly what a
     * front-end request gets: the shim removed and the modern inline styles enqueued in its place.
     * `admin_print_styles` never fires during a front-end template render, so the moved hook is
     * inert either way.
     */
    private function relocateEmojiStyles(): void
    {
        if (has_action('wp_print_styles', 'print_emoji_styles') === false) {
            return;
        }

        remove_action('wp_print_styles', 'print_emoji_styles');
        add_action('admin_print_styles', 'print_emoji_styles');
    }

    /** Point a URL that references the default login at the custom slug instead. */
    public function filterLoginUrl(mixed $url): mixed
    {
        if (! is_string($url) || ! str_contains($url, 'wp-login.php')) {
            return $url;
        }

        return $this->rewrite($url);
    }

    /**
     * Point the login address inside the multisite welcome email at the custom slug.
     *
     * Separate from filterLoginUrl because this filter carries an entire message body, not a URL —
     * parsing it as one yields nonsense. A plain replacement of the script name is what fits here.
     */
    public function filterWelcomeEmail(mixed $email): mixed
    {
        if (! is_string($email)) {
            return $email;
        }

        return str_replace('wp-login.php', trailingslashit($this->settings->customSlug), $email);
    }

    /**
     * @param mixed $url
     * @param mixed $path
     */
    public function filterSiteUrl(mixed $url, mixed $path = ''): mixed
    {
        if (! is_string($url) || ! is_string($path) || ! str_contains($path, 'wp-login.php')) {
            return $url;
        }

        return $this->rewrite($url);
    }

    /**
     * Swap wp-login.php for the custom slug, keeping the query string intact.
     *
     * The previous implementation was a bare str_replace, which produced
     * "…/slug/?action=logout&_wpnonce=…" only by accident and mangled anything with a path after
     * the script name. Parsing keeps the arguments (action, redirect_to, _wpnonce) that logout,
     * lost-password, and interim login depend on.
     */
    private function rewrite(string $url): string
    {
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
        $target = $this->loginUrl($this->schemeFor($url));

        if ($query === '') {
            return $target;
        }

        $args = [];
        parse_str($query, $args);

        return $args === [] ? $target : add_query_arg($args, $target);
    }

    /** The custom login URL, honouring the site's permalink and trailing-slash settings. */
    public function loginUrl(?string $scheme = null): string
    {
        return LoginUrl::forSettings($this->settings, $scheme);
    }

    /** Keep a rewritten URL on the scheme it arrived on rather than forcing one. */
    private function schemeFor(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? $scheme : null;
    }

    private function isCustomLoginPath(string $path): bool
    {
        if (! get_option('permalink_structure')) {
            return isset($_GET[$this->settings->customSlug]);
        }

        return trim($path, '/') === $this->settings->customSlug;
    }

    private function isDefaultLoginPath(string $path): bool
    {
        $trimmed = rtrim($path, '/');

        return str_contains($path, 'wp-login.php') || $trimmed === '/wp-login';
    }

    private function requestPath(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';

        return (string) (parse_url(rawurldecode($uri), PHP_URL_PATH) ?: '/');
    }

    private function script(string $path): string
    {
        return strtolower(basename($path));
    }

    private function hidesDefaults(): bool
    {
        return $this->settings->enabled && $this->settings->blockDefaultEndpoints && ! $this->unguarded;
    }

    public function customLoginPath(): string
    {
        return '/' . $this->settings->customSlug . '/';
    }

    public function movesCoreFiles(): bool
    {
        return false;
    }
}
