<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * What one email says, handed to a design to be drawn (spec 106, FR-016).
 */
final readonly class EmailContent
{
    /** @var list<array{label:string,url:string}> */
    public array $links;

    public string $kind;

    /**
     * @param string $subject   Plain text, not escaped.
     * @param string $bodyHtml  The message. A reply's is already reduced to paragraphs, line
     *                          breaks, emphasis, lists and links; a template's is the markup its
     *                          template wrote, its values escaped. A design does not have to clean it.
     * @param string $recipient The address the email is for.
     * @param string $kind      What kind of email it is ({@see EmailKind}); a reply unless said
     *                          (spec 120). Last but one, so every earlier caller means a reply.
     * @param list<array{label:string,url:string}> $links What the footer links to: the privacy
     *                          page, an unsubscribe link. Each address is a web address.
     */
    public function __construct(
        public string $subject,
        public string $bodyHtml,
        public string $recipient,
        string $kind = EmailKind::REPLY,
        array $links = [],
    ) {
        $this->kind = EmailKind::of($kind);
        $this->links = array_values(array_filter(
            $links,
            static fn (mixed $link): bool => is_array($link)
                && is_string($link['label'] ?? null) && trim($link['label']) !== ''
                && is_string($link['url'] ?? null) && preg_match('#^https?://#i', $link['url']) === 1,
        ));
    }
}
