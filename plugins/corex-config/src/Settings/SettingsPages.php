<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * The pages of the Settings screen (spec 119, US-SET-01, DECISIONS #440).
 *
 * The screen was one form of nine tabs with one button. Each page here is a form of its own with
 * its own address, reached from under Settings in the CoreX rail, and a save writes that page
 * only. An add-on's section is a page by itself, so it can leave the navigation when the add-on
 * is not installed and still be answered at its address.
 */
final class SettingsPages
{
    public const SCREEN = 'corex-settings-config';

    public const DEFAULT = 'general';

    /** @var array<string,SettingsPage>|null */
    private ?array $pages = null;

    /** @return array<string,SettingsPage> page key => page, in the order the navigation lists them */
    public function all(): array
    {
        return $this->pages ??= $this->declare();
    }

    public function find(string $key): ?SettingsPage
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The page an address asks for: `section=<page>`, or the older `corex_tab=<section>` the
     * Overview, Email Studio and the mail delivery policy link with. Null for a page that does
     * not exist; the first page when nothing is asked for.
     */
    public function requested(string $page, string $legacyTab): ?SettingsPage
    {
        if ($page !== '') {
            return $this->find($page);
        }

        if ($legacyTab !== '') {
            return $this->holding($legacyTab) ?? $this->find($legacyTab);
        }

        return $this->find(self::DEFAULT);
    }

    /** The page a registry section is saved on. */
    public function holding(string $sectionKey): ?SettingsPage
    {
        foreach ($this->all() as $page) {
            if ($page->holds($sectionKey)) {
                return $page;
            }
        }

        return null;
    }

    public function url(string $key): string
    {
        return admin_url('admin.php?page=' . self::SCREEN . '&section=' . rawurlencode($key));
    }

    /** @return array<string,SettingsPage> */
    private function declare(): array
    {
        $pages = [
            new SettingsPage(
                'general',
                __('General and appearance', 'corex'),
                __('Your company\'s details, how the CoreX admin looks, and the widgets on the WordPress dashboard.', 'corex'),
                ['brand', 'dashboard'],
            ),
            new SettingsPage(
                'email',
                __('Email', 'corex'),
                __('Who CoreX email is from, where replies and form notifications go, and how an email looks.', 'corex'),
                ['mail', 'forms'],
            ),
            new SettingsPage(
                'captcha',
                __('Captcha', 'corex'),
                __('The spam check in front of CoreX forms.', 'corex'),
                ['captcha'],
                new SettingsAddon('corex-captcha/corex-captcha.php', 'corex-captcha', __('Corex Captcha', 'corex')),
            ),
            new SettingsPage(
                'media',
                __('Media', 'corex'),
                __('WebP copies of uploaded images, and converting the images already in the library.', 'corex'),
                ['media'],
                new SettingsAddon('corex-media/corex-media.php', 'corex-media', __('Corex Media', 'corex')),
            ),
            new SettingsPage(
                'insights',
                __('Insights', 'corex'),
                __('The keys Insights uses for performance checks and the security scan.', 'corex'),
                ['insights'],
            ),
            new SettingsPage(
                'guides',
                __('Guides', 'corex'),
                __('Where questions from the Guides screen are sent.', 'corex'),
                ['guides'],
                new SettingsAddon('corex-guides/corex-guides.php', 'corex-guides', __('Corex Guides', 'corex')),
            ),
            new SettingsPage(
                'advanced',
                __('Advanced', 'corex'),
                __('Facts about this server and WordPress, for whoever supports the site.', 'corex'),
                ['advanced'],
            ),
        ];

        $keyed = [];
        foreach ($pages as $page) {
            $keyed[$page->key] = $page;
        }

        return $keyed;
    }
}
