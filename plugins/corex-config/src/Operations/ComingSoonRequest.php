<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * The facts about one request that decide what it gets while the site is in Coming soon (spec 101).
 *
 * Facts only, already established by whoever built this: {@see ComingSoonGuard} asks WordPress and
 * {@see ComingSoonDecision} reads nothing but this object. Every fact defaults to "no", so the
 * request with nothing said about it is an anonymous visitor asking for an address that is not the
 * home URL — the one the mode exists to turn away.
 *
 * Two things are absent on purpose. "Signed in" is not a fact: what admits somebody is being able
 * to edit posts, so a subscriber and a stranger are the same request (FR-007). And an invalid
 * preview value is not a fact either: it is the same as no value, so there is nothing here for the
 * decision to answer differently with (FR-014).
 */
final readonly class ComingSoonRequest
{
    /**
     * @param string $mode                The operations mode in force.
     * @param bool   $neverIntercepted    The admin, the login route, the REST API, AJAX or cron.
     * @param bool   $carriesValidPreview The address carries a preview-link value, and it is the
     *                                    current one.
     * @param bool   $allowedByClient     Client code allowed this request through (FR-018).
     * @param bool   $isRobotsOrFavicon   `robots.txt`, or the favicon address.
     * @param bool   $canEditPosts        Signed in, and able to edit posts.
     * @param bool   $asksForVisitorView  Asked to see the page as a visitor sees it.
     * @param bool   $holdsPreviewAccess  This browser holds access granted by the preview link.
     * @param bool   $isSitemap           The sitemap index address, on a site that publishes one.
     * @param bool   $isHome              The home URL.
     */
    public function __construct(
        public string $mode,
        public bool $neverIntercepted = false,
        public bool $carriesValidPreview = false,
        public bool $allowedByClient = false,
        public bool $isRobotsOrFavicon = false,
        public bool $canEditPosts = false,
        public bool $asksForVisitorView = false,
        public bool $holdsPreviewAccess = false,
        public bool $isSitemap = false,
        public bool $isHome = false,
    ) {
    }
}
