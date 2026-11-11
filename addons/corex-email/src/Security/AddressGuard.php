<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Security;

defined('ABSPATH') || exit;

use WP_HTML_Tag_Processor;

/**
 * Takes off every address in drawn markup that a reader should not follow (spec 120, FR-030).
 *
 * A template's values are escaped as they are merged, so a value never adds markup; but a value
 * merged into an address can still be the whole address, `javascript:` included. After merging,
 * every tag is read by WordPress's own HTML parser, as a browser reads it (unquoted attributes, a
 * link that is never closed, an attribute named like `href`), and an `href` that is not http,
 * https, mailto or tel, or a `src` that is not http or https, is removed. The element keeps its
 * words: a link without an address is text.
 */
final readonly class AddressGuard
{
    /** The attributes that are addresses, each with the schemes it may have. */
    private const ADDRESSES = [
        'href'       => ['http', 'https', 'mailto', 'tel'],
        'xlink:href' => ['http', 'https', 'mailto', 'tel'],
        'src'        => ['http', 'https'],
    ];

    public function sanitize(string $html): string
    {
        if (preg_match('/href|src/i', $html) !== 1) {
            return $html;
        }
        $tags = new WP_HTML_Tag_Processor($html);
        while ($tags->next_tag()) {
            foreach (self::ADDRESSES as $attribute => $schemes) {
                $address = $tags->get_attribute($attribute);
                if ($address !== null && ! $this->allowed($address, $schemes)) {
                    $tags->remove_attribute($attribute);
                }
            }
        }

        return $tags->get_updated_html();
    }

    /**
     * Whether an address, as the parser decoded it, has one of the schemes: the spaces and
     * control characters a browser ignores in a scheme ("java\tscript:") taken out first.
     *
     * @param string|true  $address An attribute's value; `true` when it is written with none.
     * @param list<string> $schemes
     */
    private function allowed(string|bool $address, array $schemes): bool
    {
        if (! is_string($address)) {
            return false;
        }
        $address = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $address);

        return preg_match('/^([a-z][a-z0-9+.-]*):/i', $address, $scheme) === 1
            && in_array(strtolower($scheme[1]), $schemes, true);
    }
}
