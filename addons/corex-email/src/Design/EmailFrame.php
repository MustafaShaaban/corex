<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Closure;
use Corex\Mail\EmailLanguage;

/**
 * The one frame every email CoreX draws is in (spec 120, story 3): a reply, a form notification,
 * a newsletter email, an Email Studio template, an account email.
 *
 * It asks for the site's brand when an email is drawn (its logo, the accent, the footer line set
 * in Settings → Mail) and hands the email to the site's design, which is CoreX's standard one
 * unless a site gives its own for that kind of email. A message a site's own code hands over
 * finished is never drawn here: it is sent as it was given.
 */
final readonly class EmailFrame
{
    /**
     * @param Closure():string|null $privacyPage Answers the site's privacy page's address, or ''
     *                                           where it has none published.
     */
    public function __construct(
        private EmailDesign $design,
        private EmailBrandSource $brands,
        private ?Closure $privacyPage = null,
        /** Draws in the email's language: the site's, whoever's request it is. */
        private ?EmailLanguage $language = null,
    ) {
    }

    /**
     * The whole document of one email.
     *
     * @param string      $accent    `#rrggbb` for this email's edge (an Email Studio layout's own
     *                               accent), or '' for the site's.
     * @param string|null $direction `ltr` or `rtl` to draw it in that direction whatever the site
     *                               reads in (the preview's right-to-left view), or null.
     */
    public function draw(EmailContent $content, string $accent = '', ?string $direction = null): string
    {
        return $this->inEmailLanguage(fn (): string => $this->design->render(
            $this->withSiteLinks($content),
            $this->brand($accent, $direction),
        ));
    }

    /**
     * The footer as the plain-text version ends: the footer's line and, for a kind that has one,
     * why the person receives the email, under the signature separator; then each link the
     * email's footer gives (a newsletter's Unsubscribe), with its address, as the HTML footer
     * links to it (spec 120, FR-012).
     *
     * @param list<array{label:string,url:string}> $links The email's own footer links.
     */
    public function plainFooter(string $kind, array $links = []): string
    {
        return $this->inEmailLanguage(fn (): string => $this->footerLines($kind) . $this->linkLines($links));
    }

    /**
     * The plain-text signature a reply has always ended with: the footer's line under the
     * signature separator, and nothing else (spec 120, FR-016: replies as before).
     */
    public function plainSignature(): string
    {
        return $this->inEmailLanguage(fn (): string => "-- \n" . $this->footerLine($this->brands->brand()));
    }

    /** @param list<array{label:string,url:string}> $links */
    private function linkLines(array $links): string
    {
        $lines = '';
        foreach ($links as $link) {
            // As the HTML footer takes them (EmailContent): a label and a web address each.
            if (trim($link['label']) === '' || preg_match('#^https?://#i', $link['url']) !== 1) {
                continue;
            }
            /* translators: 1: a footer link's label, such as "Unsubscribe". 2: its web address. */
            $lines .= "\n" . sprintf(__('%1$s: %2$s', 'corex'), $link['label'], $link['url']);
        }

        return $lines;
    }

    private function footerLines(string $kind): string
    {
        $brand = $this->brands->brand();
        $reason = EmailKind::reason(EmailKind::of($kind), $brand->name);

        return "-- \n" . $this->footerLine($brand) . ($reason === '' ? '' : "\n" . $reason);
    }

    /** The footer's line: Settings → Mail's, or who sent the email. */
    private function footerLine(EmailBrand $brand): string
    {
        return $brand->footer !== ''
            ? $brand->footer
            /* translators: 1: the name of the site an email was sent from. 2: the site's address. */
            : sprintf(__('This message was sent by %1$s (%2$s).', 'corex'), $brand->name, $brand->homeUrl);
    }

    /** The site's name as its emails show it: what a region that only repeats it is told by. */
    public function siteName(): string
    {
        return $this->brands->brand()->name;
    }

    /**
     * @template T
     *
     * @param Closure():T $draw
     *
     * @return T
     */
    private function inEmailLanguage(Closure $draw): mixed
    {
        return $this->language === null ? $draw() : $this->language->during($draw);
    }

    private function brand(string $accent, ?string $direction): EmailBrand
    {
        $brand = $this->brands->brand();
        $accent = strtolower(trim($accent));
        if (preg_match('/^#[0-9a-f]{6}\z/', $accent) === 1) {
            $brand = $brand->withPalettes(
                $brand->light->with(['accent' => $accent]),
                $brand->dark->with(['accent' => $accent]),
            );
        }

        return $direction === null ? $brand : $brand->withDirection($direction);
    }

    /** The email's own links, and the site's privacy page where it has a published one. */
    private function withSiteLinks(EmailContent $content): EmailContent
    {
        $privacy = $this->privacyPage === null ? '' : (string) ($this->privacyPage)();
        $named = array_column($content->links, 'url');
        if ($privacy === '' || in_array($privacy, $named, true)) {
            return $content;
        }

        return new EmailContent(
            $content->subject,
            $content->bodyHtml,
            $content->recipient,
            $content->kind,
            [...$content->links, ['label' => __('Privacy', 'corex'), 'url' => $privacy]],
        );
    }
}
