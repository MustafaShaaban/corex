<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Whether a login address is free, as opposed to merely well-formed (spec 077, FR-018).
 *
 * {@see LoginSlug} answers the question a regular expression can answer: is this the right shape,
 * and is it one of the paths WordPress reserves. It is deliberately pure — no `get_option`, no
 * query — because it runs on `plugins_loaded` before translations exist and because that purity is
 * why it has held since DECISIONS #140.
 *
 * This is the other half, and it needs the database. A slug can pass every rule in `LoginSlug` and
 * still be taken: `about` is a perfectly good slug and a perfectly common page. Nothing in the
 * pattern can see that, and the collision only shows up later as a login that serves somebody's
 * About page — or a page that has quietly become the login.
 *
 * Kept in a separate class rather than added to `LoginSlug` on purpose. Merging them would give
 * `LoginSlug` a database dependency and make it unusable at the moment it is most needed.
 */
final class LoginSlugAvailability
{
    /** Something else already answers at that path. */
    public const REASON_TAKEN_BY_PAGE = 'taken_by_page';

    /** A rewrite rule claims that path — a CPT archive, a taxonomy, a plugin's endpoint. */
    public const REASON_TAKEN_BY_ROUTE = 'taken_by_route';

    /**
     * Why this slug cannot be used, or null when it is free.
     *
     * Only collisions. Shape and reserved-name rejections stay with `LoginSlug`, so a caller asks
     * both and each answers what it knows.
     */
    public function rejectionReason(string $slug): ?string
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return null; // LoginSlug owns "empty".
        }

        if ($this->pageExistsAt($slug)) {
            return self::REASON_TAKEN_BY_PAGE;
        }

        if ($this->rewriteClaims($slug)) {
            return self::REASON_TAKEN_BY_ROUTE;
        }

        return null;
    }

    public function isAvailable(string $slug): bool
    {
        return $this->rejectionReason($slug) === null;
    }

    /**
     * A published page, post or any public post type sitting at that path.
     *
     * `get_page_by_path()` with every public post type rather than pages alone: a CPT with
     * `publicly_queryable` and a matching slug claims the path just as effectively, and an owner
     * who called a Product "login" would otherwise be told the address is free and then find it
     * is not.
     */
    private function pageExistsAt(string $slug): bool
    {
        if (! function_exists('get_page_by_path')) {
            return false;
        }

        $types = get_post_types(['public' => true], 'names');
        $found = get_page_by_path($slug, OBJECT, array_values($types));

        if (! $found instanceof \WP_Post) {
            return false;
        }

        // A draft or a trashed post does not answer at that path, so it does not take it. Only a
        // published one is a real collision.
        return in_array(get_post_status($found), ['publish', 'private'], true);
    }

    /**
     * A rewrite rule that claims this path by name.
     *
     * **Not** by running the slug against every rewrite pattern. That was the first implementation
     * and it rejected every slug ever proposed, because WordPress's page rule is the catch-all
     * `(.?.+?)/?$` — it matches any path at all, by design, since that is how pages work. Anything
     * built on "does a rule match" therefore answers yes for `corex-login`, `my-secret-door` and
     * every other string, and no custom login address could ever be saved.
     *
     * What actually claims a path is a rule whose pattern *starts with a literal segment*:
     * `category/...`, `author/...`, a post type archive's slug, a plugin's endpoint. Those are the
     * collisions worth refusing, and the catch-all is already covered by the page check above —
     * which asks the more precise question of whether something is really published there.
     */
    private function rewriteClaims(string $slug): bool
    {
        global $wp_rewrite;

        if (! isset($wp_rewrite) || ! is_object($wp_rewrite) || ! method_exists($wp_rewrite, 'wp_rewrite_rules')) {
            return false;
        }

        $rules = $wp_rewrite->wp_rewrite_rules();

        if (! is_array($rules)) {
            return false;
        }

        foreach (array_keys($rules) as $pattern) {
            $claimed = $this->literalPrefix((string) $pattern);

            if ($claimed !== '' && $claimed === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fixed first path segment of a rewrite pattern, when it has one.
     *
     * `category/(.+?)/?$` yields `category`; `(.?.+?)/?$` yields nothing, because it claims no
     * particular path.
     */
    private function literalPrefix(string $pattern): string
    {
        $firstSegment = explode('/', ltrim($pattern, '^'))[0] ?? '';

        // Any regex metacharacter means this segment is a placeholder, not a claim.
        if ($firstSegment === '' || preg_match('/[\[\](){}.*+?^$|\\\\]/', $firstSegment) === 1) {
            return '';
        }

        return $firstSegment;
    }
}
