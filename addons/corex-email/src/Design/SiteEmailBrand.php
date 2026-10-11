<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;
use Throwable;

/**
 * This site's email brand, read when an email is drawn: its name, its address, its mark, its
 * direction and its language, in CoreX's colours unless the site gives its own (spec 106,
 * FR-015).
 *
 * A site gives its own in two ways, and neither is a framework file:
 *
 * - **CoreX → Settings → Mail**: the accent colour, a logo and the footer line. No code.
 * - **The filter `corex_email_brand`**, from the site's plugin, for the colours a setting does
 *   not reach. It is handed the brand as the settings left it and returns one.
 */
final readonly class SiteEmailBrand implements EmailBrandSource
{
    public const FILTER = 'corex_email_brand';

    public function __construct(private ConfigInterface $config)
    {
    }

    public function brand(): EmailBrand
    {
        $brand = $this->fromSettings();

        // A site's filter is code CoreX did not write, running while a customer is answered.
        // If it throws (a colour that is not #rrggbb does) or answers something that is not a
        // brand, the email is drawn from the brand the site's settings gave.
        try {
            /**
             * What this site's emails are drawn from.
             *
             * @param EmailBrand $brand The site's brand, with its Mail settings applied.
             */
            $given = apply_filters(self::FILTER, $brand);
        } catch (Throwable) {
            return $brand;
        }

        return $given instanceof EmailBrand ? $given : $brand;
    }

    /**
     * An absolute address of the site's mark, or ''.
     *
     * The theme's custom logo first: it is the mark the site's owner chose. The site icon
     * second: a site that set one has still said which mark is its own. Both are absolute,
     * which matters here more than anywhere: a mail client has no page to resolve a path from.
     */
    public function logo(): string
    {
        $customLogoId = (int) get_theme_mod('custom_logo');
        if ($customLogoId > 0) {
            $logo = wp_get_attachment_image_url($customLogoId, 'medium');
            if (is_string($logo) && $logo !== '') {
                return $logo;
            }
        }

        return (string) get_site_icon_url(180);
    }

    /** The site as WordPress knows it, with what its Mail settings say about its emails. */
    private function fromSettings(): EmailBrand
    {
        $home = (string) home_url('/');
        // WordPress stores the name with its entities encoded; a design escapes it itself.
        $name = trim(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES));
        $ownLogo = trim((string) $this->config->get('mail.brand.logo', ''));
        $brand = EmailBrand::standard(
            // A site with no title is still named in its emails, by where it is.
            $name !== '' ? $name : (string) wp_parse_url($home, PHP_URL_HOST),
            $home,
            $ownLogo !== '' ? $ownLogo : $this->logo(),
            is_rtl() ? 'rtl' : 'ltr',
            (string) get_bloginfo('language'),
            trim((string) $this->config->get('mail.brand.footer', '')),
        );

        // The edge of the card, in both schemes: it is never text, so it has no contrast to keep.
        // The setting is not saved unless it is #rrggbb; a value that reached the store some
        // other way is passed over here, not thrown at somebody sending a reply.
        $accent = strtolower(trim((string) $this->config->get('mail.brand.accent', '')));
        if (preg_match('/^#[0-9a-f]{6}\z/', $accent) !== 1) {
            return $brand;
        }

        return $brand->withPalettes(
            $brand->light->with(['accent' => $accent]),
            $brand->dark->with(['accent' => $accent]),
        );
    }
}
