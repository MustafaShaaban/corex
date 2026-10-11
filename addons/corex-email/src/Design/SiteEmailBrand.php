<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * This site's email brand, read from WordPress when an email is drawn: its name, its address,
 * its logo, its direction and its language, in CoreX's colours.
 */
final class SiteEmailBrand implements EmailBrandSource
{
    public function brand(): EmailBrand
    {
        return EmailBrand::standard(
            // WordPress stores the name with its entities encoded; a design escapes it itself.
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            (string) home_url('/'),
            $this->logo(),
            is_rtl() ? 'rtl' : 'ltr',
            (string) get_bloginfo('language'),
        );
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
}
