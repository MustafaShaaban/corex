<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * The public behaviour of Coming soon mode (spec 101): the launch page at the home URL with a 200,
 * a temporary redirect to it from every other front-end address, and the real site for the people
 * building it.
 *
 * This class does two things and decides neither. It gathers the facts about the request — it is
 * the only class in the feature that asks WordPress who is signed in or what was asked for — and
 * it carries out what {@see ComingSoonDecision} answers. The rules themselves are that class's
 * table, which is why they can be read, and tested, without a request.
 *
 * It is not {@see MaintenanceGuard} with a second mode. Maintenance answers 503 with a fixed card
 * and admits administrators only; this answers 200 with the client's own page, redirects, and
 * admits anybody who can edit posts. Exactly one mode is in force, so the two never both act.
 */
final class ComingSoonGuard
{
    /** Client code returns true from this filter to let the current request through (FR-018). */
    public const BYPASS_FILTER = 'corex_coming_soon_bypass';

    /**
     * Client code may name further addresses as a home URL — each language's home, on a
     * multilingual site. Receives the answer so far and the request path.
     */
    public const HOME_FILTER = 'corex_coming_soon_is_home';

    /**
     * The query argument by which somebody who is served the real site asks to see the page as a
     * visitor does (FR-016). It asks for less than the user already has, so it carries no nonce.
     */
    public const VISITOR_VIEW = 'corex_visitor_view';

    public function __construct(
        private readonly OperationsModeStore $store,
        private readonly ComingSoonTemplate $template,
        private readonly ComingSoonSitemap $sitemap,
    ) {
    }

    public function register(): void
    {
        // Priority 0: before the canonical redirect, before the theme, before another plugin's
        // redirect can send a visitor somewhere this mode would not.
        add_action('template_redirect', [$this, 'handle'], 0);
    }

    public function handle(): void
    {
        $decision = $this->decision();

        if ($decision->noCache) {
            nocache_headers();
        }

        match ($decision->outcome) {
            ComingSoonDecision::REDIRECT => $this->redirectHome(),
            ComingSoonDecision::SITEMAP  => $this->sendSitemap(),
            ComingSoonDecision::SERVE    => $this->arrangeToServe(),
            default                      => null,
        };
    }

    private function arrangeToServe(): void
    {
        // Swapped at the last moment WordPress offers, and last among the filters there, so the
        // template chosen for the request's own query cannot be put back over it.
        add_filter('template_include', [$this, 'serve'], PHP_INT_MAX);

        // The page is what a visitor sees, whoever is looking (US3.3) — and a visitor has no
        // WordPress toolbar. Turning it off is two steps because WordPress sets it up on this same
        // hook at this same priority, and was registered first: by now its stylesheet is queued,
        // and that stylesheet is what pushes the page down by the toolbar's height.
        add_filter('show_admin_bar', '__return_false');
        wp_dequeue_style('admin-bar');
        wp_dequeue_script('admin-bar');
        add_filter('body_class', [$this, 'visitorBodyClasses']);
    }

    /**
     * The `body_class` callback for the served page: a visitor's page is not marked as a signed-in
     * one, and a theme may style by that mark.
     *
     * @param mixed $classes
     *
     * @return list<string>
     */
    public function visitorBodyClasses(mixed $classes): array
    {
        return array_values(array_diff((array) $classes, ['logged-in']));
    }

    /**
     * What this request gets. Sends nothing and ends nothing, so it is what the tests ask.
     */
    public function decision(): ComingSoonDecision
    {
        return ComingSoonDecision::for($this->request());
    }

    /**
     * The `template_include` callback for the serve outcome: the coming-soon template in place of
     * whatever WordPress chose for the request, or the self-contained page when the active theme
     * has no block templates to render one with (FR-009).
     */
    public function serve(mixed $chosen): string
    {
        // `$chosen` is deliberately unread and untyped: it is whatever the filters before this one
        // returned, which another plugin can make anything, and none of it is wanted.
        // The home URL of a site whose front page is missing or unpublished resolves to a 404.
        // The visitor asked for the launch page and is getting it, so the answer is a 200.
        status_header(200);

        $canvas = $this->template->canvas();
        if ($canvas !== '') {
            return $canvas;
        }

        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo $this->template->standalone(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
        exit;
    }

    /**
     * Whether an address is the home URL: the home URL's own path, with nothing in it that
     * WordPress acts on.
     *
     * The path is what the visitor asked for, which `is_front_page()` is not — that answers for
     * the query WordPress resolved, and a static or redirecting front page is one of the cases
     * this has to survive. The query variables are the other half: `/?feed=rss2`, `/?p=12` and
     * `/?s=launch` all have the home path, and WordPress renders a feed before any template is
     * chosen. A campaign tag is not a query variable WordPress knows, so it changes nothing.
     *
     * @param array<string,mixed> $queryVars The public query variables WordPress matched.
     */
    public function isHome(string $requestUri, string $homeUrl, array $queryVars): bool
    {
        if ($queryVars !== []) {
            return false;
        }

        return $this->path($requestUri) === $this->path($homeUrl);
    }

    private function request(): ComingSoonRequest
    {
        $mode = $this->store->current();

        // Nothing further is asked in any other mode: no filter fires and no fact is gathered
        // for a decision whose first row has already been met.
        if ($mode !== OperationsMode::COMING_SOON) {
            return new ComingSoonRequest($mode);
        }

        $queryVars = $this->queryVars();
        $uri       = $this->requestUri();

        return new ComingSoonRequest(
            mode: $mode,
            neverIntercepted: $this->isNeverIntercepted(),
            // The preview link arrives with T042. Until it does, no value can be valid and no
            // browser can hold access, and saying so here is truer than reading a cookie nothing
            // sets.
            carriesValidPreview: false,
            allowedByClient: apply_filters(self::BYPASS_FILTER, false) === true,
            isRobotsOrFavicon: is_robots() || is_favicon(),
            canEditPosts: is_user_logged_in() && current_user_can('edit_posts'),
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state; it asks for less than the user already has.
            asksForVisitorView: isset($_GET[self::VISITOR_VIEW]),
            holdsPreviewAccess: false,
            isSitemap: $this->isSitemapIndex($queryVars),
            isHome: apply_filters(
                self::HOME_FILTER,
                $this->isHome($uri, home_url('/'), $queryVars),
                $this->path($uri),
            ) === true,
        );
    }

    /**
     * The contexts FR-006 names. None of them reaches `template_redirect` in a stock WordPress —
     * the admin, `wp-login.php`, AJAX and cron never load a template, and the REST API answers
     * before one is chosen — so this is the rule stated where it can be read, and a guard against
     * a plugin that fires the hook from one of them.
     */
    private function isNeverIntercepted(): bool
    {
        return is_admin()
            || is_login()
            || wp_doing_ajax()
            || wp_doing_cron()
            || (defined('REST_REQUEST') && REST_REQUEST);
    }

    /**
     * The sitemap index, on a site that publishes sitemaps. WordPress publishes none when it is
     * set to discourage search engines, and this mode does not overrule that.
     *
     * @param array<string,mixed> $queryVars
     */
    private function isSitemapIndex(array $queryVars): bool
    {
        if (($queryVars['sitemap'] ?? '') !== 'index') {
            return false;
        }

        return wp_sitemaps_get_server()->sitemaps_enabled();
    }

    private function redirectHome(): never
    {
        // `/admin`, `/login` and the like are WordPress's own shortcuts to routes this mode never
        // intercepts. Its redirect for them sits on this same hook, long after this priority, so
        // it is given its turn here: it ends the request for one of those addresses and returns
        // for every other. Login protection unhooks it when it hides the login route, and then
        // those addresses are ordinary ones.
        if (has_action('template_redirect', 'wp_redirect_admin_locations') !== false) {
            wp_redirect_admin_locations();
        }

        // Temporary, never permanent (FR-005): a browser that remembered a 301 would keep sending
        // visitors to the home URL after launch.
        wp_safe_redirect(home_url('/'), 302, 'CoreX');
        exit;
    }

    private function sendSitemap(): never
    {
        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        echo $this->sitemap->xml(home_url('/')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ComingSoonSitemap escapes the one value it holds.
        exit;
    }

    /**
     * The public query variables WordPress matched in the request.
     *
     * @return array<string,mixed>
     */
    private function queryVars(): array
    {
        $wp = $GLOBALS['wp'] ?? null;

        return $wp instanceof \WP ? (array) $wp->query_vars : [];
    }

    private function requestUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    }

    /** A URL's or a request URI's path, without the slashes at either end. */
    private function path(string $url): string
    {
        return trim((string) parse_url($url, PHP_URL_PATH), '/');
    }
}
