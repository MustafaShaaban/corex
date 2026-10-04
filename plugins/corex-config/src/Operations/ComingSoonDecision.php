<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * What one request gets while the site is in Coming soon (spec 101): the plan's table, as code.
 *
 * The rows are tried in the order written and the first that matches decides. That order is the
 * behaviour — an editor asking for the visitor view must be answered before the pass an editor
 * otherwise gets, the sitemap before the home rule — so it is one method, read top to bottom, with
 * a test for every row and for every pair whose order matters.
 *
 * It reads nothing. The facts come in a {@see ComingSoonRequest}; {@see ComingSoonGuard} is the
 * only class that asks WordPress who is signed in or what was requested, and the only one that
 * acts on the answer.
 */
final readonly class ComingSoonDecision
{
    /** The request goes on as if the mode were off. */
    public const PASS = 'pass';

    /** The coming-soon page, with a 200. */
    public const SERVE = 'serve';

    /** The sitemap that lists the home URL and nothing else, with a 200. */
    public const SITEMAP = 'sitemap';

    /** A temporary redirect to the home URL. */
    public const REDIRECT = 'redirect';

    /** A valid preview link: remember this browser, then send it to the same address without the value. */
    public const CLAIM = 'claim';

    /**
     * @param string $outcome One of the constants above.
     * @param bool   $noCache Whether the response must be marked non-cacheable, because what this
     *                        address answers depends on who asked (FR-015). False leaves the
     *                        response's caching as WordPress would have it.
     */
    private function __construct(
        public string $outcome,
        public bool $noCache,
    ) {
    }

    public static function for(ComingSoonRequest $request): self
    {
        return match (true) {
            // 1. The mode is not Coming soon.
            $request->mode !== OperationsMode::COMING_SOON => new self(self::PASS, false),
            // 2. Never intercepted: nobody can be locked out of the admin or the login route.
            $request->neverIntercepted => new self(self::PASS, false),
            // 3. A preview link is being opened.
            $request->carriesValidPreview => new self(self::CLAIM, true),
            // 4. The client's own word on this request.
            $request->allowedByClient => new self(self::PASS, false),
            // 5. Files asked for by a fixed address, which a redirect would hide.
            $request->isRobotsOrFavicon => new self(self::PASS, false),
            // 6. Somebody who passes, asking to see what a visitor sees.
            $request->canEditPosts && $request->asksForVisitorView => new self(self::SERVE, true),
            // 7. Somebody building the site.
            $request->canEditPosts => new self(self::PASS, true),
            // 8. Somebody reviewing it through the preview link.
            $request->holdsPreviewAccess => new self(self::PASS, true),
            // 9. A crawler asking what there is to index.
            $request->isSitemap => new self(self::SITEMAP, false),
            // 10. The launch page.
            $request->isHome => new self(self::SERVE, false),
            // 11. Everything else, feeds included.
            default => new self(self::REDIRECT, true),
        };
    }
}
