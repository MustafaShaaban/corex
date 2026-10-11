<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * What an email is drawn from: whose it is, and in which colours and type (spec 106).
 *
 * The header shows the site's name and logo, not CoreX's: the email is from the site to its
 * customer. The colours and the type are CoreX's until a site gives its own.
 */
final readonly class EmailBrand
{
    /**
     * The admin's body face, as a mail client can use it: no self-hosted font, which a client
     * will not load. Single quotes only: the value is written inside `style="…"`.
     */
    public const FONT = "system-ui, -apple-system, 'Segoe UI', Roboto, Tahoma, Arial, sans-serif";

    /** `--corex-admin-space-lg`, in the pixels a mail client honours. */
    public const SPACE = '24px';

    /** `--corex-admin-radius-md`. */
    public const RADIUS = '10px';

    /** `--corex-admin-line-normal`. */
    public const LINE_HEIGHT = '1.6';

    /**
     * @param string $name      The site's name.
     * @param string $homeUrl   The site's address, for the footer.
     * @param string $logo      An absolute address of the site's logo, or ''.
     * @param string $direction `ltr` or `rtl`: the site's own direction.
     * @param string $language  A language tag for the document, e.g. `en-GB`.
     * @param string $footer    The footer's line, or '' for the standard one naming the site.
     */
    public function __construct(
        public string $name,
        public string $homeUrl,
        public string $logo,
        public string $direction,
        public string $language,
        public string $footer,
        public EmailPalette $light,
        public EmailPalette $dark,
    ) {
        // Written into the document as it is, so it is one of the two or it is refused.
        if (! in_array($this->direction, ['ltr', 'rtl'], true)) {
            throw new InvalidArgumentException('An email reads ltr or rtl.');
        }
    }

    /** This brand with another footer line, or '' for the standard one naming the site. */
    public function withFooter(string $footer): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $this->direction, $this->language, $footer, $this->light, $this->dark);
    }

    /** This brand in other colours. */
    public function withPalettes(EmailPalette $light, EmailPalette $dark): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $this->direction, $this->language, $this->footer, $light, $dark);
    }

    /** A site's emails in CoreX's own colours. */
    public static function standard(
        string $name,
        string $homeUrl,
        string $logo = '',
        string $direction = 'ltr',
        string $language = 'en',
        string $footer = '',
    ): self {
        return new self(
            $name,
            $homeUrl,
            $logo,
            $direction === 'rtl' ? 'rtl' : 'ltr',
            $language,
            $footer,
            EmailPalette::light(),
            EmailPalette::dark(),
        );
    }
}
