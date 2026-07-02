<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Server render for the Newsletter Signup block (spec 063, Phase 7). It renders a REAL double opt-in
 * signup form wired to the existing `corex/v1/newsletter/subscribe` REST route (provided by the
 * corex-newsletter add-on): email + explicit consent + a honeypot, an accessible live status region,
 * and the endpoint/nonce carried on the form for the view script. It is gated on the optional
 * corex-newsletter add-on (Principle IX) — when the add-on is inactive it renders an honest
 * "unavailable" note, never a form that silently fails. No fabricated success: the visitor sees the
 * real endpoint's outcome (a confirmation email is sent for new/pending subscribers).
 */
final class NewsletterSignupRenderer implements BlockRenderer
{
    private const NEWSLETTER_PLUGIN = 'corex-newsletter/corex-newsletter.php';

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        if (! $this->newsletterActive()) {
            return '<div class="corex-newsletter-signup corex-newsletter-signup--unavailable">'
                . '<p>' . esc_html__('Newsletter signup is not available on this site yet.', 'corex') . '</p></div>';
        }

        $heading  = $this->attr($attributes, 'heading', __('Subscribe to our newsletter', 'corex'));
        $button   = $this->attr($attributes, 'buttonLabel', __('Subscribe', 'corex'));
        $consent  = $this->attr(
            $attributes,
            'consentText',
            __('I agree to receive emails and can unsubscribe at any time.', 'corex'),
        );

        $endpoint = esc_url(rest_url('corex/v1/newsletter/subscribe'));
        $nonce    = esc_attr(wp_create_nonce('wp_rest'));

        return '<div class="corex-newsletter-signup">'
            . '<form class="corex-newsletter-signup__form" data-corex-newsletter="' . $endpoint . '"'
            . ' data-corex-newsletter-nonce="' . $nonce . '" novalidate>'
            . ( $heading !== '' ? '<h2 class="corex-newsletter-signup__heading">' . esc_html($heading) . '</h2>' : '' )
            . '<div class="corex-newsletter-signup__row">'
            . '<label class="screen-reader-text" for="corex-newsletter-email">'
            . esc_html__('Email address', 'corex') . '</label>'
            . '<input class="corex-newsletter-signup__email" id="corex-newsletter-email" type="email"'
            . ' name="email" required autocomplete="email"'
            . ' placeholder="' . esc_attr__('you@example.com', 'corex') . '" />'
            . '<button class="corex-newsletter-signup__submit" type="submit">' . esc_html($button) . '</button>'
            . '</div>'
            . '<label class="corex-newsletter-signup__consent">'
            . '<input type="checkbox" name="consent" value="1" required /> '
            . '<span>' . esc_html($consent) . '</span></label>'
            // Honeypot: visually hidden, must stay empty. Bots that fill it are rejected server-side.
            . '<div class="corex-newsletter-signup__hp" aria-hidden="true">'
            . '<label>' . esc_html__('Leave this field empty', 'corex') . '<input type="text" name="corex_hp"'
            . ' tabindex="-1" autocomplete="off" /></label></div>'
            . '<p class="corex-newsletter-signup__status" role="status" aria-live="polite"></p>'
            . '</form></div>';
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function attr(array $attributes, string $key, string $default): string
    {
        $value = isset($attributes[$key]) ? trim((string) $attributes[$key]) : '';

        return $value !== '' ? $value : $default;
    }

    private function newsletterActive(): bool
    {
        return in_array(
            self::NEWSLETTER_PLUGIN,
            array_map('strval', (array) get_option('active_plugins', [])),
            true,
        );
    }
}
