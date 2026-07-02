<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Server render for the privacy-friendly Blog social-share block (spec 063, Phase 7). It shares the
 * REAL current post permalink + title via plain share-intent links — no third-party scripts, no
 * tracking pixels, and no fabricated share counts. The links work without JavaScript (real hrefs);
 * the copy-link and native-share controls are progressive enhancement (rendered hidden, revealed by
 * the view script). Icon-only controls carry accessible names; the bar is a labelled group. Thin per
 * Principle: it only builds escaped, translation-ready markup from the current post.
 */
final class SocialShareRenderer implements BlockRenderer
{
    /** The networks this block can render, in display order. */
    private const NETWORKS = ['x', 'facebook', 'linkedin', 'whatsapp', 'email'];

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $permalink = (string) get_permalink();
        if ($permalink === '') {
            return ''; // No current post (e.g. rendered outside a post context) — render nothing, never a fake.
        }

        $title    = (string) get_the_title();
        $networks = $this->selectedNetworks($attributes);

        $links = '';
        foreach ($networks as $network) {
            $links .= $this->networkLink($network, $permalink, $title);
        }

        return '<div class="corex-social-share" role="group" aria-label="'
            . esc_attr__('Share this post', 'corex') . '">'
            . '<span class="corex-social-share__label">' . esc_html__('Share', 'corex') . '</span>'
            . $links
            . $this->copyButton($permalink)
            . $this->nativeButton()
            . '</div>';
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return list<string>
     */
    private function selectedNetworks(array $attributes): array
    {
        $requested = $attributes['networks'] ?? null;

        if (! is_array($requested) || $requested === []) {
            return self::NETWORKS;
        }

        // Keep the canonical order and only known networks — an unknown value is dropped, never rendered.
        return array_values(array_filter(
            self::NETWORKS,
            static fn (string $network): bool => in_array($network, $requested, true),
        ));
    }

    private function networkLink(string $network, string $url, string $title): string
    {
        $shareUrl = $this->shareUrl($network, $url, $title);
        if ($shareUrl === '') {
            return '';
        }

        // mailto opens the mail client; the rest open a new tab with a safe rel and no referrer leakage.
        $attrs = $network === 'email'
            ? ''
            : ' target="_blank" rel="noopener noreferrer nofollow"';

        return sprintf(
            '<a class="corex-social-share__link corex-social-share__link--%1$s" href="%2$s"%3$s>'
            . '%4$s<span class="screen-reader-text">%5$s</span></a>',
            esc_attr($network),
            esc_url($shareUrl),
            $attrs,
            $this->icon($network),
            esc_html($this->networkLabel($network)),
        );
    }

    private function shareUrl(string $network, string $url, string $title): string
    {
        $encodedUrl   = rawurlencode($url);
        $encodedTitle = rawurlencode($title);

        return match ($network) {
            'x'        => 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedTitle,
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl,
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl,
            'whatsapp' => 'https://api.whatsapp.com/send?text=' . rawurlencode($title . ' ' . $url),
            'email'    => 'mailto:?subject=' . $encodedTitle . '&body=' . $encodedUrl,
            default    => '',
        };
    }

    private function networkLabel(string $network): string
    {
        return match ($network) {
            'x'        => __('Share on X', 'corex'),
            'facebook' => __('Share on Facebook', 'corex'),
            'linkedin' => __('Share on LinkedIn', 'corex'),
            'whatsapp' => __('Share on WhatsApp', 'corex'),
            'email'    => __('Share by email', 'corex'),
            default    => __('Share', 'corex'),
        };
    }

    /**
     * Copy-link control: rendered hidden and revealed by the view script (progressive enhancement), so
     * no-JS visitors never see an inert button. Carries the real URL for the script to copy.
     */
    private function copyButton(string $url): string
    {
        return sprintf(
            '<button type="button" class="corex-social-share__copy" data-corex-share-copy="%1$s" hidden>'
            . '%2$s<span class="corex-social-share__copy-label">%3$s</span></button>',
            esc_attr($url),
            $this->icon('copy'),
            esc_html__('Copy link', 'corex'),
        );
    }

    /**
     * Native-share control: hidden until the view script confirms the Web Share API exists.
     */
    private function nativeButton(): string
    {
        return sprintf(
            '<button type="button" class="corex-social-share__native" data-corex-share-native hidden>'
            . '%1$s<span class="screen-reader-text">%2$s</span></button>',
            $this->icon('share'),
            esc_html__('Share via your device', 'corex'),
        );
    }

    /** A tiny inline, decorative SVG glyph (aria-hidden); the accessible name is the sibling text. */
    private function icon(string $name): string
    {
        $paths = [
            'x'        => '<path d="M4 3l6.5 8.5L4.3 21H6l5.4-6.6L16 21h4l-6.8-9L19.6 3H18l-5 6.1L8.5 3H4z"/>',
            'facebook' => '<path d="M15 8h2V5h-2a3 3 0 00-3 3v2H9v3h3v6h3v-6h2.2l.4-3H15V8z"/>',
            'linkedin' => '<path d="M6 9v9H3V9h3zM4.5 4a1.6 1.6 0 100 3.2A1.6 1.6 0 004.5 4zM21 18h-3v-4.5c0-1.2-.5-1.9-1.5-1.9s-1.5.7-1.5 1.9V18h-3V9h3v1.2c.5-.8 1.4-1.4 2.6-1.4 2 0 3.4 1.3 3.4 4V18z"/>',
            'whatsapp' => '<path d="M12 3a9 9 0 00-7.7 13.6L3 21l4.5-1.2A9 9 0 1012 3zm0 2a7 7 0 016 10.6l-.3.5.7 2.4-2.5-.7-.5.3A7 7 0 1112 5z"/>',
            'email'    => '<path d="M3 5h18v14H3V5zm2 2v.4l7 4.6 7-4.6V7H5zm14 3.3l-6.4 4.2a1 1 0 01-1.2 0L5 10.3V17h14v-6.7z"/>',
            'copy'     => '<path d="M9 3h9v13H9V3zm-2 4H5v14h11v-2H7V7z"/>',
            'share'    => '<path d="M14 9V5l7 7-7 7v-4C9 12 6 14 4 18c0-6 4-9 10-9z"/>',
        ];

        return '<svg class="corex-social-share__icon" viewBox="0 0 24 24" width="18" height="18" '
            . 'fill="currentColor" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
    }
}
