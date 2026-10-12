<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
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

    public function __construct(
        private ConfigInterface $config,
        private EventDispatcher $events,
    ) {
    }

    public function brand(): EmailBrand
    {
        $brand = $this->fromSettings();

        // A site's filter is code CoreX did not write, running while a customer is answered.
        // If it throws (a colour that is not #rrggbb does) or answers something that is not a
        // brand, the email is drawn from the brand the site's settings gave, and whoever
        // manages email is told: a typing mistake in a colour would otherwise show as CoreX's
        // colours and nothing else.
        try {
            /**
             * What this site's emails are drawn from.
             *
             * @param EmailBrand $brand The site's brand, with its Mail settings applied.
             */
            $given = apply_filters(self::FILTER, $brand);
        } catch (Throwable $error) {
            $this->events->dispatch(new EmailDesignFailedEvent(
                self::FILTER,
                EmailDesignFailedEvent::BRAND_THREW,
                mb_substr(trim((string) strtok(strip_tags($error->getMessage()), "\r\n")), 0, 200),
            ));

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
        return $this->ownMark()[0];
    }

    /**
     * The site's own mark, with its size where WordPress recorded one.
     *
     * @return array{string,int,int} Its address, width and height; 0 and 0 for a size not known.
     */
    private function ownMark(): array
    {
        $customLogoId = (int) get_theme_mod('custom_logo');
        $logo = $customLogoId > 0 ? wp_get_attachment_image_url($customLogoId, 'medium') : false;
        if (is_string($logo) && self::drawable($logo)) {
            return [$logo, ...self::recordedSize($customLogoId)];
        }
        $icon = (string) get_site_icon_url(180);

        return self::drawable($icon) ? [$icon, ...self::recordedSize((int) get_option('site_icon'))] : ['', 0, 0];
    }

    /**
     * A logo a mail client draws. A vector image is not one: Gmail and Outlook on Windows show
     * nothing for it, and the site's name is a better header than a gap.
     */
    private static function drawable(string $address): bool
    {
        return $address !== '' && preg_match('/\.svgz?(?:[?#].*)?$/i', $address) !== 1;
    }

    /**
     * The size WordPress recorded for an image of the site's library, whose proportions every
     * cut of it keeps. Asked of the record and not of `wp_get_attachment_image_src()`, which
     * answers 1 by 1 for an image it holds no size for.
     *
     * @return array{int,int} Width and height, or 0 and 0.
     */
    private static function recordedSize(int $attachmentId): array
    {
        $recorded = $attachmentId > 0 ? wp_get_attachment_metadata($attachmentId) : false;
        $width = is_array($recorded) ? (int) ($recorded['width'] ?? 0) : 0;
        $height = is_array($recorded) ? (int) ($recorded['height'] ?? 0) : 0;

        return $width > 0 && $height > 0 ? [$width, $height] : [0, 0];
    }

    /**
     * The size of a logo the Mail settings name by its address.
     *
     * Known only for an image under this site's own uploads: what the library recorded, or,
     * for a cut WordPress made, the size it wrote into the cut's name (`-300x150.png`). A name
     * elsewhere that happens to end so says nothing about the image (`logo-16x9.png`).
     *
     * @return array{int,int} Width and height, or 0 and 0.
     */
    private function sizeOfNamed(string $address): array
    {
        $path = (string) strtok($address, '?#');
        $uploads = (string) (wp_get_upload_dir()['baseurl'] ?? '');
        if ($uploads === '' || stripos((string) set_url_scheme($path, 'https'), (string) set_url_scheme($uploads, 'https') . '/') !== 0) {
            return [0, 0];
        }
        $size = self::recordedSize((int) attachment_url_to_postid($path));
        if ($size !== [0, 0]) {
            return $size;
        }

        return preg_match('/-(\d{1,5})x(\d{1,5})\.[a-z0-9]+$/i', $path, $said) === 1
            ? [(int) $said[1], (int) $said[2]]
            : [0, 0];
    }

    /** The site as WordPress knows it, with what its Mail settings say about its emails. */
    private function fromSettings(): EmailBrand
    {
        $home = (string) home_url('/');
        // WordPress stores the name with its entities encoded; a design escapes it itself.
        $name = trim(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES));
        // A mail client has no page to resolve a path from: only a whole web address is a logo.
        $ownLogo = trim((string) $this->config->get('mail.brand.logo', ''));
        $ownLogo = preg_match('#^https?://#i', $ownLogo) === 1 ? $ownLogo : '';
        [$logo, $logoWidth, $logoHeight] = self::drawable($ownLogo)
            ? [$ownLogo, ...$this->sizeOfNamed($ownLogo)]
            : $this->ownMark();
        $brand = EmailBrand::standard(
            // A site with no title is still named in its emails, by where it is.
            $name !== '' ? $name : (string) wp_parse_url($home, PHP_URL_HOST),
            $home,
            $logo,
            is_rtl() ? 'rtl' : 'ltr',
            (string) get_bloginfo('language'),
            trim((string) $this->config->get('mail.brand.footer', '')),
        )->withLogoSize($logoWidth, $logoHeight);

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
