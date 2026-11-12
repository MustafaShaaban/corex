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

    /** The box a logo is drawn inside, in pixels: no wider, no taller, whatever its shape. */
    public const LOGO_WIDTH = 180;
    public const LOGO_HEIGHT = 48;

    /**
     * @param string $name      The site's name.
     * @param string $homeUrl   The site's address, for the footer.
     * @param string $logo      An absolute address of the site's logo, or ''.
     * @param string $direction `ltr` or `rtl`: the site's own direction.
     * @param string $language  A language tag for the document, e.g. `en-GB`.
     * @param string $footer    The footer's line, or '' for the standard one naming the site.
     * @param int $logoWidth    The logo's own width in pixels, or 0 where it is not known.
     * @param int $logoHeight   Its own height, or 0. A design fits the logo to its header from these.
     */
    public int $logoWidth;
    public int $logoHeight;

    public function __construct(
        public string $name,
        public string $homeUrl,
        public string $logo,
        public string $direction,
        public string $language,
        public string $footer,
        public EmailPalette $light,
        public EmailPalette $dark,
        int $logoWidth = 0,
        int $logoHeight = 0,
    ) {
        // A size is a width and a height, or it is no size: half of one fits nothing.
        $sized = $logoWidth > 0 && $logoHeight > 0;
        $this->logoWidth = $sized ? $logoWidth : 0;
        $this->logoHeight = $sized ? $logoHeight : 0;
        // Written into the document as it is, so it is one of the two or it is refused.
        if (! in_array($this->direction, ['ltr', 'rtl'], true)) {
            throw new InvalidArgumentException('An email reads ltr or rtl.');
        }
    }

    /**
     * The size a logo is drawn at: inside the box, its proportions kept, never enlarged.
     *
     * @return array{int,int}|null Width and height; null where the logo's own size is not known.
     */
    public function logoBox(): ?array
    {
        if ($this->logoWidth < 1 || $this->logoHeight < 1) {
            return null;
        }
        $scale = min(1, self::LOGO_WIDTH / $this->logoWidth, self::LOGO_HEIGHT / $this->logoHeight);

        return [max(1, (int) round($this->logoWidth * $scale)), max(1, (int) round($this->logoHeight * $scale))];
    }

    /** This brand with another footer line, or '' for the standard one naming the site. */
    public function withFooter(string $footer): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $this->direction, $this->language, $footer, $this->light, $this->dark, $this->logoWidth, $this->logoHeight);
    }

    /**
     * This brand with its logo's own size said, in pixels: what a brand made in a site's own
     * filter calls, so that a design can fit the logo it names.
     */
    public function withLogoSize(int $width, int $height): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $this->direction, $this->language, $this->footer, $this->light, $this->dark, $width, $height);
    }

    /**
     * Whether the logo is the name written out, and not a mark the name stands beside.
     *
     * Told by its shape, which is all that is known of it: at least twice as wide as it is
     * tall reads as a line of lettering. A squarer one is a mark, and a design writes the
     * site's name beside it as text, so that the name is there when images are not shown.
     */
    public function logoIsItsName(): bool
    {
        return $this->logoWidth >= 2 * $this->logoHeight && $this->logoHeight > 0;
    }

    /** This brand read in the other direction: the preview's right-to-left view (spec 120). */
    public function withDirection(string $direction): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $direction === 'rtl' ? 'rtl' : 'ltr', $this->language, $this->footer, $this->light, $this->dark, $this->logoWidth, $this->logoHeight);
    }

    /** This brand in other colours. */
    public function withPalettes(EmailPalette $light, EmailPalette $dark): self
    {
        return new self($this->name, $this->homeUrl, $this->logo, $this->direction, $this->language, $this->footer, $light, $dark, $this->logoWidth, $this->logoHeight);
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
