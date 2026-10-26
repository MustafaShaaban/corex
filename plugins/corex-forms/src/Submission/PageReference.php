<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * The page a flow form was printed on, signed by the site (spec 122, FR-010; DECISIONS #471).
 *
 * "Page owner" routing assigns the page's author, so the page has to come from the site and not
 * from the browser: the form carries `{id}.{signature}`, and a value that was changed, or signed
 * for another flow, reads as "the page is not known" and the fallback applies.
 */
final class PageReference
{
    public const FIELD = 'corex_page';

    public function sign(int $pageId, int $flowId): string
    {
        return $pageId < 1 ? '' : $pageId . '.' . $this->signature($pageId, $flowId);
    }

    public function verify(mixed $value, int $flowId): ?int
    {
        if (! is_string($value) || preg_match('/^([1-9][0-9]*)\.([a-f0-9]{16})$/', $value, $parts) !== 1) {
            return null;
        }
        $pageId = (int) $parts[1];

        return hash_equals($this->signature($pageId, $flowId), $parts[2]) ? $pageId : null;
    }

    private function signature(int $pageId, int $flowId): string
    {
        return substr(wp_hash(sprintf('corex-flow-page|%d|%d', $flowId, $pageId)), 0, 16);
    }
}
