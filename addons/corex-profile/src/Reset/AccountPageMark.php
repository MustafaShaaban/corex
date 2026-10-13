<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

/**
 * The mark the account block leaves in its forgot-password form: the page it is on, signed
 * by this site.
 *
 * A reset email links to the page the reset was asked from, and the request names that page.
 * A page number alone would let anybody have the email link to any page of the site. Whether
 * a page shows the block cannot be read from the page either: the theme's Account template
 * places the block, and the page's own content is empty. So the block signs the page as it
 * renders on it, and only a mark this site made is taken (DECISIONS #340).
 *
 * The mark is the same on every render, so a page that is served from a cache carries one
 * that still reads.
 */
final class AccountPageMark
{
    public function of(int $pageId): string
    {
        return $pageId . '.' . $this->signature($pageId);
    }

    /**
     * The page a mark was made for, when this site made it and the page is still published.
     * Zero for anything else.
     */
    public function publishedPage(string $mark): int
    {
        [$id, $signature] = explode('.', $mark, 2) + ['', ''];

        if (! ctype_digit($id) || ! hash_equals($this->signature((int) $id), $signature)) {
            return 0;
        }

        return get_post_status((int) $id) === 'publish' ? (int) $id : 0;
    }

    private function signature(int $pageId): string
    {
        return hash_hmac('sha256', 'corex-account-page:' . $pageId, wp_salt('auth'));
    }
}
